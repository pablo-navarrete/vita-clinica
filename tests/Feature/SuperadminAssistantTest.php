<?php

namespace Tests\Feature;

use App\Models\{Customer,PortalAccount,Role,User};
use Carbon\{Carbon,CarbonImmutable};
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperadminAssistantTest extends TestCase
{
    private User $administrator;
    private string $endpoint;

    protected function setUp(): void
    {
        parent::setUp();DB::beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00','America/Santiago'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-17 10:00:00','America/Santiago'));
        $this->app->instance('customer',null);
        $this->administrator=$this->user();
        $role=Role::firstOrCreate(['customer_id'=>null,'name'=>'Super Administrador']);
        $this->administrator->roles()->attach($role->id);
        $this->endpoint='http://'.config('app.domain').'/superadmin/assistant/ask';
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();CarbonImmutable::setTestNow();DB::rollBack();parent::tearDown();
    }

    private function user(?Customer $clinic=null): User
    {
        $key=Str::lower(Str::random(16));
        return User::create(['name'=>'Assistant administrator','customer_id'=>$clinic?->id,'email'=>$key.'@example.test',
            'rut'=>$key,'password'=>'test-password','phone'=>'123','status'=>1]);
    }

    private function clinic(string $created, int $status=1): Customer
    {
        $key=Str::lower(Str::random(16));
        $clinic=Customer::create(['name'=>'PRIVATE CUSTOMER '.$key,'legal_name'=>'Private legal name','holder'=>'Private owner',
            'domain'=>'assistant-'.$key,'rut'=>$key,'email'=>$key.'@example.test','phone'=>'123','status'=>$status]);
        DB::table('customers')->where('id',$clinic->id)->update(['created_at'=>$created]);
        return $clinic;
    }

    public function test_superadmin_gets_real_current_month_count_with_calendar_boundaries(): void
    {
        $baseline=Customer::where('created_at','>=','2026-11-01')->where('created_at','<','2026-12-01')->count();
        $this->clinic('2026-10-31 23:59:59');$this->clinic('2026-11-01 00:00:00',0);
        $this->clinic('2026-11-17 09:00:00');$this->clinic('2026-12-01 00:00:00');
        $this->actingAs($this->administrator,'web')->postJson($this->endpoint,['message'=>'¿Cuántos clientes nuevos tengo este mes?',
            'context'=>'clinic','customer_id'=>999999,'role'=>'patient'])
            ->assertOk()->assertJsonPath('context','superadmin')->assertJsonPath('summary.new_customers',$baseline+2)
            ->assertJsonPath('summary.month',11)->assertJsonPath('summary.year',2026)
            ->assertJsonPath('summary.timezone','America/Santiago')->assertSee('noviembre')->assertDontSee('PRIVATE CUSTOMER');
        $html=view('components.vita-assistant')->render();
        $this->assertStringContainsString('data-assistant-context="superadmin"',$html);
        $this->assertStringContainsString(route('superadmin.assistant.ask'),$html);
    }

    public function test_other_periods_are_not_silently_answered_with_current_month_data(): void
    {
        $this->actingAs($this->administrator,'web')->postJson($this->endpoint,['message'=>'Cuántos clientes nuevos hubo el mes pasado'])
            ->assertOk()->assertJsonMissingPath('summary');
        $this->postJson($this->endpoint,['message'=>'Hola, dónde estoy'])->assertOk()->assertJsonPath('context','superadmin')
            ->assertSee('superadmin VITA');
        $this->postJson($this->endpoint,['message'=>str_repeat('x',1201)])->assertUnprocessable();
    }

    public function test_guest_portal_clinic_and_inactive_users_cannot_get_global_statistics(): void
    {
        $this->postJson($this->endpoint,['message'=>'clientes nuevos este mes'])->assertUnauthorized();
        $portal=new PortalAccount;$portal->forceFill(['id'=>99999,'name'=>'Portal only']);
        $this->actingAs($portal,'mi_salud')->postJson($this->endpoint,['message'=>'clientes nuevos este mes'])->assertUnauthorized();
        $clinic=$this->clinic('2026-11-01');$tenant=$this->user($clinic);
        // Even a forged global-role assignment cannot turn a clinic account into a global assistant user.
        $tenant->roles()->attach($this->administrator->roles()->firstOrFail()->id);
        $this->actingAs($tenant,'web')->postJson($this->endpoint,['message'=>'clientes nuevos este mes','context'=>'superadmin'])->assertForbidden();
        $this->administrator->update(['status'=>0]);
        $this->actingAs($this->administrator,'web')->postJson($this->endpoint,['message'=>'clientes nuevos este mes'])->assertForbidden();
    }
}
