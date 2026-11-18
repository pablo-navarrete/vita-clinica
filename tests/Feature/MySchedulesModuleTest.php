<?php

namespace Tests\Feature;

use App\Models\{ClinicBranch, ProfessionalSchedule};
use Tests\TestCase;

class MySchedulesModuleTest extends TestCase
{
    use \Tests\Support\SigningFixtures;

    public function test_admin_role_in_headquarters_does_not_allow_writes_in_another_branch(): void
    {
        $admin = $this->user('Administrador');
        $quillota = ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Quillota', 'status' => 1]);
        $admin->branches()->attach($quillota->id, ['status' => 1]);
        $doctorRole = \App\Models\Role::where('customer_id', $this->clinic->id)->where('name', 'Doctor')->firstOrFail();
        $admin->branchRoles()->attach($doctorRole->id, ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $quillota->id]);
        $schedule = ProfessionalSchedule::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $quillota->id,
            'user_id' => $admin->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);
        $this->actingAs($admin)->withSession(['active_branch_id' => $quillota->id]);
        $this->get($this->root . '/my-schedules?_vita_content=1')->assertOk()->assertSee('Quillota')->assertDontSee('Administrar mis horarios');
        $this->postJson($this->root . '/professional/' . $admin->id . '/schedule', [
            'clinic_branch_id' => $quillota->id, 'day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '12:00',
        ])->assertForbidden();
        $this->deleteJson($this->root . '/professional/schedule/' . $schedule->id, ['clinic_branch_id' => $quillota->id])->assertForbidden();
        $this->assertNotNull($schedule->fresh());
        $this->withSession(['active_branch_id' => $this->branch->id])->get($this->root . '/my-schedules?_vita_content=1')
            ->assertOk()->assertSee('Administrar mis horarios');
    }

    public function test_administrator_can_open_manager_and_save_own_schedule(): void
    {
        $admin = $this->user('Administrador');
        $this->actingAs($admin)->get($this->root . '/my-schedules?_vita_content=1')->assertOk()
            ->assertSee('Administrar mis horarios')->assertSee('scheduleComponent');
        $this->postJson($this->root . '/professional/' . $admin->id . '/schedule', [
            'clinic_branch_id' => $this->branch->id, 'day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '12:00',
        ])->assertOk();
        $this->assertTrue(ProfessionalSchedule::where('customer_id', $this->clinic->id)
            ->where('clinic_branch_id', $this->branch->id)->where('user_id', $admin->id)->exists());
    }

    public function test_configured_role_without_schedule_permission_cannot_manage(): void
    {
        $this->doctor->branchRoles()->update(['permissions_configured' => true]);
        $this->get($this->root . '/my-schedules?_vita_content=1')->assertOk()->assertDontSee('Administrar mis horarios');
        $this->postJson($this->root . '/professional/' . $this->doctor->id . '/schedule', [
            'day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '12:00',
        ])->assertForbidden();
    }

    public function test_module_only_lists_current_users_schedules_in_active_branch(): void
    {
        $base = ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
            'user_id' => $this->doctor->id, 'day_of_week' => 1, 'start_time' => '09:15', 'end_time' => '12:45'];
        ProfessionalSchedule::create($base);
        ProfessionalSchedule::create(array_replace($base, ['user_id' => $this->user()->id, 'start_time' => '14:20', 'end_time' => '15:50']));
        $branch = ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Other branch', 'status' => 1]);
        ProfessionalSchedule::create(array_replace($base, ['clinic_branch_id' => $branch->id, 'start_time' => '16:20', 'end_time' => '18:50']));
        $this->get($this->root . '/my-schedules?_vita_content=1')->assertOk()
            ->assertSee('Mis horarios')->assertSee('Lunes')->assertSee('09:15')->assertSee('12:45')
            ->assertDontSee('14:20')->assertDontSee('16:20');
    }
}
