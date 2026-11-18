<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAdministratorOnboardingTest extends TestCase
{
    private User $actor;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->actor = User::factory()->create(['customer_id' => null, 'status' => 1]);
        $role = Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
        $this->actor->roles()->attach($role->id);
        $this->actingAs($this->actor);
        $this->url = 'http://'.config('app.domain').'/customers';
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function data(): array
    {
        $key = Str::lower(Str::random(12));

        return ['operating_declaration_accepted' => true, 'name' => 'Clinic'.$key, 'legal_name' => 'Test clinic', 'holder' => 'Holder', 'rut' => '12.345.678-5',
            'email' => $key.'@clinic.test', 'phone' => '+56987654321', 'status' => 1,
            'admin_name' => 'Initial admin', 'admin_rut' => '12.345.678-5', 'admin_phone' => '+56987654322',
            'admin_email' => $key.'@admin.test', 'admin_password' => 'onboarding-password',
            'admin_password_confirmation' => 'onboarding-password'];
    }

    public function test_customer_admin_and_active_headquarters_are_created_together_and_can_log_in(): void
    {
        $data = $this->data();
        $response = $this->postJson($this->url, $data)->assertOk();
        $this->assertStringNotContainsString($data['admin_password'], $response->getContent());
        $customer = Customer::findOrFail($response->json('data.id'));
        $admin = User::where('customer_id', $customer->id)->sole();
        $branch = $customer->branches()->sole();
        $this->assertTrue(Hash::check($data['admin_password'], $admin->password));
        $this->assertEquals(1, $admin->status);
        $this->assertTrue($admin->branches()->whereKey($branch->id)->wherePivot('status', 1)->exists());
        $this->assertSame(['Administrador'], $admin->clinicRolesForBranch($customer->id, $branch->id)->pluck('name')->all());
        $this->assertFalse($admin->branchRoles()->where('roles.name', 'Super Administrador')->exists());
        $this->assertFalse($admin->branchRoles()->where('roles.name', 'Doctor')->exists());
        $this->assertTrue($admin->clinicRolesForBranch($customer->id, $branch->id)
            ->whereHas('permissions', fn ($q) => $q->where('slug', 'professionals.manage'))->exists());
        Auth::logout();
        $root = 'http://'.$customer->domain.'.'.config('app.domain');
        $this->post($root.'/login', ['email' => $data['admin_email'], 'password' => $data['admin_password']])
            ->assertSessionHasNoErrors()->assertRedirect($root.'/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertEquals($branch->id, session('active_branch_id'));
    }

    public function test_admin_validation_prevents_incomplete_customers(): void
    {
        $data = $this->data();
        unset($data['admin_email']);
        $this->postJson($this->url, $data)->assertUnprocessable()->assertJsonValidationErrors('admin_email');
        $this->assertDatabaseMissing('customers', ['name' => $data['name']]);
        $data = $this->data();
        $data['admin_password_confirmation'] = 'wrong';
        $this->postJson($this->url, $data)->assertUnprocessable()->assertJsonValidationErrors('admin_password');
        $this->assertDatabaseMissing('customers', ['name' => $data['name']]);
    }

    public function test_assignment_failure_rolls_back_customer_branch_and_user(): void
    {
        $data = $this->data();
        $fail = true;
        $this->withoutExceptionHandling();
        $before = DB::table('clinic_branches')->count();
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_contains($query->sql, 'insert into `role_user`')) {
                throw new \RuntimeException('Simulated role assignment failure');
            }
        });
        try {
            $this->postJson($this->url, $data);
            $this->fail('Atomic rollback required');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated role assignment failure', $e->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertDatabaseMissing('customers', ['name' => $data['name']]);
        $this->assertDatabaseMissing('users', ['email' => $data['admin_email']]);
        $this->assertSame($before, DB::table('clinic_branches')->count());
    }

    public function test_validation_redirect_does_not_flash_administrator_passwords(): void
    {
        $data = $this->data() + ['billing_plan_price_id' => 999999999, 'subscription_starts_on' => 'invalid'];
        $this->from($this->url)->post($this->url, $data)->assertRedirect($this->url)->assertSessionHasErrors();
        $old = session('_old_input', []);
        $this->assertArrayNotHasKey('admin_password', $old);
        $this->assertArrayNotHasKey('admin_password_confirmation', $old);
        $this->assertDatabaseMissing('customers', ['name' => $data['name']]);
    }

    public function test_shared_email_in_different_clinics_authenticates_the_current_tenant_only(): void
    {
        $data = $this->data();
        $this->postJson($this->url, $data)->assertOk();
        $other = $this->data();
        $other['admin_email'] = $data['admin_email'];
        $other['admin_password'] = $other['admin_password_confirmation'] = 'different-password';
        $response = $this->postJson($this->url, $other)->assertOk();
        $customer = Customer::findOrFail($response->json('data.id'));
        $user = User::where('customer_id', $customer->id)->sole();
        Auth::logout();
        $root = 'http://'.$customer->domain.'.'.config('app.domain');
        $this->post($root.'/login', ['email' => $other['admin_email'], 'password' => $data['admin_password']])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post($root.'/login', ['email' => $other['admin_email'], 'password' => $other['admin_password']])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_spaces_are_preserved_and_unprivileged_users_cannot_create_clients(): void
    {
        $data = $this->data();
        $data['admin_password'] = $data['admin_password_confirmation'] = ' onboarding-password ';
        $response = $this->postJson($this->url, $data)->assertOk();
        $user = User::where('customer_id', $response->json('data.id'))->sole();
        $this->assertTrue(Hash::check($data['admin_password'], $user->password));
        $this->assertFalse(Hash::check(trim($data['admin_password']), $user->password));
        $denied = $this->data();
        $this->actingAs($user)->postJson($this->url, $denied)->assertForbidden();
        $this->assertDatabaseMissing('customers', ['name' => $denied['name']]);
    }

    public function test_edit_loads_admin_updates_details_and_keeps_password_when_blank(): void
    {
        $data = $this->data();
        $data['admin_name'] = "Admin O'Test & Asociados";
        $response = $this->postJson($this->url, $data)->assertOk();
        $customer = Customer::findOrFail($response->json('data.id'));
        $admin = $customer->administratorUsers()->sole();
        $hash = $admin->password;
        $listing = $this->getJson('http://'.config('app.domain').'/customer-data?draw=1&start=0&length=100')->assertOk();
        $row = collect($listing->json('data'))->firstWhere('id', $customer->id);
        $this->assertSame($admin->id, $row['administrators'][0]['id']);
        $this->assertSame($data['admin_email'], $row['administrators'][0]['email']);
        $this->assertSame($data['admin_name'], $row['administrators'][0]['name']);
        $this->assertArrayNotHasKey('password', $row['administrators'][0]);
        $this->assertStringNotContainsString($hash, $listing->getContent());
        $data = array_merge($data, ['admin_user_id' => $admin->id, 'admin_name' => 'Updated admin',
            'admin_email' => Str::random(12).'@updated.test', 'admin_password' => '', 'admin_password_confirmation' => '']);
        $this->putJson($this->url.'/'.$customer->id, $data)->assertOk();
        $this->assertSame('Updated admin', $admin->fresh()->name);
        $this->assertSame($data['admin_email'], $admin->fresh()->email);
        $this->assertSame($hash, $admin->fresh()->password);
        $branch = $customer->branches()->sole();
        $this->assertSame(['Administrador'], $admin->clinicRolesForBranch($customer->id, $branch->id)->pluck('name')->all());
    }

    public function test_edit_can_change_password_but_rejects_foreign_admins_and_duplicate_emails(): void
    {
        $data = $this->data();
        $customer = Customer::findOrFail($this->postJson($this->url, $data)->assertOk()->json('data.id'));
        $admin = $customer->administratorUsers()->sole();
        $url = $this->url.'/'.$customer->id;
        $data['admin_user_id'] = $admin->id;
        $data['admin_password'] = $data['admin_password_confirmation'] = 'new-admin-password';
        $this->putJson($url, $data)->assertOk();
        $this->assertTrue(Hash::check($data['admin_password'], $admin->fresh()->password));
        $foreignData = $this->data();
        $foreign = Customer::findOrFail($this->postJson($this->url, $foreignData)->assertOk()->json('data.id'));
        $foreignAdmin = $foreign->administratorUsers()->sole();
        $foreignHash = $foreignAdmin->password;
        $this->putJson($url, array_merge($data, ['admin_user_id' => $foreignAdmin->id]))->assertNotFound();
        $this->assertSame($foreignHash, $foreignAdmin->fresh()->password);
        $this->putJson($url, array_merge($data, ['admin_user_id' => $this->actor->id]))->assertNotFound();
        $other = User::factory()->create(['customer_id' => $customer->id, 'status' => 1]);
        $this->putJson($url, array_merge($data, ['admin_user_id' => $other->id]))->assertNotFound();
        $this->putJson($url, array_merge($data, ['admin_email' => $other->email]))->assertUnprocessable()->assertJsonValidationErrors('admin_email');
        $this->assertSame($data['admin_email'], $admin->fresh()->email);
    }

    public function test_customer_update_failure_rolls_back_admin_changes_and_password(): void
    {
        $data = $this->data();
        $customer = Customer::findOrFail($this->postJson($this->url, $data)->assertOk()->json('data.id'));
        $admin = $customer->administratorUsers()->sole();
        $before = $admin->getAttributes();
        $fail = true;
        $this->withoutExceptionHandling();
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_starts_with($query->sql, 'update `customers`')) {
                throw new \RuntimeException('Simulated customer update failure');
            }
        });
        $data = array_merge($data, ['admin_user_id' => $admin->id, 'name' => 'Changed'.$data['name'],
            'admin_name' => 'Changed admin', 'admin_password' => 'new-admin-password', 'admin_password_confirmation' => 'new-admin-password']);
        try {
            $this->putJson($this->url.'/'.$customer->id, $data);
            $this->fail('Atomic rollback required');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated customer update failure', $e->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertNotSame($data['name'], $customer->fresh()->name);
    }

    public function test_multiple_headquarters_admins_are_selectable_without_changing_other_users(): void
    {
        $data = $this->data();
        $customer = Customer::findOrFail($this->postJson($this->url, $data)->assertOk()->json('data.id'));
        $initial = $customer->administratorUsers()->sole();
        $before = $initial->getAttributes();
        $branch = $customer->branches()->sole();
        $role = Role::where('customer_id', $customer->id)->where('name', 'Administrador')->sole();
        $second = User::factory()->create(['customer_id' => $customer->id, 'status' => 1]);
        $second->branches()->attach($branch->id, ['status' => 1]);
        $second->branchRoles()->attach($role->id, ['customer_id' => $customer->id, 'clinic_branch_id' => $branch->id]);
        $this->assertCount(2, $customer->administratorUsers()->get());
        $data = array_merge($data, ['admin_user_id' => $second->id, 'admin_name' => 'Second admin updated',
            'admin_email' => $second->email, 'admin_rut' => $second->rut, 'admin_password' => '', 'admin_password_confirmation' => '']);
        $this->putJson($this->url.'/'.$customer->id, $data)->assertOk();
        $this->assertSame('Second admin updated', $second->fresh()->name);
        $this->assertSame($before, $initial->fresh()->getAttributes());
    }

    public function test_edit_creates_missing_headquarters_admin_and_rejects_a_second_creation(): void
    {
        $data = $this->data();
        $customer = Customer::create(\Illuminate\Support\Arr::only($data, ['name', 'legal_name', 'holder', 'rut', 'email', 'phone', 'status']) + ['domain' => strtolower($data['name'])]);
        $branch = $customer->branches()->create(['name' => 'Principal', 'status' => 1]);
        $payload = $data + ['create_admin' => true, 'admin_branch_id' => $branch->id];
        $this->putJson($this->url.'/'.$customer->id, $payload)->assertOk();
        $admin = User::where('customer_id', $customer->id)->sole();
        $this->assertTrue(Hash::check($data['admin_password'], $admin->password));
        $this->assertTrue($admin->branches()->whereKey($branch->id)->wherePivot('status', 1)->exists());
        $this->assertSame(['Administrador'], $admin->clinicRolesForBranch($customer->id, $branch->id)->pluck('name')->all());
        $payload['admin_email'] = Str::random(12).'@duplicate.test';
        $this->putJson($this->url.'/'.$customer->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('admin_user_id');
        $this->assertSame(1, User::where('customer_id', $customer->id)->count());
    }

    public function test_general_admin_has_all_current_and_future_branches_without_crossing_tenants(): void
    {
        $data = $this->data();
        $customer = Customer::findOrFail($this->postJson($this->url, $data)->assertOk()->json('data.id'));
        $initial = $customer->administratorUsers()->sole();
        $this->assertTrue($initial->is_customer_administrator);
        $branch = app(\App\Services\Billing\BranchCapacityService::class)->create($customer, ['name' => 'Quillota']);
        $data['admin_email'] = Str::random(12).'@branch.test';
        $data['admin_name'] = 'Branch administrator';
        $data += ['create_admin' => true, 'admin_branch_id' => $branch->id];
        $this->putJson($this->url.'/'.$customer->id, $data)->assertUnprocessable()->assertJsonValidationErrors('admin_user_id');
        $admin = $initial;
        $this->assertSame(['Administrador'], $admin->clinicRolesForBranch($customer->id, $branch->id)->pluck('name')->all());
        $this->assertCount(2, $admin->branches()->get());
        session(['active_branch_customer_id' => $customer->id, 'active_branch_id' => $branch->id]);
        $this->assertTrue(\App\Support\ClinicBranchContext::canAdministerAllBranches($admin, $customer->id));
        $this->assertFalse($admin->clinicRolesForBranch($customer->id + 100000, $branch->id)->exists());
        $listing = $this->getJson('http://'.config('app.domain').'/customer-data?draw=1&length=100')->assertOk();
        $row = collect($listing->json('data'))->firstWhere('id', $customer->id);
        $this->assertCount(2, $row['admin_branches']);
        $this->assertCount(2, collect($row['administrators'])->firstWhere('id', $admin->id)['branch_ids']);
        $data['create_admin'] = false;
        $data['admin_user_id'] = $admin->id;
        $data['admin_name'] = 'Edited branch admin';
        $data['admin_password'] = $data['admin_password_confirmation'] = '';
        $this->putJson($this->url.'/'.$customer->id, $data)->assertOk();
        $this->assertSame('Edited branch admin', $admin->fresh()->name);
    }

    public function test_missing_admin_creation_requires_active_branch_and_password_confirmation(): void
    {
        $data = $this->data();
        $customer = Customer::create(\Illuminate\Support\Arr::only($data, ['name', 'legal_name', 'holder', 'rut', 'email', 'phone', 'status']) + ['domain' => strtolower($data['name'])]);
        $branch = $customer->branches()->create(['name' => 'Secondary', 'status' => 1]);
        $data['admin_email'] = Str::random(12).'@new.test';
        $data += ['create_admin' => true, 'admin_branch_id' => $branch->id];
        $this->putJson($this->url.'/'.$customer->id, array_merge($data, ['admin_password_confirmation' => 'wrong']))->assertUnprocessable()->assertJsonValidationErrors('admin_password');
        $branch->update(['status' => 0]);
        $this->putJson($this->url.'/'.$customer->id, $data)->assertUnprocessable()->assertJsonValidationErrors('admin_user_id');
        $this->assertSame(0, User::where('customer_id', $customer->id)->count());
    }

    public function test_failed_missing_admin_assignment_leaves_no_partial_account(): void
    {
        $data = $this->data();
        $customer = Customer::create(\Illuminate\Support\Arr::only($data, ['name', 'legal_name', 'holder', 'rut', 'email', 'phone', 'status']) + ['domain' => strtolower($data['name'])]);
        $branch = $customer->branches()->create(['name' => 'Principal', 'status' => 1]);
        $before = DB::table('clinic_branch_user')->count();
        $fail = true;
        $this->withoutExceptionHandling();
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_contains($query->sql, 'insert into `clinic_branch_user_role`')) {
                throw new \RuntimeException('Simulated missing admin role failure');
            }
        });
        try {
            $this->putJson($this->url.'/'.$customer->id, $data + ['create_admin' => true, 'admin_branch_id' => $branch->id]);
            $this->fail('Rollback required');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated missing admin role failure', $e->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertSame(0, User::where('customer_id', $customer->id)->count());
        $this->assertSame($before, DB::table('clinic_branch_user')->count());
        $this->assertEquals(1, $customer->fresh()->status);
    }
}
