<?php

namespace Tests\Feature;

use App\Models\{PortalAccount,User};
use App\Services\MiSalud\PortalAccountService;
use App\Mail\PortalSecurityLink;
use App\Services\Identification\Storage\IdentifierCrypto;
use Illuminate\Support\Facades\{Auth, DB, Hash, Mail};
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class MiSaludAccountTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',DB::connection()->getDatabaseName())) $this->markTestSkipped('Isolated MySQL only.');
        config(['identification'=>IdentifierStorageKeys::config()+['application_key'=>config('app.key')],'cache.default'=>'array']);
        Mail::fake(); $this->root='http://'.config('app.domain').'/mi-salud';
    }
    private function data(string $country='CL'): array
    {
        return ['name'=>'Mi Salud test','email'=>Str::uuid().'@example.test','password'=>'SecurePassword123!','password_confirmation'=>'SecurePassword123!',
            'identification_country'=>$country,'identification_type'=>$country==='CL'?'national_id':'passport','identification_number'=>$country==='CL'?'12.345.678-5':'000AbCd77'];
    }
    private function register(string $country='CL'): PortalAccount
    {
        $data=$this->data($country); $this->post($this->root.'/registro',$data)->assertRedirect($this->root.'/ingresar')->assertSessionHasNoErrors();
        return PortalAccount::where('email',$data['email'])->firstOrFail();
    }
    private function verify(PortalAccount $account): void
    {
        $mail=Mail::sent(PortalSecurityLink::class)->last();
        $this->post($this->root.'/enlace/verify',['security_token'=>parse_url($mail->securityUrl,PHP_URL_FRAGMENT)])->assertRedirect($this->root.'/ingresar');
        $this->assertNotNull($account->fresh()->email_verified_at);
    }
    private function login(PortalAccount $account): void
    {
        $this->post($this->root.'/ingresar',['email'=>$account->email,'password'=>'SecurePassword123!'])->assertRedirect($this->root.'/panel');
    }
    public function test_portal_viti_requires_portal_authentication_and_never_reads_clinical_records(): void
    {
        $this->postJson($this->root.'/asistente',['message'=>'hola'])->assertRedirect($this->root.'/ingresar');
        $account=$this->register(); $this->verify($account); $this->login($account);
        $this->get($this->root.'/panel')->assertOk()->assertSee('data-vita-assistant', false)->assertSee('/mi-salud/asistente', false);
        $clinicalQueries=[];
        DB::listen(function($query) use (&$clinicalQueries) {
            if (preg_match('/\b(patients|medical_records|appointments|encounters|customer_identifiers)\b/i',$query->sql)) $clinicalQueries[]=$query->sql;
        });
        $this->postJson($this->root.'/asistente',['message'=>'Quiero ver mi ficha y agendar una cita','customer_id'=>99,'patient_id'=>58])
            ->assertOk()->assertJsonPath('actions.0.label','Mi perfil')->assertSee('todav',false);
        $this->assertSame([],$clinicalQueries);
        $this->postJson($this->root.'/asistente',['message'=>str_repeat('x',1201)])->assertUnprocessable();
    }
    public function test_registration_is_free_encrypted_and_does_not_write_clinical_or_internal_records(): void
    {
        $before=[]; foreach(['patients','users','medical_records','appointments','encounter_signatures'] as $t) $before[$t]=DB::table($t)->count();
        $account=$this->register();
        foreach($before as $table=>$count) $this->assertSame($count,DB::table($table)->count());
        $this->assertNull($account->email_verified_at); $this->assertTrue(Hash::check('SecurePassword123!',$account->password));
        $row=DB::table('portal_account_identifiers')->where('portal_account_id',$account->id)->first();
        $this->assertStringNotContainsString('12345678',$row->encrypted_payload);
        $this->assertStringNotContainsString('12.345.678-5',json_encode(DB::table('portal_audit_events')->get()));
        $this->assertSame('12.345.678-5',app(PortalAccountService::class)->identifier($account)['display']);
        $this->assertFalse(app(PortalAccountService::class)->identifier($account)['identity_verified']);
        $this->assertFalse(Auth::guard('web')->check());
    }
    public function test_profile_sharing_is_optional_password_protected_and_revocable(): void
    {
        $account=$this->register();$this->verify($account);$this->login($account);
        $this->assertFalse(DB::table('portal_profile_sharing')->where('portal_account_id',$account->id)->exists());
        $data=['name'=>$account->name,'identification_country'=>'CL','identification_type'=>'national_id','current_password'=>'incorrect','sharing_settings_present'=>1,'share_basic_profile'=>1,'phone'=>'912345678','address'=>'Private portal address','gender'=>'Otro','birth_date'=>'1990-01-01'];
        $this->put($this->root.'/perfil',$data)->assertSessionHasErrors('current_password');
        $this->assertFalse(DB::table('portal_profile_sharing')->where('portal_account_id',$account->id)->exists());
        $data['current_password']='SecurePassword123!';$this->put($this->root.'/perfil',$data)->assertRedirect($this->root.'/perfil')->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('portal_profile_sharing')->where('portal_account_id',$account->id)->value('enabled'));
        $this->get($this->root.'/perfil')->assertOk()->assertSee('Permitir que personal autorizado');
        $data['share_basic_profile']=0;$this->put($this->root.'/perfil',$data)->assertSessionHasNoErrors();
        $this->assertSame(0,DB::table('portal_profile_sharing')->where('portal_account_id',$account->id)->value('enabled'));
    }
    public function test_foreign_document_keeps_leading_zeroes_and_same_declared_document_does_not_block_another_account(): void
    {
        $a=$this->register('AR'); $b=$this->register('AR');
        $this->assertNotSame($a->id,$b->id);
        $this->assertSame('000AbCd77',app(PortalAccountService::class)->identifier($a)['display']);
        $rows=DB::table('portal_account_identifiers')->whereIn('portal_account_id',[$a->id,$b->id])->get();
        $this->assertNotSame($rows[0]->lookup_hash,$rows[1]->lookup_hash);
    }
    public function test_invalid_country_rut_or_document_type_creates_nothing_and_document_is_not_flashed(): void
    {
        $before=PortalAccount::count();
        foreach([['identification_number'=>'12345678-0'],['identification_country'=>'XX'],['identification_type'=>'arbitrary']] as $bad) {
            $data=array_replace($this->data(),$bad);
            $this->post($this->root.'/registro',$data)->assertSessionHasErrors('identification_number');
            $this->assertNull(session()->getOldInput('identification_number'));
        }
        $this->assertSame($before,PortalAccount::count());
    }
    public function test_duplicate_email_response_is_neutral_and_does_not_replace_identity_or_password(): void
    {
        $account=$this->register(); $before=$account->getAttributes(); $count=PortalAccount::count();
        $data=$this->data('AR'); $data['email']=strtoupper($account->email);
        $this->post($this->root.'/registro',$data)->assertRedirect($this->root.'/ingresar')->assertSessionHas('status');
        $this->assertSame($count,PortalAccount::count()); $this->assertSame($before,$account->fresh()->getAttributes());
    }

    public function test_personal_account_can_share_email_with_internal_user_without_sharing_credentials(): void
    {
        $data=$this->data();
        $user=User::create(['name'=>'Professional account','email'=>$data['email'],'password'=>Hash::make('DifferentPassword123!'),'status'=>1]);
        $this->post($this->root.'/registro',$data)->assertRedirect($this->root.'/ingresar');
        $account=PortalAccount::where('email',$data['email'])->firstOrFail();
        $this->assertTrue(Hash::check('SecurePassword123!',$account->password));
        $this->assertFalse(Hash::check('SecurePassword123!',$user->fresh()->password));
        $this->assertFalse(Auth::guard('web')->check());
    }
    public function test_verification_is_hash_only_expiring_one_use_and_get_has_no_side_effects(): void
    {
        $account=$this->register(); $mail=Mail::sent(PortalSecurityLink::class)->last(); $token=parse_url($mail->securityUrl,PHP_URL_FRAGMENT);
        $row=DB::table('portal_auth_tokens')->where('portal_account_id',$account->id)->first();
        $this->assertSame(hash('sha256',$token),$row->token_hash); $this->assertStringNotContainsString($token,json_encode($row));
        $this->get(explode('#',$mail->securityUrl)[0])->assertOk()->assertDontSee($token)->assertHeader('Referrer-Policy','no-referrer')->assertHeader('X-Robots-Tag','noindex, nofollow');
        $this->assertNull($account->fresh()->email_verified_at);
        $this->verify($account);
        $this->post($this->root.'/enlace/verify',['security_token'=>$token])->assertSessionHasErrors('security_token');
        $this->assertNull(session()->getOldInput('security_token'));
    }
    public function test_expired_wrong_purpose_and_invalid_tokens_never_verify(): void
    {
        $account=$this->register();$token=parse_url(Mail::sent(PortalSecurityLink::class)->last()->securityUrl,PHP_URL_FRAGMENT);
        $service=app(PortalAccountService::class);
        $this->assertFalse($service->consume('reset',$token,'AnotherPassword123!'));
        $this->assertFalse($service->consume('verify',str_repeat('0',64)));
        $this->travel(31)->minutes();$this->assertFalse($service->consume('verify',$token));$this->assertNull($account->fresh()->email_verified_at);
    }
    public function test_login_requires_verification_for_panel_and_web_session_cannot_enter_portal(): void
    {
        $account=$this->register();
        $this->post($this->root.'/ingresar',['email'=>$account->email,'password'=>'SecurePassword123!'])->assertRedirect($this->root.'/verificar-correo');
        $this->get($this->root.'/panel')->assertRedirect($this->root.'/verificar-correo');
        $this->verify($account); $this->get($this->root.'/panel')->assertOk()->assertSee('Tu historia comienza aquí')->assertDontSee('12.345.678-5');
        $this->assertFalse(Auth::guard('web')->check());
        $this->post($this->root.'/salir')->assertRedirect($this->root.'/ingresar');
        $this->get($this->root.'/panel')->assertRedirect($this->root.'/ingresar');
        $user=User::create(['name'=>'Internal test','email'=>Str::uuid().'@example.test','password'=>Hash::make('Internal123!'),'status'=>1]);
        Auth::guard('web')->setUser($user);
        $this->get($this->root.'/panel')->assertRedirect($this->root.'/ingresar');
        $this->assertSame($user->id,Auth::guard('web')->id());
    }
    public function test_password_recovery_is_neutral_one_use_and_invalidates_existing_portal_sessions(): void
    {
        $account=$this->register();$this->verify($account);$this->login($account);
        $this->post($this->root.'/recuperar-acceso',['email'=>'unknown@example.test'])->assertSessionHas('status');
        $this->post($this->root.'/recuperar-acceso',['email'=>$account->email])->assertSessionHas('status');
        $mail=Mail::sent(PortalSecurityLink::class)->last();$token=parse_url($mail->securityUrl,PHP_URL_FRAGMENT);
        $data=['security_token'=>$token,'password'=>'ChangedPassword123!','password_confirmation'=>'ChangedPassword123!'];
        $this->post($this->root.'/enlace/reset',$data)->assertRedirect($this->root.'/ingresar');
        $this->assertTrue(Hash::check('ChangedPassword123!',$account->fresh()->password));
        $this->get($this->root.'/panel')->assertRedirect($this->root.'/ingresar');
        $this->post($this->root.'/enlace/reset',$data)->assertSessionHasErrors('security_token');
    }

    public function test_reset_can_retry_password_validation_without_putting_token_in_session(): void
    {
        $account=$this->register();app(PortalAccountService::class)->sendLink($account,'reset');
        $token=parse_url(Mail::sent(PortalSecurityLink::class)->last()->securityUrl,PHP_URL_FRAGMENT);
        $this->post($this->root.'/enlace/reset',['security_token'=>$token,'password'=>'short','password_confirmation'=>'different'])
            ->assertStatus(422)->assertSee($token)->assertHeader('Referrer-Policy','no-referrer');
        $this->assertNull(session()->getOldInput('security_token'));
        $this->post($this->root.'/enlace/reset',['security_token'=>$token,'password'=>'RetryPassword123!','password_confirmation'=>'RetryPassword123!'])
            ->assertRedirect($this->root.'/ingresar');
        $this->assertTrue(Hash::check('RetryPassword123!',$account->fresh()->password));
    }
    public function test_profile_is_own_only_reauthenticates_and_preserves_prior_document(): void
    {
        $a=$this->register();$this->verify($a);$b=$this->register('AR');$this->login($a);
        $data=$this->data('AR');$data['current_password']='Wrong123!';unset($data['password'],$data['password_confirmation']);
        $this->put($this->root.'/perfil',$data)->assertSessionHasErrors('current_password');
        $data['current_password']='SecurePassword123!';$data['name']='My new name';$data['portal_account_id']=$b->id;
        $this->put($this->root.'/perfil',$data)->assertRedirect($this->root.'/perfil');
        $this->assertSame('My new name',$a->fresh()->name);$this->assertSame('Mi Salud test',$b->fresh()->name);
        $this->assertSame(2,DB::table('portal_account_identifiers')->where('portal_account_id',$a->id)->count());
        $this->assertSame(1,DB::table('portal_account_identifiers')->where('portal_account_id',$a->id)->whereNull('retired_at')->count());
        $this->get($this->root.'/perfil')->assertOk()->assertSee('000AbCd77')->assertSee('Identidad del titular no verificada');
    }
    public function test_cookie_is_distinct_host_only_and_path_scoped_and_tenant_host_cannot_serve_portal(): void
    {
        $response=$this->get($this->root.'/ingresar')->assertOk();
        $cookie=collect($response->headers->getCookies())->first(fn($c)=>$c->getName()==='vita_mi_salud_session');
        $this->assertNotNull($cookie);$this->assertSame('/mi-salud',$cookie->getPath());$this->assertNull($cookie->getDomain());$this->assertTrue($cookie->isHttpOnly());
        $this->get('http://unrelated.'.config('app.domain').'/mi-salud/ingresar')->assertNotFound();
    }

    public function test_login_and_registration_follow_superadmin_brand_mode_and_login_logo(): void
    {
        foreach (['title','logo','both'] as $mode) {
            \App\Models\VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>[
                'brand'=>'VITA Personal','header_brand_display'=>$mode,
                'logos'=>[
                    ['id'=>'header-logo','name'=>'Header','url'=>'vita/website/logos/header-test.png','status'=>1,'locations'=>['header']],
                    ['id'=>'login-logo','name'=>'Login','url'=>'vita/website/logos/login-test.png','status'=>1,'locations'=>['login']],
                ],
            ]]);
            foreach (['ingresar','registro'] as $page) {
                $response=$this->get($this->root.'/'.$page)->assertOk();
                if ($mode==='title') $response->assertDontSee('class="portal-brand-logo"',false);
                else $response->assertSee('storage/vita/website/logos/login-test.png',false)->assertDontSee('storage/vita/website/logos/header-test.png',false);
                $response->assertSee('class="portal-brand-title"',false)->assertSee('VITA Personal');
            }
        }
        \App\Models\VitaWebsiteSetting::whereKey(1)->update(['content'=>['brand'=>'VITA fallback','header_brand_display'=>'logo','logos'=>[]]]);
        $response=$this->get($this->root.'/ingresar')->assertOk();
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('admin/logos/logo-vita-3.png')) {
            $response->assertSee('storage/admin/logos/logo-vita-3.png',false)->assertSee('class="portal-brand-title"',false);
        } else {
            $response->assertSee('VITA fallback')->assertDontSee('class="portal-brand-logo"',false);
        }
    }
    public function test_resending_invalidates_prior_link_and_inactive_account_cannot_enter(): void
    {
        $account=$this->register();$token=parse_url(Mail::sent(PortalSecurityLink::class)->last()->securityUrl,PHP_URL_FRAGMENT);
        app(PortalAccountService::class)->sendLink($account,'verify');
        $this->assertFalse(app(PortalAccountService::class)->consume('verify',$token));
        $this->verify($account);$this->login($account);$account->active=false;$account->save();
        $this->get($this->root.'/panel')->assertRedirect($this->root.'/ingresar');
    }
    public function test_login_throttling_and_append_only_audit(): void
    {
        $account=$this->register();
        for($i=0;$i<6;$i++)$this->post($this->root.'/ingresar',['email'=>$account->email,'password'=>'WrongPassword123!'])->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('mi_salud')->check());
        try {DB::table('portal_audit_events')->where('portal_account_id',$account->id)->delete();$this->fail('Audit deletion allowed.');}
        catch(\Illuminate\Database\QueryException $error){$this->assertStringContainsString('append only',$error->getMessage());}
    }

    public function test_identifier_failure_rolls_back_account_identifier_and_audit_together(): void
    {
        $data=$this->data();$audit=DB::table('portal_audit_events')->count();
        DB::unprepared("CREATE TRIGGER portal_test_failure BEFORE INSERT ON portal_account_identifiers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic storage failure'");
        try {
            try {app(PortalAccountService::class)->register($data);$this->fail('Expected atomic failure.');}
            catch(\RuntimeException $error){$this->assertSame('No se pudo guardar la cuenta de Mi Salud.',$error->getMessage());}
        } finally {DB::unprepared('DROP TRIGGER portal_test_failure');}
        $this->assertFalse(PortalAccount::where('email',$data['email'])->exists());
        $this->assertSame($audit,DB::table('portal_audit_events')->count());Mail::assertNothingSent();
    }

    public function test_missing_crypto_configuration_fails_before_account_creation(): void
    {
        $data=$this->data();config(['identification.encryption_keys'=>[]]);
        try {app(PortalAccountService::class)->register($data);$this->fail('Expected fail closed.');}
        catch(\RuntimeException){$this->assertFalse(PortalAccount::where('email',$data['email'])->exists());}
    }

    public function test_local_panel_preview_is_session_only_and_cannot_bypass_verification_in_production(): void
    {
        $account=$this->register();
        $this->post($this->root.'/ingresar',['email'=>$account->email,'password'=>'SecurePassword123!'])->assertRedirect($this->root.'/verificar-correo');
        $this->app['env']='local';
        $this->get($this->root.'/verificar-correo')->assertOk()->assertSee('Entrar al panel en local');
        $this->post($this->root.'/vista-local',['_token'=>session()->token()])->assertRedirect($this->root.'/panel');
        $this->get($this->root.'/panel')->assertOk()->assertSee('Vista local de prueba');
        $this->assertNull($account->fresh()->email_verified_at);
        $this->assertFalse(Auth::guard('web')->check());
        $this->app['env']='production';
        $this->get($this->root.'/panel')->assertRedirect($this->root.'/verificar-correo');
        $this->post($this->root.'/vista-local',['_token'=>session()->token()])->assertNotFound();
        $this->get($this->root.'/verificar-correo')->assertDontSee('Entrar al panel en local');
        $this->app['env']='testing';
    }

    public function test_post_without_csrf_is_rejected_and_security_page_has_no_external_resources(): void
    {
        $this->app['env']='local';
        $this->post($this->root.'/registro',$this->data())->assertStatus(419);
        $this->app['env']='testing';
        $this->get($this->root.'/enlace/verify')->assertOk()->assertDontSee('google-analytics')->assertDontSee('cdnjs')->assertDontSee('<iframe',false);
    }
}
