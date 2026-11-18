<?php

namespace Tests\Feature;

use App\Models\{ClinicBranch, Customer, Specialty, User};
use App\Services\SpecialtyCatalog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FileSpecialtyCatalogTest extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as signingSetUp; tearDown as signingTearDown; }
    private bool $isolatedStarted = false;
    protected function setUp(): void
    {
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', getenv('DB_DATABASE') ?: '')) $this->markTestSkipped('Isolated MySQL only.');
        $this->signingSetUp(); $this->isolatedStarted = true;
        config(['identification'=>\Tests\Support\IdentifierStorageKeys::config()+['application_key'=>config('app.key')]]);
    }
    protected function tearDown(): void { if ($this->isolatedStarted) $this->signingTearDown(); }


    private function catalogId(string $name = 'Pediatría'): int
    {
        return (int) array_search($name, config('specialties.catalog'), true);
    }

    public function test_fixed_catalog_multi_selection_is_tenant_and_branch_scoped_without_granting_roles(): void
    {
        $admin = $this->user('Administrador'); $allied = $this->user('Terapeuta');
        $id = $this->catalogId(); $second = $this->catalogId('Psicología');
        $url = $this->root.'/users/'.$allied->id.'/specialties';
        $this->actingAs($admin)->patchJson($url, ['branch_id' => $this->branch->id, 'specialty_ids' => [-$id, -$second, -$id]])->assertOk();
        $rows = $allied->specialtiesForBranch($this->clinic->id, $this->branch->id)->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing([$id, $second], $rows->pluck('catalog_id')->all());
        $this->assertSame(['Terapeuta'], $allied->clinicRolesForBranch($this->clinic->id, $this->branch->id)->pluck('name')->all());
        $role = $allied->clinicRolesForBranch($this->clinic->id, $this->branch->id)->firstOrFail();
        $this->actingAs($admin)->patchJson($this->root.'/users/'.$allied->id.'/roles',
            ['branch_id' => $this->branch->id, 'role_ids' => [$role->id]])->assertOk();
        $this->assertCount(2, $allied->specialtiesForBranch($this->clinic->id, $this->branch->id)->get());
        $this->assertCount(0, app(SpecialtyCatalog::class)->offered($this->clinic->id));
        $permission = \App\Models\Permission::firstOrCreate(['slug' => 'medical_records.manage'],
            ['name' => 'Atención clínica', 'module' => 'clinical']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->assertCount(2, app(SpecialtyCatalog::class)->offered($this->clinic->id));
        $this->getJson($url.'?branch_id='.$this->branch->id)->assertOk()->assertJsonCount(90, 'specialties')
            ->assertJsonFragment(['assigned' => [-$id, -$second]]);
        $before = DB::table('specialty_user')->where('user_id', $allied->id)->get()->toJson();
        $this->patchJson($url, ['branch_id' => $this->branch->id, 'specialty_ids' => [-999999]])->assertUnprocessable();
        $this->assertSame($before, DB::table('specialty_user')->where('user_id', $allied->id)->get()->toJson());
        $other = ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Other', 'status' => 1]);
        $this->patchJson($url, ['branch_id' => $other->id, 'specialty_ids' => [-$id]])->assertForbidden();
        $this->actingAs($allied)->patchJson($url, ['branch_id' => $this->branch->id, 'specialty_ids' => []])->assertForbidden();
    }

    public function test_legacy_references_and_duplicate_assignments_are_not_merged_or_rewritten(): void
    {
        $service = app(SpecialtyCatalog::class); $id = $this->catalogId();
        $a = Specialty::create(['customer_id' => $this->clinic->id, 'name' => 'PEDIATRIA', 'catalog_id' => $id, 'status' => 1]);
        $b = Specialty::create(['customer_id' => $this->clinic->id, 'name' => 'Pediatra', 'catalog_id' => $id, 'status' => 1]);
        $unknown = Specialty::create(['customer_id' => $this->clinic->id, 'name' => 'Historical unknown', 'status' => 1]);
        foreach ([$a, $b, $unknown] as $s) $this->doctor->specialties()->attach($s->id, ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id]);
        $before = DB::table('specialty_user')->where('user_id', $this->doctor->id)->get()->toJson();
        $service->sync($this->doctor, $this->clinic->id, $this->branch->id, [-$id, $unknown->id]);
        $this->assertSame($before, DB::table('specialty_user')->where('user_id', $this->doctor->id)->get()->toJson());
        $this->assertSame('PEDIATRIA', $a->fresh()->name); $this->assertSame('Pediatra', $b->fresh()->name);
        $other = $this->user();
        try { $service->sync($other, $this->clinic->id, $this->branch->id, [$unknown->id]); $this->fail('Legacy cannot be newly assigned'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('specialties', $e->errors()); }
        $this->assertCount(91, $service->options($this->doctor, $this->clinic->id, $this->branch->id));
    }

    public function test_offer_requires_active_provider_membership_and_branch_and_has_no_duplicate_categories(): void
    {
        $service = app(SpecialtyCatalog::class); $id = $this->catalogId();
        $service->sync($this->doctor, $this->clinic->id, $this->branch->id, [-$id]);
        $local = $this->doctor->specialtiesForBranch($this->clinic->id, $this->branch->id)->first();
        $duplicate = Specialty::create(['customer_id' => $this->clinic->id, 'catalog_id' => $id, 'name' => 'Pediatra', 'status' => 1]);
        $this->doctor->specialties()->attach($duplicate->id, ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id]);
        $this->assertCount(1, $service->offered($this->clinic->id));
        $this->assertSame('Pediatría', $service->offered($this->clinic->id)->first()->name);
        $this->assertEqualsCanonicalizing([$local->id, $duplicate->id], $service->equivalentIds($this->clinic->id, $local->id));
        $this->doctor->update(['status' => 0]); $this->assertCount(0, $service->offered($this->clinic->id));
        $this->doctor->update(['status' => 1]); $this->branch->update(['status' => 0]); $this->assertCount(0, $service->offered($this->clinic->id));
        $this->branch->update(['status' => 1]);
        $this->doctor->branches()->updateExistingPivot($this->branch->id, ['status' => 0]);
        $this->assertCount(0, $service->offered($this->clinic->id));
        $this->assertSame(2, DB::table('specialty_user')->where('user_id', $this->doctor->id)->count());
    }

    public function test_catalog_maintenance_routes_and_commands_are_retired(): void
    {
        $admin = $this->user('Administrador');
        $this->actingAs($admin)->get($this->root.'/specialties')->assertNotFound();
        $this->postJson($this->root.'/specialties', ['name' => 'Cannot create'])->assertNotFound();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('superadmin.directory-specialties.index'));
        $commands = \Illuminate\Support\Facades\Artisan::all();
        $this->assertArrayNotHasKey('specialties:import-master', $commands);
        $this->assertArrayNotHasKey('directory:reconcile-specialties', $commands);
        $this->assertCount(90, config('specialties.catalog'));
    }

    public function test_public_booking_groups_equivalent_local_ids_and_respects_branch_roles(): void
    {
        \App\Models\WebsiteNavigationButton::create(['customer_id' => $this->clinic->id, 'public_booking_enabled' => true]);
        $id = $this->catalogId(); $service = app(SpecialtyCatalog::class);
        $service->sync($this->doctor, $this->clinic->id, $this->branch->id, [-$id]);
        $first = $this->doctor->specialtiesForBranch($this->clinic->id, $this->branch->id)->firstOrFail();
        $other = $this->user();
        $duplicate = Specialty::create(['customer_id' => $this->clinic->id, 'catalog_id' => $id, 'name' => 'PEDIATRIA', 'status' => 1]);
        $other->specialties()->attach($duplicate->id, ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id]);
        $this->assertCount(2, $service->equivalentIds($this->clinic->id, $first->id));
        $url = $this->root.'/agendar/profesionales?specialty_id='.$first->id.'&clinic_branch_id='.$this->branch->id;
        $this->getJson($url)->assertOk()->assertJsonCount(2);
        $request = \Illuminate\Http\Request::create('/', 'GET', ['specialty_id' => $first->id,
            'professional_id' => $other->id, 'clinic_branch_id' => $this->branch->id]);
        $method = new \ReflectionMethod(\App\Http\Controllers\customer\PublicBookingController::class, 'resolveCatalogSelection');
        $method->setAccessible(true); $method->invoke(app(\App\Http\Controllers\customer\PublicBookingController::class), $request);
        $this->assertSame($duplicate->id, $request->input('specialty_id'));
        $other->branchRoles()->detach();
        $this->getJson($url)->assertOk()->assertJsonCount(1);
        $this->branch->update(['status' => 0]);
        $this->getJson($this->root.'/agendar/profesionales?specialty_id='.$first->id)->assertOk()->assertJsonCount(0);
    }

    public function test_create_and_edit_professional_use_file_catalog_and_keep_local_references(): void
    {
        $admin = $this->user('Administrador');
        $role = $this->doctor->clinicRolesForBranch($this->clinic->id, $this->branch->id)->firstOrFail();
        $id = $this->catalogId();
        $data = ['name' => 'Created from catalog', 'identification_country'=>'AR','identification_type'=>'passport','identification_number'=>'CATALOG'.\Illuminate\Support\Str::random(8),
            'email' => \Illuminate\Support\Str::random(12).'@test.test', 'phone' => '123', 'status' => 1,
            'password' => 'password-test', 'role_ids' => [$role->id], 'branch_ids' => [$this->branch->id],
            'specialty_branch_id' => $this->branch->id, 'specialties' => [-$id]];
        $response = $this->actingAs($admin)->postJson($this->root.'/users', $data)->assertOk();
        $user = User::findOrFail($response->json('data.id'));
        $local = $user->specialtiesForBranch($this->clinic->id, $this->branch->id)->firstOrFail();
        $this->assertSame($id, (int)$local->catalog_id);
        $this->putJson($this->root.'/users/'.$user->id, array_merge($data, ['name' => 'Edited', 'password' => '']))->assertOk();
        $this->assertSame($local->id, $user->specialtiesForBranch($this->clinic->id, $this->branch->id)->firstOrFail()->id);
    }

    public function test_other_tenants_cannot_share_or_modify_local_references(): void
    {
        $foreign = $this->clinic->replicate();
        $foreign->rut = 'foreign-'.\Illuminate\Support\Str::random(12);
        $foreign->domain = 'foreign-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(12)); $foreign->save();
        $branch = $foreign->branches()->create(['name' => 'Foreign', 'status' => 1]);
        $user = $this->doctor->replicate(); $user->customer_id = $foreign->id;
        $user->email = \Illuminate\Support\Str::random(12).'@test.test'; $user->save();
        $user->branches()->attach($branch->id, ['status' => 1]);
        $service = app(SpecialtyCatalog::class); $id = $this->catalogId();
        $service->sync($this->doctor, $this->clinic->id, $this->branch->id, [-$id]);
        $service->sync($user, $foreign->id, $branch->id, [-$id]);
        $ownLocal = $this->doctor->specialtiesForBranch($this->clinic->id, $this->branch->id)->firstOrFail();
        $foreignLocal = $user->specialtiesForBranch($foreign->id, $branch->id)->firstOrFail();
        $this->assertNotSame($ownLocal->id, $foreignLocal->id);
        $this->assertSame((int)$ownLocal->catalog_id, (int)$foreignLocal->catalog_id);
        $admin = $this->user('Administrador');
        $this->actingAs($admin)->patchJson($this->root.'/users/'.$user->id.'/specialties',
            ['branch_id' => $branch->id, 'specialty_ids' => []])->assertNotFound();
        $this->assertSame([], $service->equivalentIds($this->clinic->id, $foreignLocal->id));
    }

    public function test_historical_chart_preserves_inactive_specialties_and_groups_only_normalized_categories(): void
    {
        $admin = $this->user('Administrador'); $id = $this->catalogId();
        foreach (['Pediatría', 'PEDIATRIA'] as $name) {
            $s = Specialty::create(['customer_id' => $this->clinic->id, 'catalog_id' => $id, 'name' => $name, 'status' => 0]);
            \App\Models\Appointment::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
                'patient_id' => $this->patient->id, 'professional_id' => $this->doctor->id, 'specialty_id' => $s->id,
                'status' => 3, 'start_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(30)]);
        }
        $this->doctor->update(['status' => 0]);
        $response = $this->actingAs($admin)->getJson($this->root.'/dashboard/statistics?year='.now()->subDay()->year)->assertOk();
        $rows = collect($response->json('appointmentsBySpecialty'))->where('specialty', 'Pediatría');
        $this->assertCount(1, $rows); $this->assertSame(2, $rows->first()['total']);
    }
}
