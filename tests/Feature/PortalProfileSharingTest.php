<?php
namespace Tests\Feature;

use App\Models\PortalAccount;
use App\Services\MiSalud\{PortalAccountService,PortalProfileSharingService,PortalPatientLinkService};
use Illuminate\Support\Facades\{DB,Mail};
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class PortalProfileSharingTest extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as fixtureSetUp; tearDown as fixtureTearDown; }
    private bool $started=false;
    private string $rut;
    protected function setUp(): void {
        if(getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',getenv('DB_DATABASE')?:''))$this->markTestSkipped('Isolated MySQL only.');
        $this->fixtureSetUp();$this->started=true;
        config(['identification'=>IdentifierStorageKeys::config()+['application_key'=>config('app.key')],'cache.default'=>'array']);Mail::fake();
        $this->actingAs($this->user('Administrador'));
        $body=(string)random_int(30000000,39000000);$sum=0;$weight=2;
        foreach(array_reverse(str_split($body))as$d){$sum+=(int)$d*$weight;$weight=$weight===7?2:$weight+1;}
        $dv=11-$sum%11;$this->rut=$body.($dv===11?'0':($dv===10?'K':$dv));
    }
    protected function tearDown(): void {if($this->started)$this->fixtureTearDown();}
    private function account(bool $sharing=true): PortalAccount {
        $email=Str::uuid().'@example.test';app(PortalAccountService::class)->register(['name'=>'Portal basic name','email'=>$email,'password'=>'SecurePassword123!',
            'identification_country'=>'CL','identification_type'=>'national_id','identification_number'=>$this->rut]);
        $account=PortalAccount::where('email',$email)->firstOrFail();$account->email_verified_at=now();$account->save();
        app(PortalProfileSharingService::class)->save($account,['share_basic_profile'=>$sharing,'gender'=>'Otro','birth_date'=>'1990-01-01','phone'=>'912345678','address'=>'Private basic address']);
        while(app(PortalPatientLinkService::class)->drain(100)){}
        return $account;
    }
    private function lookup() {return $this->postJson($this->root.'/patient/mi-salud-profile',['identification_country'=>'CL','identification_type'=>'national_id','identification_number'=>$this->rut]);}
    public function test_lookup_returns_only_authorized_basic_fields_and_audits_without_document(): void {
        $a=$this->account();$response=$this->lookup()->assertOk()->assertJsonPath('profile.name','Portal basic name')->assertJsonPath('profile.phone','912345678');
        $this->assertSame(['gender','birth_date','phone','address','name','email','document'],array_keys($response->json('profile')));
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('Private basic address',DB::table('portal_profile_sharing')->where('portal_account_id',$a->id)->value('encrypted_profile'));
        $event=DB::table('portal_profile_lookup_events')->latest('id')->first();$this->assertSame($this->clinic->id,$event->customer_id);
        $this->assertStringNotContainsString($this->rut,json_encode($event));
    }
    public function test_consent_revocation_and_ambiguity_are_neutral(): void {
        $a=$this->account(false);$this->lookup()->assertOk()->assertExactJson(['profile'=>null]);
        app(PortalProfileSharingService::class)->save($a,['share_basic_profile'=>true]);$this->lookup()->assertJsonPath('profile.name','Portal basic name');
        $this->account(false);$this->lookup()->assertExactJson(['profile'=>null]);
    }
    public function test_missing_and_unverified_accounts_do_not_reveal_data(): void {
        $this->lookup()->assertExactJson(['profile'=>null]);$a=$this->account();$a->email_verified_at=null;$a->save();
        $this->lookup()->assertExactJson(['profile'=>null]);
    }
    public function test_staff_without_create_permission_cannot_lookup(): void {
        $this->account();$actor=$this->user('WithoutPatientCreate');
        \App\Models\Role::where('customer_id',$this->clinic->id)->where('name','WithoutPatientCreate')->update(['permissions_configured'=>true]);
        $this->actingAs($actor);$this->lookup()->assertForbidden();
    }
    public function test_invalid_document_is_rejected(): void {
        $this->rut='123456789';$this->lookup()->assertUnprocessable();
    }
    public function test_autofill_decision_does_not_control_registration_or_linking(): void {
        $a=$this->account();
        // The client may accept or ignore the returned profile; neither decision is submitted.
        $this->lookup()->assertOk();
        $data=['name'=>'Manually entered name','email'=>'manual@example.test','phone'=>'912345678','gender'=>'Otro','birth_date'=>'1990-01-01','address'=>'Chile',
            'identification_country'=>'CL','identification_type'=>'national_id','identification_number'=>$this->rut,'registration_operation'=>(string)Str::uuid()];
        $id=$this->postJson($this->root.'/patient',$data)->assertOk()->json('data.id');
        while(app(PortalPatientLinkService::class)->drain(100)){}
        $this->assertSame('candidate',DB::table('portal_patient_links')->where('portal_account_id',$a->id)->where('patient_id',$id)->value('status'));
        $this->assertSame('Portal basic name',$a->fresh()->name);
        $this->assertDatabaseHas('patients',['id'=>$id,'name'=>'Manually entered name','customer_id'=>$this->clinic->id]);
        $data['registration_operation']=(string)Str::uuid();$this->postJson($this->root.'/patient',$data)->assertUnprocessable();
        $this->lookup()->assertExactJson(['profile'=>null]);
    }
}
