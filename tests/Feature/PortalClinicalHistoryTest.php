<?php

namespace Tests\Feature;

use App\Models\{Encounter, MedicalRecord, Permission, PortalAccount, PortalClinicalGrant};
use App\Services\MiSalud\{PortalAccountService, PortalClinicalAccessService, PortalPatientLinkService};
use Illuminate\Support\Facades\{Auth, DB, Mail};
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class PortalClinicalHistoryTest extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as private fixtureSetUp; }
    private PortalAccount $account;
    private $administrator;
    private string $portal;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        config(['identification'=>IdentifierStorageKeys::config()+['application_key'=>config('app.key')]]);
        Mail::fake();
        $this->patient->update(['rut'=>'12.345.678-5','gender'=>'Otro','status'=>1]);
        $this->administrator=$this->user('Administrador');
        $role=$this->administrator->branchRoles()->where('roles.customer_id',$this->clinic->id)->firstOrFail();
        foreach (['branches.admin_all','medical_records.manage','medical_records.view','patients.view'] as $slug) {
            $permission=Permission::firstOrCreate(['slug'=>$slug],['name'=>$slug,'module'=>explode('.',$slug)[0]]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->account=$this->account();
        $this->sync();
        $this->portal='http://'.config('app.domain').'/mi-salud/mis-atenciones';
        $this->patient->medicalRecord()->create(['customer_id'=>$this->clinic->id]);
        $this->patient->medicalRecord->allergies()->create(['customer_id'=>$this->clinic->id,'allergen'=>'Alergia de prueba','reaction'=>'Reacción de prueba']);
    }

    private function account(string $document='12.345.678-5'): PortalAccount
    {
        $email=Str::uuid().'@example.test';
        app(PortalAccountService::class)->register(['name'=>'Portal patient','email'=>$email,'password'=>'SecurePassword123!',
            'identification_country'=>'CL','identification_type'=>'national_id','identification_number'=>$document]);
        $account=PortalAccount::where('email',$email)->firstOrFail();
        $account->forceFill(['email_verified_at'=>now(),'active'=>true])->save();
        return $account->fresh();
    }
    private function sync(): void { while(app(PortalPatientLinkService::class)->drain(100)) {} }
    private function asPatient(?PortalAccount $account=null): void
    {
        $account??=$this->account;
        $this->actingAs($account,'mi_salud')->withSession(['portal_session_version'=>$account->session_version]);
    }
    private function asClinic(): void
    {
        $this->actingAs($this->administrator,'web');
        $this->app->instance('customer',$this->clinic);
        session(['active_branch_customer_id'=>$this->clinic->id,'active_branch_id'=>$this->branch->id]);
    }
    private function approve(): PortalClinicalGrant
    {
        $this->asClinic();
        return app(PortalClinicalAccessService::class)->approve($this->administrator,$this->patient,[
            'email'=>$this->account->email,'verification_method'=>'in_person','password'=>'correct-password']);
    }
    private function finalized(bool $attachment=false): void
    {
        $this->actingAs($this->doctor,'web');$this->app->instance('customer',$this->clinic);
        if ($attachment) $this->attachment();
        $this->sign($this->prepare())->assertOk();
    }

    public function test_matching_document_alone_does_not_reveal_clinics_or_records(): void
    {
        $this->finalized();$this->asPatient();
        $this->get($this->portal)->assertOk()->assertSee('Mis atenciones')->assertSee('Aún no tienes fichas habilitadas')
            ->assertDontSee($this->clinic->name)->assertDontSee('Diagnóstico firmado')->assertDontSee('Alergia de prueba');
        $this->assertFalse((bool)DB::table('portal_patient_links')->where('portal_account_id',$this->account->id)->value('clinical_access'));
    }

    public function test_approved_patient_reads_signed_history_and_private_attachments_without_tenant_context(): void
    {
        $this->finalized(true);$grant=$this->approve();$this->asPatient();
        $this->get($this->portal)->assertOk()->assertSee($this->clinic->name)->assertSee('1');
        $this->get(route('mi-salud.panel').'?year='.now()->year)->assertOk()
            ->assertViewHas('summary',fn($summary)=>$summary['total']===1
                && count($summary['months'])===12 && $summary['clinics'][0]['count']===1
                && $summary['professionals'][0]['count']===1
                && $summary['latest'][0]['encounter_id']===$this->encounter->id);
        $this->get(route('mi-salud.panel').'?year='.(now()->year-1))->assertOk()
            ->assertViewHas('summary',fn($summary)=>$summary['total']===0 && $summary['professionals']===[]);
        $this->get(route('mi-salud.panel').'?year=invalid')->assertSessionHasErrors('year');
        $this->get(route('mi-salud.panel').'?year='.now()->year.'&month=13')->assertSessionHasErrors('month');
        $month=$this->encounter->started_at->timezone(config('app.timezone'))->month;
        $this->get(route('mi-salud.panel').'?year='.now()->year.'&month='.$month)->assertOk()
            ->assertViewHas('summary',fn($summary)=>$summary['total']===1 && $summary['month']===$month);
        $other=$month===12 ? 1 : $month+1;
        $this->get(route('mi-salud.panel').'?year='.now()->year.'&month='.$other)->assertOk()
            ->assertViewHas('summary',fn($summary)=>$summary['total']===0 && $summary['clinics']===[] && array_sum($summary['months'])===1);
        $this->get(route('mi-salud.panel').'?year='.now()->year.'&month='.$month,['X-Mi-Salud-Summary'=>'1'])
            ->assertOk()->assertHeader('X-Mi-Salud-Summary','1')->assertSee('data-attention-results',false)
            ->assertDontSee('id="portal-sidebar"',false)->assertViewHas('summary',fn($summary)=>$summary['total']===1);
        $this->get($this->portal.'/'.$grant->id)->assertOk()->assertSee('Alergia de prueba')->assertSee('Motivo de prueba');
        $url=$this->portal.'/'.$grant->id.'/atenciones/'.$this->encounter->id;
        $this->get($url)->assertOk()->assertSee('Diagnóstico firmado')->assertSee('Tratamiento firmado');
        $document=$this->encounter->documents()->firstOrFail();
        $this->get($url.'/documentos/'.$document->id)->assertOk()->assertStreamedContent('private attachment bytes');
        $this->assertNull(app()->bound('customer') ? app('customer') : null);
        $this->assertTrue(DB::table('portal_clinical_events')->where('grant_id',$grant->id)->where('action','document_downloaded')->exists());
    }

    public function test_other_patient_and_unsigned_encounter_cannot_be_opened_even_with_known_ids(): void
    {
        $grant=$this->approve();$this->asPatient();
        $this->get($this->portal.'/'.$grant->id.'/atenciones/'.$this->encounter->id)->assertNotFound();
        $other=$this->account('11.111.111-1');$this->sync();$this->asPatient($other);
        $this->get($this->portal.'/'.$grant->id)->assertNotFound();
        $this->get($this->portal.'/'.$grant->id.'/atenciones/'.$this->encounter->id)->assertNotFound();
    }

    public function test_revocation_and_identity_changes_invalidate_access_immediately(): void
    {
        $grant=$this->approve();$this->asPatient();
        $this->get($this->portal.'/'.$grant->id)->assertOk();
        // Bypass the reconciler deliberately: the current identity fingerprint must still fail closed.
        DB::table('patients')->where('id',$this->patient->id)->update(['rut'=>'11.111.111-1']);
        $this->get($this->portal.'/'.$grant->id)->assertNotFound();
        DB::table('patients')->where('id',$this->patient->id)->update(['rut'=>'12.345.678-5']);
        $this->asClinic();app(PortalClinicalAccessService::class)->revoke($this->administrator,$this->patient,$grant->id);
        $this->asPatient();$this->get($this->portal.'/'.$grant->id)->assertNotFound();
        $this->get($this->portal)->assertDontSee($this->clinic->name);
    }

    public function test_only_authorized_clinic_administration_can_approve_and_confirmation_is_required(): void
    {
        $url=$this->root.'/patients/'.$this->patient->id.'/mi-salud-access';
        $this->actingAs($this->doctor,'web')->get($url.'?_vita_content=1')->assertForbidden();
        $this->asClinic();$this->get($url.'?_vita_content=1')->assertOk();
        $data=['email'=>$this->account->email,'verification_method'=>'in_person','identity_confirmed'=>1,'password'=>'correct-password'];
        $this->postJson($url,array_replace($data,['identity_confirmed'=>0]))->assertUnprocessable();
        $this->postJson($url,array_replace($data,['password'=>'wrong-password']))->assertUnprocessable();
        $this->post($url,$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1,PortalClinicalGrant::where('patient_id',$this->patient->id)->count());
        $this->post($url,$data)->assertSessionHasErrors('email');
    }

    public function test_ambiguous_matching_is_not_an_authorization_and_audit_is_immutable(): void
    {
        $grant=$this->approve();
        $this->account();$this->sync();$this->asPatient();
        $this->get($this->portal.'/'.$grant->id)->assertNotFound();
        $event=DB::table('portal_clinical_events')->where('grant_id',$grant->id)->first();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('portal_clinical_events')->where('id',$event->id)->update(['action'=>'changed']);
    }

    public function test_two_centers_are_grouped_separately_and_grant_cannot_cross_between_their_encounters(): void
    {
        $this->clinic->update(['name'=>'Centro Norte']);$this->finalized();$north=$this->approve();
        $this->fixtures();$this->clinic->update(['name'=>'Centro Sur']);
        $this->patient->update(['rut'=>'12.345.678-5','status'=>1]);
        $this->encounter->update(['chief_complaint'=>'Atención privada Sur']);
        $this->administrator=$this->user('Administrador');
        $role=$this->administrator->branchRoles()->where('roles.customer_id',$this->clinic->id)->firstOrFail();
        foreach (['branches.admin_all','medical_records.manage','medical_records.view'] as $slug) {
            $role->permissions()->syncWithoutDetaching([Permission::where('slug',$slug)->firstOrFail()->id]);
        }
        $this->sync();$this->finalized();$south=$this->approve();
        $this->asPatient();
        $this->get($this->portal)->assertOk()->assertSee('Centro Norte')->assertSee('Centro Sur');
        $this->get($this->portal.'/'.$north->id)->assertOk()->assertDontSee('Atención privada Sur');
        $this->get($this->portal.'/'.$north->id.'/atenciones/'.$this->encounter->id)->assertNotFound();
        $this->get($this->portal.'/'.$south->id.'/atenciones/'.$this->encounter->id)->assertOk()->assertSee('Atención privada Sur');
    }

    public function test_unsigned_and_foreign_attachments_are_not_available_from_the_portal(): void
    {
        $file=$this->attachment();$grant=$this->approve();$this->asPatient();
        $this->get($this->portal.'/'.$grant->id.'/atenciones/'.$this->encounter->id.'/documentos/'.$file->id)->assertNotFound();
    }

    public function test_panel_shows_three_latest_authorized_signed_attentions_across_period_filters(): void
    {
        $fields=$this->encounter->only(['customer_id','clinic_branch_id','patient_id','professional_id','status','chief_complaint','modality']);
        $ids=[];
        foreach ([30,2,10,1] as $days) {
            $this->encounter=Encounter::create($fields+['started_at'=>now()->subDays($days)]);
            $ids[]=$this->encounter->id;
            $this->finalized();
        }
        $grant=$this->approve();$this->asPatient();
        $this->get(route('mi-salud.panel').'?year=2000&month=1')->assertOk()->assertSee('Tus últimas atenciones')
            ->assertViewHas('summary',fn($summary)=>array_column($summary['latest'],'encounter_id')===[$ids[3],$ids[1],$ids[2]]
                && $summary['total']===0 && $summary['latest'][0]['clinic']===$this->clinic->name
                && $summary['latest'][0]['specialty']==='Atención clínica');
        $this->asClinic();app(PortalClinicalAccessService::class)->revoke($this->administrator,$this->patient,$grant->id);
        $this->asPatient();
        $this->get(route('mi-salud.panel'))->assertOk()->assertViewHas('summary',fn($summary)=>$summary['latest']===[]);
    }

    public function test_booking_directory_shows_only_published_centers_with_enabled_online_booking(): void
    {
        \App\Models\WebsitePage::create(['customer_id'=>$this->clinic->id,'slug'=>'inicio','title'=>'Inicio','status'=>1]);
        $profile=\App\Models\CustomerDirectoryProfile::create(['customer_id'=>$this->clinic->id,'slug'=>'booking-'.Str::lower(Str::random(12)),
            'description'=>'Centro para reservar una atención','city'=>'Quillota','publication_enabled'=>true,
            'authorized_by'=>$this->administrator->id,'authorized_at'=>now(),'authorization_version'=>'directory-v1']);
        $settings=\App\Models\WebsiteNavigationButton::create(['customer_id'=>$this->clinic->id,'public_booking_enabled'=>true]);
        $this->asPatient();
        $url=route('mi-salud.booking.index');
        $this->get($url)->assertOk()->assertSee($this->clinic->name)->assertSee('Agendar atención')
            ->assertSee(route('customer.public-booking.index',['customer'=>$this->clinic->domain]),false);
        $this->get($url.'?buscar=Quillota')->assertOk()->assertSee($this->clinic->name);
        $profile->update(['latitude'=>-32.88,'longitude'=>-71.25]);
        $this->postJson(route('mi-salud.booking.location'),['latitude'=>-32.880123,'longitude'=>-71.250123])
            ->assertOk()->assertSessionHas('portal_booking_location',fn($location)=>$location['latitude']===-32.88
                && $location['longitude']===-71.25 && $location['expires_at']>time());
        $this->get($url.'?cercanas=1')->assertOk()->assertSee($this->clinic->name)->assertSee('km aproximadamente')
            ->assertViewHas('nearby',true);
        $this->withSession(['portal_booking_location'=>['latitude'=>-32.88,'longitude'=>-71.25,'expires_at'=>time()-1]])
            ->get($url.'?cercanas=1')->assertOk()->assertViewHas('locationExpired',true)->assertViewHas('nearby',false);
        $this->postJson(route('mi-salud.booking.location'),['latitude'=>100,'longitude'=>-71])->assertUnprocessable();
        $this->get($url.'?buscar=NoExisteUnCentroConEsteNombre')->assertOk()->assertDontSee($this->clinic->name);
        $settings->update(['public_booking_enabled'=>false]);
        $this->get($url)->assertOk()->assertDontSee($this->clinic->name);
        $settings->update(['public_booking_enabled'=>true]);$profile->update(['publication_enabled'=>false]);
        $this->get($url)->assertOk()->assertDontSee($this->clinic->name);
    }

    public function test_patient_assistant_keeps_patient_context_and_never_returns_global_customer_counts(): void
    {
        $this->asPatient();
        $this->postJson(route('mi-salud.assistant.ask'),['message'=>'Cuántos clientes nuevos tiene VITA este mes','context'=>'superadmin'])
            ->assertOk()->assertJsonPath('context','mi-salud')->assertJsonMissingPath('summary')->assertSee('Mi Salud');
    }

    public function test_unverified_preview_and_disabled_accounts_cannot_read_clinical_history(): void
    {
        $grant=$this->approve();
        $this->account->forceFill(['email_verified_at'=>null])->save();$this->asPatient();
        $this->app->instance('env','local');
        $this->get($this->portal)->assertRedirect(route('mi-salud.verify.notice'));
        $this->withSession(['portal_local_preview_account'=>$this->account->id]);
        $this->get($this->portal)->assertOk()->assertSee('Vista de prueba local')->assertDontSee($this->clinic->name);
        $this->get($this->portal.'/'.$grant->id)->assertForbidden();
        $this->account->forceFill(['active'=>false])->save();
        $this->get($this->portal)->assertRedirect(route('mi-salud.login'));
    }
}
