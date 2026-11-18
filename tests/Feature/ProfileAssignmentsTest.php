<?php

namespace Tests\Feature;

use App\Models\Role;
use Tests\TestCase;

class ProfileAssignmentsTest extends TestCase
{
    use \Tests\Support\SigningFixtures;

    public function test_non_admin_cannot_change_personal_data_but_can_change_password(): void
    {
        $before = $this->doctor->fresh()->only(['name', 'email', 'phone', 'rut']);
        $this->patchJson($this->root . '/profile', ['name' => 'Unauthorized change'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame($before, $this->doctor->fresh()->only(array_keys($before)));
        $this->patch($this->root . '/profile', ['current_password' => 'correct-password',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $this->doctor->fresh()->password));
    }

    public function test_active_branch_admin_can_update_personal_details(): void
    {
        $admin = $this->user('Administrador');
        $data = $admin->only(['name', 'email', 'phone', 'rut']);
        $data['name'] = 'Updated Administrator';
        $this->actingAs($admin)->patch($this->root . '/profile', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($data['name'], $admin->fresh()->name);
    }

    public function test_headquarters_admin_is_regular_doctor_in_quillota_profile(): void
    {
        $admin = $this->user('Administrador');
        $branch = \App\Models\ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Quillota', 'status' => 1]);
        $admin->branches()->attach($branch->id, ['status' => 1]);
        $doctorRole = Role::where('customer_id', $this->clinic->id)->where('name', 'Doctor')->firstOrFail();
        $admin->branchRoles()->attach($doctorRole->id, ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $branch->id]);
        $response = $this->actingAs($admin)->withSession(['active_branch_id' => $branch->id])
            ->get($this->root . '/profile?_vita_content=1')->assertOk()
            ->assertSee('Para agregar o modificar especialidades, contacta al administrador.')
            ->assertDontSee('data-open-assignment', false);
        $this->assertFalse($response->viewData('isClinicAdministrator'));
        $this->assertSame([$branch->id], $response->viewData('roleGroups')->pluck('branch.id')->all());
        $this->assertSame(['Doctor'], $response->viewData('roles')->pluck('name')->all());
        $this->withSession(['active_branch_id' => $this->branch->id])->get($this->root . '/profile?_vita_content=1')
            ->assertOk()->assertDontSee('Para agregar o modificar especialidades, contacta al administrador.');
    }

    public function test_admin_specialties_are_grouped_under_their_own_branches(): void
    {
        $admin = $this->user('Administrador');
        $otherBranch = \App\Models\ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Quillota', 'status' => 1]);
        $admin->branches()->attach($otherBranch->id, ['status' => 1]);
        foreach ([$this->branch->id => 'Cardiología principal', $otherBranch->id => 'Pediatría Quillota'] as $branchId => $name) {
            $specialty = \App\Models\Specialty::create(['customer_id' => $this->clinic->id, 'name' => $name, 'status' => 1]);
            $admin->specialties()->attach($specialty->id, ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $branchId]);
        }
        $response = $this->actingAs($admin)->get($this->root . '/profile?_vita_content=1')->assertOk()
            ->assertSee('data-specialties-by-branch', false)->assertSee('Cardiología principal')->assertSee('Pediatría Quillota');
        $groups = $response->viewData('specialtyGroups')->keyBy(fn ($group) => $group['branch']->id);
        $this->assertSame(['Cardiología principal'], $groups[$this->branch->id]['specialties']->pluck('name')->all());
        $this->assertSame(['Pediatría Quillota'], $groups[$otherBranch->id]['specialties']->pluck('name')->all());
    }

    public function test_administrator_can_manage_own_roles_from_profile_using_existing_endpoints(): void
    {
        $admin = $this->user('Administrador');
        $this->actingAs($admin)->get($this->root . '/profile?_vita_content=1')->assertOk()
            ->assertSee('data-profile-assignments', false)->assertSee('data-open-assignment="roles"', false)
            ->assertSee('data-open-assignment="specialties"', false)
            ->assertSee('id="profile-roles-modal" hidden', false)->assertSee('id="profile-specialties-modal" hidden', false);
        $roles = Role::where('customer_id', $this->clinic->id)->whereIn('name', ['Administrador', 'Doctor'])->pluck('id')->all();
        $this->patchJson($this->root . '/users/' . $admin->id . '/roles', [
            'branch_id' => $this->branch->id, 'role_ids' => $roles,
        ])->assertOk();
        $this->getJson($this->root . '/users/' . $admin->id . '/specialties?branch_id=' . $this->branch->id)
            ->assertOk()->assertJsonPath('can_assign', true);
    }

    public function test_non_administrator_profile_does_not_offer_assignment_editor(): void
    {
        $this->get($this->root . '/profile?_vita_content=1')->assertOk()->assertDontSee('data-profile-assignments', false);
    }
}
