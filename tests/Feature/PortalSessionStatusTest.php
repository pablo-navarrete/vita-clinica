<?php

namespace Tests\Feature;

use App\Models\PortalAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalSessionStatusTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); DB::beginTransaction(); }
    protected function tearDown(): void { DB::rollBack(); parent::tearDown(); }
    private function url(): string { return 'http://'.config('app.domain').'/mi-salud/estado-sesion'; }
    private function account(): PortalAccount
    {
        $account=new PortalAccount(['name'=>'Private name','email'=>Str::uuid().'@example.test','password'=>bcrypt('PortalPassword123!')]);
        $account->forceFill(['email_verified_at'=>now(),'active'=>true,'session_version'=>1])->save();
        return $account;
    }

    public function test_guest_status_is_private_and_does_not_require_login(): void
    {
        $response=$this->getJson($this->url())->assertOk()->assertExactJson(['authenticated'=>false]);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $cookie=collect($response->headers->getCookies())->first(fn($cookie)=>$cookie->getName()==='vita_mi_salud_session');
        $this->assertNotNull($cookie);
        $this->assertSame('/mi-salud',$cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }

    public function test_patient_login_changes_status_and_logout_restores_guest_status(): void
    {
        $account=$this->account();$base='http://'.config('app.domain').'/mi-salud';
        $this->post($base.'/ingresar',['email'=>$account->email,'password'=>'PortalPassword123!'])->assertRedirect(route('mi-salud.panel'));
        $this->getJson($this->url())->assertOk()->assertExactJson(['authenticated'=>true])
            ->assertDontSee($account->email)->assertDontSee('Private name');
        $this->post($base.'/salir')->assertRedirect();
        $this->getJson($this->url())->assertExactJson(['authenticated'=>false]);
    }

    public function test_revoked_or_disabled_sessions_do_not_show_the_patient_panel(): void
    {
        $account=$this->account();
        $this->actingAs($account,'mi_salud')->withSession(['portal_session_version'=>1]);
        $this->getJson($this->url())->assertExactJson(['authenticated'=>true]);
        $account->forceFill(['session_version'=>2])->save();
        $this->getJson($this->url())->assertExactJson(['authenticated'=>false]);
        $account->forceFill(['session_version'=>1,'active'=>false])->save();
        $this->getJson($this->url())->assertExactJson(['authenticated'=>false]);
    }
}
