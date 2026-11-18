<?php
namespace Tests\Feature;

use App\Models\{PortalAccount, Patient, Appointment, ProfessionalSchedule, WebsiteNavigationButton, Specialty};
use App\Services\MiSalud\{PortalAccountService, PortalProfileSharingService, PortalPatientLinkService, PortalBookingService};
use Illuminate\Support\Facades\{DB, Mail, Notification};
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class PortalPublicBookingTest extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as fixtureSetUp; tearDown as fixtureTearDown; }
    private bool $started=false;
    private array $selection;
    protected function setUp(): void
    {
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',getenv('DB_DATABASE')?:'')) $this->markTestSkipped('Isolated MySQL only.');
        $this->fixtureSetUp();$this->started=true;
        config(['identification'=>IdentifierStorageKeys::config()+['application_key'=>config('app.key')],
            'patient_registration.enabled'=>true,'patient_registration.channels.public_booking'=>true,'cache.default'=>'array']);
        Mail::fake();Notification::fake();
        $this->mock(\App\Support\ClinicAppointmentMessenger::class)->shouldReceive('send')->andReturn([]);
        app(\App\Services\SpecialtyCatalog::class)->sync($this->doctor,$this->clinic->id,$this->branch->id,[-1089]);
        $specialty=Specialty::where('customer_id',$this->clinic->id)->where('catalog_id',1089)->value('id');
        WebsiteNavigationButton::create(['customer_id'=>$this->clinic->id,'public_booking_enabled'=>true]);
        $day=now()->addDays(14)->startOfDay();
        ProfessionalSchedule::create(['customer_id'=>$this->clinic->id,'clinic_branch_id'=>$this->branch->id,'user_id'=>$this->doctor->id,
            'day_of_week'=>$day->dayOfWeek,'start_time'=>'09:00:00','end_time'=>'18:00:00']);
        $this->selection=['specialty_id'=>$specialty,'professional_id'=>$this->doctor->id,'clinic_branch_id'=>$this->branch->id,
            'date'=>$day->toDateString(),'time'=>'10:00','modality'=>'in_person','reason'=>'Appointment draft'];
    }
    protected function tearDown(): void {if($this->started)$this->fixtureTearDown();}
    private function account(?string $document=null,string $country='AR'): PortalAccount
    {
        $email=Str::uuid().'@example.test';
        app(PortalAccountService::class)->register(['name'=>'Booking owner','email'=>$email,'password'=>'SecurePassword123!',
            'identification_country'=>$country,'identification_type'=>$country==='CL'?'national_id':'passport','identification_number'=>$document??'000'.Str::upper(Str::random(12))]);
        $a=PortalAccount::where('email',$email)->firstOrFail();$a->email_verified_at=now();$a->save();
        app(PortalProfileSharingService::class)->save($a,['gender'=>'Otro','phone'=>'912345678','birth_date'=>'1990-01-01','address'=>'Chile']);
        while(app(PortalPatientLinkService::class)->drain(100)){}
        return $a;
    }
    private function grant(PortalAccount $a): string
    {
        $bridge=app(PortalBookingService::class);$session=session()->getId();
        $this->withCookie(config('session.cookie'),$session);
        $id=$bridge->begin($this->clinic->id,$session,$this->selection);
        $code=$bridge->authorize($id,$a);$bridge->redeem($id,$session,$code,$this->clinic->id);
        return $id;
    }
    public function test_bridge_is_one_use_bound_to_clinic_and_session_and_payload_is_encrypted(): void
    {
        $a=$this->account();$b=app(PortalBookingService::class);$id=$b->begin($this->clinic->id,'session-A',$this->selection+['name'=>'FORBIDDEN']);
        $code=$b->authorize($id,$a);
        foreach ([['session-B',$code,$this->clinic->id],['session-A',str_repeat('a',64),$this->clinic->id],['session-A',$code,$this->clinic->id+1]] as $args) {
            try {$b->redeem($id,...$args);$this->fail('Invalid authorization accepted.');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(403,$e->getStatusCode());}
        }
        $payload=$b->redeem($id,'session-A',$code,$this->clinic->id);
        $this->assertSame($this->selection,$payload['selection']);$this->assertSame($a->email,$payload['personal']['email']);
        $row=DB::table('portal_booking_authorizations')->find($id);
        $this->assertNull($row->code_hash);$this->assertStringNotContainsString($a->email,$row->encrypted_payload);
        $this->assertArrayNotHasKey('authorization_code',$b->payload($row));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);$b->redeem($id,'session-A',$code,$this->clinic->id);
    }
    public function test_expired_and_changed_identity_authorizations_are_rejected(): void
    {
        $a=$this->account();$id=$this->grant($a);$b=app(PortalBookingService::class);
        $a->name='Changed after preview';$a->save();
        try {$b->grant($id,session()->getId(),$this->clinic->id);$this->fail();}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}
        DB::table('portal_booking_authorizations')->where('id',$id)->update(['expires_at'=>now()->subSecond()]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);$b->intent($id);
    }
    public function test_public_channel_remains_a_hard_gate(): void
    {
        config(['patient_registration.channels.public_booking'=>false]);
        $this->post($this->root.'/agendar/mi-salud',$this->selection)->assertStatus(503);
        $this->assertSame(0,DB::table('portal_booking_authorizations')->where('customer_id',$this->clinic->id)->count());
    }
    public function test_portal_can_book_new_patient_and_reuse_it_without_duplicate_or_clinical_access(): void
    {
        $this->withoutExceptionHandling();
        $a=$this->account();$id=$this->grant($a);
        $before=Patient::where('customer_id',$this->clinic->id)->count();
        $this->withSession(['portal_booking_grant'=>$id])->post($this->root.'/agendar',$this->selection+['portal_account_id'=>999,'patient_id'=>999])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before+1,Patient::where('customer_id',$this->clinic->id)->count());
        $evidence=DB::table('portal_booking_authorizations')->find($id);$this->assertNotNull($evidence->completed_at);
        $patient=Patient::findOrFail($evidence->patient_id);$this->assertNull($patient->rut);$this->assertNotNull($patient->medicalRecord);
        $this->assertSame(1,Appointment::where('patient_id',$patient->id)->count());
        Mail::assertNotSent(\App\Mail\VerifyPatientRegistration::class);
        while(app(PortalPatientLinkService::class)->drain(100)){}
        $this->selection['time']='11:00';$second=$this->grant($a);
        $this->withSession(['portal_booking_grant'=>$second])->post($this->root.'/agendar',$this->selection)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before+1,Patient::where('customer_id',$this->clinic->id)->count());
        $this->assertSame(2,Appointment::where('patient_id',$patient->id)->count());
        $this->assertSame(0,(int)DB::table('portal_patient_links')->where('patient_id',$patient->id)->value('clinical_access'));
    }
    public function test_missing_data_are_requested_and_filled_values_cannot_be_overridden(): void
    {
        $a=$this->account();app(PortalProfileSharingService::class)->save($a,['gender'=>'Otro']);
        $id=$this->grant($a);
        $this->withSession(['portal_booking_grant'=>$id])->post($this->root.'/agendar',$this->selection+['name'=>'Injected name','email'=>'attacker@example.test'])
            ->assertSessionHasErrors('phone');
        $this->assertNull(DB::table('portal_booking_authorizations')->find($id)->completed_at);
        $this->withSession(['portal_booking_grant'=>$id])->post($this->root.'/agendar',$this->selection+['phone'=>'912345678','name'=>'Injected name','email'=>'attacker@example.test'])->assertSessionHasNoErrors();
        $patient=Patient::findOrFail(DB::table('portal_booking_authorizations')->find($id)->patient_id);
        $this->assertSame($a->email,$patient->email);$this->assertSame('Booking owner',$patient->name);
    }
    public function test_chilean_portal_booking_preserves_valid_rut_mirror_and_encrypted_identifier(): void
    {
        $body=(string)random_int(30000000,39000000);$sum=0;$weight=2;
        foreach(array_reverse(str_split($body))as$d){$sum+=(int)$d*$weight;$weight=$weight===7?2:$weight+1;}
        $dv=11-$sum%11;$rut=$body.($dv===11?'0':($dv===10?'K':$dv));
        $a=$this->account($rut,'CL');$id=$this->grant($a);
        $this->withSession(['portal_booking_grant'=>$id])->post($this->root.'/agendar',$this->selection)->assertRedirect()->assertSessionHasNoErrors();
        $patientId=DB::table('portal_booking_authorizations')->find($id)->patient_id;
        $this->assertSame($rut,Patient::findOrFail($patientId)->rut);
        $this->assertSame(1,DB::table('patient_identifiers')->where('patient_id',$patientId)->where('issuing_country_code','CL')->count());
    }
    public function test_historical_match_never_creates_duplicate_or_grants_clinical_access(): void
    {
        $a=$this->account();$id=$this->grant($a);$personal=app(PortalBookingService::class)->payload(DB::table('portal_booking_authorizations')->find($id))['personal'];
        Patient::create(['customer_id'=>$this->clinic->id,'clinic_branch_id'=>$this->branch->id,'name'=>'Historical private record',
            'rut'=>$personal['identification_number'],'gender'=>'Otro','birth_date'=>'1990-01-01']);
        $before=Patient::where('customer_id',$this->clinic->id)->count();
        $this->withSession(['portal_booking_grant'=>$id])->post($this->root.'/agendar',$this->selection)->assertRedirect()->assertSessionHasErrors('time');
        $this->assertSame($before,Patient::where('customer_id',$this->clinic->id)->count());
        $this->assertNull(DB::table('portal_booking_authorizations')->find($id)->completed_at);
        $this->assertSame(0,Appointment::where('customer_id',$this->clinic->id)->count());
    }
    public function test_return_renders_selection_and_only_missing_fields_without_private_profile_ids(): void
    {
        $a=$this->account();$id=$this->grant($a);
        $response=$this->withSession(['portal_booking_grant'=>$id])->get($this->root.'/agendar')->assertOk()->assertSee('Confirmar cita')->assertSee('Booking owner');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('portal-booking-form.js',$response->getContent());
        $this->assertStringContainsString('Appointment draft',$response->getContent());
        $this->assertStringNotContainsString('name="portal_account_id"',$response->getContent());
        if (getenv('VITA_PORTAL_BOOKING_PREVIEW')==='yes') {
            $directory=storage_path('framework/testing/portal-booking-preview');if(!is_dir($directory))mkdir($directory,0700,true);
            file_put_contents($directory.'/booking.html',$response->getContent());
        }
    }
    public function test_real_mysql_concurrent_portal_bookings_only_confirm_one_slot(): void
    {
        $a=$this->account();$b=$this->account();$first=$this->grant($a);$second=$this->grant($b);$session=session()->getId();
        DB::commit(); // Committed synthetic fixtures only in the isolated MySQL schema.
        DB::beginTransaction();DB::table('customers')->where('id',$this->clinic->id)->lockForUpdate()->first();$workers=[];
        try {
            foreach([$first,$second] as $id){
                $gate=tempnam(sys_get_temp_dir(),'portal-booking-');unlink($gate);
                $worker=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/portal-booking-worker.php'),(string)$this->clinic->id,$id,$session,$gate],base_path());
                $worker->setTimeout(40);$worker->start();$workers[]=[$worker,$gate];
            }
            $deadline=microtime(true)+15;
            do {
                $ready=count(array_filter($workers,fn($w)=>is_file($w[1].'.ready')));
                $waits=(int)DB::selectOne('SELECT COUNT(*) AS n FROM performance_schema.data_lock_waits')->n;
                if($ready===2 && $waits>=2)break;
                usleep(30000);
            }while(microtime(true)<$deadline);
            $this->assertSame(2,$ready);$this->assertGreaterThanOrEqual(2,$waits,'Both real MySQL connections must contend on the clinic lock.');DB::commit();
            $results=[];foreach($workers as[$worker]){$worker->wait();$this->assertTrue($worker->isSuccessful(),$worker->getOutput());$results[]=$worker->getOutput();}
            sort($results);$this->assertSame(['completed','unavailable'],$results);
            $this->assertSame(1,Appointment::where('customer_id',$this->clinic->id)->count());
        }finally{
            if(DB::transactionLevel())DB::rollBack();foreach($workers as[$worker,$gate]){if($worker->isRunning())$worker->stop();if(is_file($gate.'.ready'))unlink($gate.'.ready');}
        }
    }
    public function test_multiple_accounts_declaring_same_document_do_not_authorize_booking(): void
    {
        $number='000'.Str::upper(Str::random(12));$a=$this->account($number);$this->account($number);$id=$this->grant($a);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);
        app(PortalBookingService::class)->grant($id,session()->getId(),$this->clinic->id);
    }
    public function test_unverified_email_cannot_be_used_as_booking_authorization_even_in_local_preview(): void
    {
        $a=$this->account();$a->email_verified_at=null;$a->save();
        $bridge=app(PortalBookingService::class);$id=$bridge->begin($this->clinic->id,session()->getId(),$this->selection);
        session(['portal_local_preview_account'=>$a->id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);$bridge->authorize($id,$a);
    }
    public function test_authorization_code_never_leaves_backend_and_central_cookie_is_host_only(): void
    {
        $a=$this->account();$bridge=app(PortalBookingService::class);$id=$bridge->begin($this->clinic->id,session()->getId(),$this->selection);
        \Illuminate\Support\Facades\Auth::guard('mi_salud')->setUser($a);
        $this->withSession(['portal_session_version'=>$a->session_version])->withCookie('vita_mi_salud_session',session()->getId());
        $response=$this->post(route('mi-salud.booking.approve',['intent'=>$id]),['authorize'=>1])->assertStatus(303);
        $location=$response->headers->get('Location');$row=DB::table('portal_booking_authorizations')->find($id);$code=$bridge->payload($row)['authorization_code'];
        $this->assertStringNotContainsString($code,$location);$this->assertStringNotContainsString('#',$location);
        $this->assertStringNotContainsString($code,$response->getContent());
        $cookies=array_filter($response->headers->getCookies(),fn($cookie)=>$cookie->getName()==='vita_mi_salud_session');
        $this->assertCount(1,$cookies);$cookie=array_values($cookies)[0];$this->assertNull($cookie->getDomain());$this->assertSame('/mi-salud',$cookie->getPath());
    }
    public function test_fragment_landing_has_no_external_resources_and_login_return_is_fixed(): void
    {
        $this->withoutExceptionHandling();
        $a=$this->account();$bridge=app(PortalBookingService::class);$id=$bridge->begin($this->clinic->id,session()->getId(),$this->selection);
        $callback=$bridge->callback($bridge->intent($id));
        $landing=$this->get($callback)->assertOk()->assertHeader('Referrer-Policy','no-referrer')->assertDontSee('src=',false)->assertSee('history.replaceState',false);
        if(getenv('VITA_PORTAL_BOOKING_PREVIEW')==='yes') {
            $directory=storage_path('framework/testing/portal-booking-preview');if(!is_dir($directory))mkdir($directory,0700,true);
            file_put_contents($directory.'/return.html',$landing->getContent());
        }
        $url=route('mi-salud.booking.show',['intent'=>$id]);
        $this->get($url)->assertRedirect(route('mi-salud.login'))->assertSessionHas('portal_booking_intent',$id);
        $this->withCookie('vita_mi_salud_session',session()->getId());
        $this->post(route('mi-salud.login.store'),['email'=>$a->email,'password'=>'SecurePassword123!'])->assertRedirect($url);
    }
}
