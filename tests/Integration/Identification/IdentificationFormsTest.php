<?php

namespace Tests\Integration\Identification;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\Identification\IdentificationForms;
use App\Services\Identification\Storage\IdentifierRepositoryFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class IdentificationFormsTest extends TestCase
{
    private User $super;

    private bool $transaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || ! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', DB::connection()->getDatabaseName())) {
            $this->markTestSkipped('Dedicated isolated identification schema only.');
        }
        config(['identification' => IdentifierStorageKeys::config() + ['application_key' => config('app.key')]]);
        DB::beginTransaction();
        $this->transaction = true;
        $this->super = User::factory()->create(['customer_id' => null, 'status' => 1]);
        $role = Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
        $this->super->roles()->attach($role->id);
        $this->actingAs($this->super);
    }

    protected function tearDown(): void
    {
        if ($this->transaction) {
            DB::rollBack();
        }parent::tearDown();
    }

    private function url(): string
    {
        return 'http://'.config('app.domain').'/customers';
    }

    private function data(string $country = 'CL'): array
    {
        $suffix = Str::lower(Str::random(12));

        return ['operating_declaration_accepted' => true, 'name' => 'Clinic'.$suffix, 'legal_name' => 'Test', 'holder' => 'Test', 'phone' => '123', 'email' => $suffix.'@test.test', 'status' => 1,
            'identification_enabled' => true, 'identification_country' => $country, 'identification_type' => 'tax_id', 'identification_holder' => 'legal_entity', 'identification_number' => $country === 'CL' ? '123456785' : '000A-é',
            'admin_name' => 'Identifier administrator', 'admin_email' => $suffix.'@admin.test', 'admin_phone' => '456', 'admin_password' => 'identifier-password', 'admin_password_confirmation' => 'identifier-password',
            'admin_identification_enabled' => true, 'admin_identification_country' => 'AR', 'admin_identification_type' => 'passport', 'admin_identification_number' => '001Ab-12'];
    }

    private function createClinic(string $country = 'CL'): array
    {
        $this->app->offsetUnset('customer'); // A real HTTP request starts with a fresh container.
        $response = $this->postJson($this->url(), $this->data($country))->assertOk();
        $customer = Customer::findOrFail($response->json('data.id'));

        return [$customer, User::where('customer_id', $customer->id)->firstOrFail(), $customer->branches()->firstOrFail()];
    }

    private function inClinic(Customer $c, User $u, $branch): string
    {
        $this->actingAs($u);
        app()->instance('customer', $c);
        session(['active_branch_customer_id' => $c->id, 'active_branch_id' => $branch->id]);

        return 'http://'.$c->domain.'.'.config('app.domain');
    }

    public function test_chilean_customer_and_foreign_administrator_are_atomic_and_encrypted(): void
    {
        [$c,$admin] = $this->createClinic();
        $this->assertSame('12.345.678-5', $c->rut);
        $this->assertNull($admin->rut);
        $row = DB::table('user_identifiers')->where('user_id', $admin->id)->sole();
        $this->assertStringNotContainsString('001Ab-12', $row->encrypted_payload);
        $this->assertEquals(1, $row->is_primary);
        $this->getJson($this->url().'/'.$c->id.'/administrators/'.$admin->id.'/identification')->assertOk()->assertJsonPath('identification_number', '001Ab-12')->assertJsonPath('identity_verified', false);
    }

    public function test_same_tax_document_can_belong_to_multiple_customers(): void
    {
        [$one] = $this->createClinic();
        [$two] = $this->createClinic();
        $this->assertNotSame($one->id, $two->id);
        $this->assertSame($one->rut, $two->rut);
    }

    public function test_foreign_customer_never_receives_a_fictitious_legacy_rut(): void
    {
        [$c] = $this->createClinic('AR');
        $this->assertNull($c->rut);
        $this->getJson($this->url().'/'.$c->id.'/identification')->assertOk()->assertJsonPath('identification_number', '000A-é');
    }

    public function test_central_administrative_name_correction_is_audited_without_document_values(): void
    {
        [$customer, $admin] = $this->createClinic('AR');
        $this->app->offsetUnset('customer');
        $admin->update(['name' => 'Administrative identity correction']);
        $log = \App\Models\ClinicActivityLog::where('customer_id', $customer->id)
            ->where('subject_type', User::class)->where('subject_id', $admin->id)->latest('id')->firstOrFail();
        $this->assertSame($this->super->id, $log->user_id);
        $this->assertContains('nombre', $log->changes['fields']);
        $this->assertStringNotContainsString('001Ab-12', json_encode($log->getAttributes()));
    }

    public function test_general_tables_do_not_return_complete_identity_documents(): void
    {
        [$customer, $admin, $branch] = $this->createClinic();
        $table = $this->getJson('http://'.config('app.domain').'/customer-data')->assertOk()->json('data');
        $row = collect($table)->firstWhere('id', $customer->id);
        $this->assertArrayNotHasKey('rut', $row);
        $this->assertArrayNotHasKey('rut', $row['administrators'][0]);
        $root = $this->inClinic($customer, $admin, $branch);
        $users = $this->getJson($root.'/users-data')->assertOk()->json('data');
        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertArrayNotHasKey('rut', $user);
            $this->assertArrayNotHasKey('document', $user);
        }
    }

    public function test_invalid_chilean_rut_rolls_back_customer_creation(): void
    {
        $data = $this->data();
        $data['identification_number'] = '12345678-4';
        $before = Customer::count();
        $this->postJson($this->url(), $data)->assertUnprocessable()->assertJsonValidationErrors('identification_number');
        $this->assertSame($before, Customer::count());
    }

    public function test_primary_change_preserves_prior_document_and_rejects_stale_write(): void
    {
        [$c] = $this->createClinic();
        $form = $this->getJson($this->url().'/'.$c->id.'/identification')->assertOk()->json();
        $request = $c->only(['name', 'legal_name', 'holder', 'email', 'phone', 'status']);
        $request += ['identification_enabled' => true, 'identification_country' => 'AR', 'identification_type' => 'tax_id', 'identification_holder' => 'legal_entity', 'identification_number' => '001NEW', 'identification_expected_primary' => $form['identification_expected_primary']];
        $this->putJson($this->url().'/'.$c->id, $request)->assertOk();
        $this->assertSame(2, DB::table('customer_identifiers')->where('customer_id', $c->id)->count());
        $this->assertSame('12.345.678-5', $c->fresh()->rut);
        $request['name'] = 'Must rollback';
        $request['identification_number'] = 'OTHER';
        $this->putJson($this->url().'/'.$c->id, $request)->assertUnprocessable()->assertJsonValidationErrors('identification_number');
        $this->assertNotSame('Must rollback', $c->fresh()->name);
    }

    public function test_unclassified_legacy_is_preserved_when_editing_contact_only(): void
    {
        $data = $this->data();
        [$c] = $this->createClinic();
        $legacy = Customer::create(['name' => 'Legacy'.Str::random(12), 'legal_name' => 'Legacy', 'holder' => 'Test', 'rut' => 'unknown-legacy', 'domain' => 'legacy'.Str::random(12), 'email' => 'legacy@test.test', 'phone' => '123', 'status' => 1]);
        $this->putJson($this->url().'/'.$legacy->id, $legacy->only(['name', 'legal_name', 'holder', 'rut', 'email', 'phone', 'status']))->assertOk();
        $this->assertSame('unknown-legacy', $legacy->fresh()->rut);
        $this->assertSame(0, DB::table('customer_identifiers')->where('customer_id', $legacy->id)->count());
    }

    public function test_foreign_staff_creation_and_tenant_isolation(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $role = Role::where('customer_id', $c->id)->where('name', 'Secretaria')->firstOrFail();
        $payload = ['name' => 'Foreign staff', 'phone' => '123', 'email' => Str::uuid().'@staff.test', 'password' => 'password-123', 'status' => 1, 'role_ids' => [$role->id], 'branch_ids' => [$branch->id],
            'identification_enabled' => true, 'identification_country' => 'PE', 'identification_type' => 'national_id', 'identification_number' => '00123'];
        $response = $this->postJson($root.'/users', $payload)->assertOk();
        $id = $response->json('data.id');
        $this->assertNull(User::findOrFail($id)->rut);
        $this->getJson($root.'/users/'.$id.'/identification')->assertOk()->assertJsonPath('identification_number', '00123');
        $this->actingAs($this->super);
        [$other,$otherAdmin,$otherBranch] = $this->createClinic();
        $otherRoot = $this->inClinic($other, $otherAdmin, $otherBranch);
        $this->getJson($otherRoot.'/users/'.$id.'/identification')->assertNotFound();
    }

    public function test_non_admin_cannot_modify_own_identifier(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $staff = User::create(['customer_id' => $c->id, 'name' => 'Staff', 'email' => Str::uuid().'@test.test', 'rut' => 'legacy', 'phone' => '123', 'status' => 1, 'password' => 'password-123']);
        $role = Role::where('customer_id', $c->id)->where('name', 'Secretaria')->firstOrFail();
        $staff->branches()->attach($branch->id, ['status' => true]);
        $staff->branchRoles()->attach($role->id, ['customer_id' => $c->id, 'clinic_branch_id' => $branch->id]);
        $staff->syncClinicRoleMirror($c->id);
        $this->actingAs($staff)->patchJson($root.'/profile', ['identification_enabled' => true, 'identification_country' => 'AR', 'identification_type' => 'passport', 'identification_number' => '123'])->assertUnprocessable();
        $this->assertSame('legacy', $staff->fresh()->rut);
    }

    public function test_profile_admin_can_change_document_and_validation_is_not_identity_verification(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $form = $this->getJson($root.'/users/'.$admin->id.'/identification')->assertOk()->json();
        $this->get($root.'/profile?_vita_content=1')->assertOk()->assertSee('País emisor del documento')->assertSee('Filtrar países')->assertDontSee('Buscar país emisor');
        $payload = $admin->only(['name', 'email', 'phone']);
        $payload += ['identification_enabled' => true, 'identification_country' => 'CL', 'identification_type' => 'national_id', 'identification_number' => '123456785', 'identification_expected_primary' => $form['identification_expected_primary']];
        $this->patch($root.'/profile', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('12.345.678-5', $admin->fresh()->rut);
        $this->assertSame(2, DB::table('user_identifiers')->where('user_id', $admin->id)->count());
        $this->getJson($root.'/users/'.$admin->id.'/identification')->assertJsonPath('identity_verified', false);
    }

    public function test_foreign_identity_does_not_prevent_explicit_authorized_doctor_role_assignment(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $doctor = Role::where('customer_id', $c->id)->where('name', 'Doctor')->firstOrFail();
        $this->patchJson($root.'/users/'.$admin->id.'/roles', ['role_ids' => [$doctor->id], 'branch_id' => $branch->id])->assertOk();
        $this->assertTrue($admin->branchRoles()->where('roles.name', 'Doctor')->exists());
    }

    public function test_blade_customer_and_professional_forms_render_without_new_dependencies(): void
    {
        $response = $this->get('http://'.config('app.domain').'/superadmin/customers?_vita_content=1')->assertOk();
        $response->assertSee('Identificación tributaria')->assertSee('Filtrar países')->assertDontSee('Buscar país emisor');
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $this->get($root.'/users?_vita_content=1')->assertOk()->assertSee('Identificación personal');
    }

    public function test_staff_document_edit_reuses_existing_document_without_duplicates(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $form = $this->getJson($root.'/users/'.$admin->id.'/identification')->assertOk()->json();
        $data = $admin->only(['name', 'email', 'phone', 'status']);
        $data += ['identification_enabled' => true, 'identification_country' => 'PE', 'identification_type' => 'national_id', 'identification_number' => '00001', 'identification_expected_primary' => $form['identification_expected_primary']];
        $this->putJson($root.'/users/'.$admin->id, $data)->assertOk();
        $form = $this->getJson($root.'/users/'.$admin->id.'/identification')->assertOk()->json();
        $data['identification_expected_primary'] = $form['identification_expected_primary'];
        $this->putJson($root.'/users/'.$admin->id, $data)->assertOk();
        $this->assertSame(2, DB::table('user_identifiers')->where('user_id', $admin->id)->count());
        $this->assertNull($admin->fresh()->rut);
        $data['identification_country'] = 'XX';
        $this->putJson($root.'/users/'.$admin->id, $data)->assertUnprocessable()->assertJsonValidationErrors('identification_number');
    }

    public function test_retired_document_cannot_be_recreated_through_forms(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $forms = app(IdentificationForms::class);
        $subject = $forms->subject($admin);
        $repo = app(IdentifierRepositoryFactory::class)->make();
        $primary = $repo->primary($subject, $admin);
        $repo->retire($subject, $admin, $primary['reference'], 'Synthetic retirement');
        $data = $admin->only(['name', 'email', 'phone', 'status']);
        $data += ['identification_enabled' => true, 'identification_country' => 'AR', 'identification_type' => 'passport', 'identification_number' => '001Ab-12', 'identification_expected_primary' => ''];
        $this->putJson($root.'/users/'.$admin->id, $data)->assertUnprocessable()->assertJsonValidationErrors('identification_number');
        $this->assertSame(1, DB::table('user_identifiers')->where('user_id', $admin->id)->count());
    }

    public function test_foreign_doctor_creation_uses_explicit_clinical_roles_without_a_fictitious_rut(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $doctor = Role::where('customer_id', $c->id)->where('name', 'Doctor')->firstOrFail();
        $catalogId = app(\App\Services\SpecialtyCatalog::class)->options()->first()->id;
        $payload = ['name' => 'Foreign doctor', 'email' => Str::uuid().'@test.test', 'password' => 'password-123', 'phone' => '123', 'status' => 1, 'role_ids' => [$doctor->id], 'branch_ids' => [$branch->id], 'specialties' => [$catalogId], 'specialty_branch_id' => $branch->id,
            'identification_enabled' => true, 'identification_country' => 'AR', 'identification_type' => 'national_id', 'identification_number' => '00123'];
        $this->postJson($root.'/users', $payload)->assertOk();
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertNull($user->rut);
        $this->assertTrue($user->hasClinicRole('Doctor', $c->id, $branch->id));
    }

    public function test_superadmin_profile_uses_same_encrypted_repository(): void
    {
        $root = 'http://'.config('app.domain');
        $this->get($root.'/profile?_vita_content=1')->assertOk()->assertSee('Identificación personal');
        $data = $this->super->only(['name', 'email', 'phone']);
        $data += ['identification_enabled' => true, 'identification_country' => 'AR', 'identification_type' => 'passport', 'identification_number' => '000GLOBAL', 'identification_expected_primary' => ''];
        $this->patch($root.'/profile', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('user_identifiers')->where('user_id', $this->super->id)->whereNull('customer_id')->count());
    }

    public function test_chilean_doctor_creation_and_explicit_document_edit_keep_legacy_compatibility(): void
    {
        [$c,$admin,$branch] = $this->createClinic();
        $root = $this->inClinic($c, $admin, $branch);
        $doctor = Role::where('customer_id', $c->id)->where('name', 'Doctor')->firstOrFail();
        $payload = ['name' => 'Chilean doctor', 'email' => Str::uuid().'@test.test', 'password' => 'password-123', 'phone' => '123', 'status' => 1,
            'role_ids' => [$doctor->id], 'branch_ids' => [$branch->id], 'specialties' => [app(\App\Services\SpecialtyCatalog::class)->options()->first()->id], 'specialty_branch_id' => $branch->id,
            'identification_enabled' => true, 'identification_country' => 'CL', 'identification_type' => 'national_id', 'identification_number' => '123456785'];
        $response = $this->postJson($root.'/users', $payload)->assertOk();
        $id = $response->json('data.id');
        $this->assertSame('12.345.678-5', User::findOrFail($id)->rut);
        $form = $this->getJson($root.'/users/'.$id.'/identification')->assertOk()->json();
        $payload['identification_expected_primary'] = $form['identification_expected_primary'];
        $payload['identification_number'] = '12345678-5';
        unset($payload['role_ids'],$payload['branch_ids'],$payload['specialties'],$payload['specialty_branch_id']);
        $this->putJson($root.'/users/'.$id, $payload)->assertOk();
        $this->assertSame('12.345.678-5', User::findOrFail($id)->rut);
        $this->assertSame(2, DB::table('user_identifiers')->where('user_id', $id)->count());
        $this->assertTrue(User::findOrFail($id)->branchRoles()->where('roles.name', 'Doctor')->exists());
    }

    public function test_old_customer_rut_constraint_cannot_be_restored_over_foreign_clients(): void
    {
        [$customer] = $this->createClinic('AR');
        $migration = require database_path('migrations/2026_11_06_000001_allow_absent_legacy_customer_rut.php');
        $this->expectException(\LogicException::class);
        $migration->down();
    }
}
