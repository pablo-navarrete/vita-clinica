<?php
namespace Tests\Feature;

use App\Models\{Role,Permission,Patient};
use Illuminate\Support\Facades\{DB,Mail,Notification};
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class AgendaPatientCreationTest extends TestCase
{
    use \Tests\Support\SigningFixtures {setUp as fixturesSetUp;tearDown as fixturesTearDown;}
    private bool $started=false;
    protected function setUp(): void {
        if(getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes'||!preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',getenv('DB_DATABASE')?:''))$this->markTestSkipped('Isolated MySQL only.');
        $this->fixturesSetUp();$this->started=true;
        config(['identification'=>IdentifierStorageKeys::config()+['application_key'=>config('app.key')],'cache.default'=>'array']);Mail::fake();Notification::fake();
        $this->actingAs($this->user('Administrador'));
    }
    protected function tearDown(): void {if($this->started)$this->fixturesTearDown();}
    public function test_agenda_reuses_one_patient_form_and_existing_patient_selector(): void {
        $response=$this->get($this->root.'/appointments?_vita_content=1')->assertOk()->assertSee('agenda-create-patient')->assertSee('appointmentPatient')->assertSee('patient-portal-autofill.js')->assertSee('agenda-patient-form.js');
        $this->assertSame(1,substr_count($response->getContent(),'id="patient-modal"'));
        $this->assertSame(1,substr_count($response->getContent(),'id="patient-name"'));
        $response->assertSee($this->patient->name);
    }
    public function test_create_permission_is_required_even_if_appointment_creation_is_allowed(): void {
        $actor=$this->user('AgendaOnly');$role=Role::where('customer_id',$this->clinic->id)->where('name','AgendaOnly')->firstOrFail();
        $role->update(['permissions_configured'=>true]);
        foreach(['appointments.view','appointments.create']as$slug){$p=Permission::firstOrCreate(['slug'=>$slug],['name'=>$slug,'module'=>'Agenda']);$role->permissions()->syncWithoutDetaching([$p->id]);}
        $this->actingAs($actor)->get($this->root.'/appointments?_vita_content=1')->assertOk()->assertDontSee('id="agenda-create-patient"',false)->assertDontSee('id="patient-modal"',false);
        $this->postJson($this->root.'/patient',$this->data())->assertForbidden();
    }
    private function data(): array {return ['name'=>'Agenda new patient','email'=>'agenda@example.test','phone'=>'912345678','gender'=>'Otro','birth_date'=>'1990-01-01','address'=>'Chile',
        'identification_country'=>'AR','identification_type'=>'passport','identification_number'=>'000'.Str::upper(Str::random(12)),'registration_operation'=>(string)Str::uuid()];}
    public function test_patient_registration_does_not_create_an_appointment_and_is_idempotent(): void {
        $before=DB::table('appointments')->count();$data=$this->data();
        $id=$this->postJson($this->root.'/patient',$data)->assertOk()->json('data.id');
        $this->assertSame($before,DB::table('appointments')->count());
        $this->assertSame($id,$this->postJson($this->root.'/patient',$data)->assertOk()->json('data.id'));
        $this->assertSame($this->clinic->id,Patient::findOrFail($id)->customer_id);
        $this->assertNotNull(Patient::findOrFail($id)->medicalRecord);
        $data['registration_operation']=(string)Str::uuid();$this->postJson($this->root.'/patient',$data)->assertUnprocessable();
    }
    public function test_submitted_clinic_and_branch_do_not_override_authorized_context(): void {
        $data=$this->data()+['customer_id'=>$this->clinic->id+999,'clinic_branch_id'=>$this->branch->id+999];
        $id=$this->postJson($this->root.'/patient',$data)->assertOk()->json('data.id');
        $this->assertDatabaseHas('patients',['id'=>$id,'customer_id'=>$this->clinic->id,'clinic_branch_id'=>$this->branch->id]);
    }
    public function test_saving_appointment_rechecks_permission_after_patient_creation(): void {
        $id=$this->postJson($this->root.'/patient',$this->data())->assertOk()->json('data.id');
        $role=Role::where('customer_id',$this->clinic->id)->where('name','Administrador')->firstOrFail();
        $role->update(['permissions_configured'=>true]);$role->permissions()->detach();
        $this->postJson($this->root.'/appointments',['patient_id'=>$id,'clinic_branch_id'=>$this->branch->id,'professional_id'=>$this->doctor->id,
            'date'=>now()->addDays(14)->toDateString(),'time'=>'10:00','modality'=>'in_person'])->assertForbidden();
    }
    public function test_internal_booking_serializes_two_real_mysql_processes_for_last_slot(): void {
        app(\App\Services\SpecialtyCatalog::class)->sync($this->doctor,$this->clinic->id,$this->branch->id,[-1089]);
        $specialty=\App\Models\Specialty::where('customer_id',$this->clinic->id)->where('catalog_id',1089)->value('id');
        $date=now()->addDays(14)->startOfDay();
        \App\Models\ProfessionalSchedule::create(['customer_id'=>$this->clinic->id,'clinic_branch_id'=>$this->branch->id,'user_id'=>$this->doctor->id,'day_of_week'=>$date->dayOfWeek,'start_time'=>'09:00:00','end_time'=>'11:00:00']);
        $actor=auth()->user();DB::commit();
        DB::beginTransaction();DB::table('users')->where('id',$this->doctor->id)->lockForUpdate()->first();$workers=[];
        try{
            foreach([1,2]as$n){
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/agenda-booking-worker.php'),(string)$this->clinic->id,(string)$this->branch->id,(string)$actor->id,(string)$this->doctor->id,(string)$this->patient->id,(string)$specialty,$date->toDateString()],base_path());
                $process->setTimeout(30);$process->start();$workers[]=$process;
            }
            $deadline=microtime(true)+12;$waiting=0;
            while($waiting<2&&microtime(true)<$deadline){
                $waiting=(int)DB::selectOne('SELECT COUNT(DISTINCT w.REQUESTING_ENGINE_TRANSACTION_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE l.OBJECT_SCHEMA=? AND l.OBJECT_NAME=?',[DB::connection()->getDatabaseName(),'users'])->n;
                if($waiting<2)usleep(10000);
            }
            $this->assertSame(2,$waiting,'Both booking processes must wait for the same professional row.');DB::commit();
            $codes=[];foreach($workers as$worker){$worker->wait();$this->assertTrue($worker->isSuccessful());$codes[]=(int)$worker->getOutput();}
            sort($codes);$this->assertSame([200,422],$codes);
            $this->assertSame(1,DB::table('appointments')->where('customer_id',$this->clinic->id)->where('professional_id',$this->doctor->id)->count());
        }finally{if(DB::transactionLevel())DB::rollBack();foreach($workers as$worker)if($worker->isRunning())$worker->stop();}
    }
}
