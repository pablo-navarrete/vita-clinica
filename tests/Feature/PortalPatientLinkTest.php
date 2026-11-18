<?php

namespace Tests\Feature;

use App\Models\{Customer,Patient,PortalAccount};
use App\Services\MiSalud\{PortalAccountService,PortalPatientLinkService};
use Illuminate\Support\Facades\{DB,Mail};
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class PortalPatientLinkTest extends TestCase
{
    private string $rut;
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',DB::connection()->getDatabaseName())) $this->markTestSkipped('Isolated MySQL only.');
        config(['identification'=>IdentifierStorageKeys::config()+['application_key'=>config('app.key')]]);
        DB::beginTransaction(); Mail::fake();
        $body=(string)random_int(20000000,29000000); $sum=0; $factor=2;
        foreach (array_reverse(str_split($body)) as $digit) { $sum+=(int)$digit*$factor; $factor=$factor===7?2:$factor+1; }
        $dv=11-$sum%11; $this->rut=$body.($dv===11?'0':($dv===10?'K':(string)$dv));
    }
    protected function tearDown(): void { if (DB::transactionLevel()) DB::rollBack(); parent::tearDown(); }
    private function patient(?string $rut=null, ?Customer $clinic=null): Patient
    {
        $uid=Str::lower(Str::random(14));
        $clinic??=Customer::create(['name'=>'Link test','legal_name'=>'Link test','domain'=>'link-'.$uid,'holder'=>'Test','rut'=>$uid,'phone'=>'123','email'=>$uid.'@example.test','status'=>1]);
        return Patient::create(['customer_id'=>$clinic->id,'name'=>'Local patient','rut'=>$rut??$this->rut,'gender'=>'Otro','birth_date'=>'1990-01-01','status'=>1]);
    }
    private function account(?string $number=null,string $country='CL'): PortalAccount
    {
        $email=Str::uuid().'@example.test';
        app(PortalAccountService::class)->register(['name'=>'Portal test','email'=>$email,'password'=>'SecurePassword123!',
            'identification_country'=>$country,'identification_type'=>$country==='CL'?'national_id':'passport','identification_number'=>$number??$this->rut]);
        return PortalAccount::where('email',$email)->firstOrFail();
    }
    private function sync(): void { while (app(PortalPatientLinkService::class)->drain(100)) {} }
    public function test_patient_first_and_account_first_match_without_changing_sources(): void
    {
        $patient=$this->patient(); $account=$this->account(); $other=$this->patient(substr($this->rut,0,-1).'-'.substr($this->rut,-1));
        $before=DB::table('patients')->whereIn('id',[$patient->id,$other->id])->orderBy('id')->get()->toJson();
        $this->sync();
        $links=DB::table('portal_patient_links')->where('portal_account_id',$account->id)->get();
        $this->assertCount(2,$links); $this->assertSame(['candidate'],$links->pluck('status')->unique()->values()->all());
        $this->assertSame([0],$links->pluck('clinical_access')->unique()->values()->all());
        $this->assertSame($before,DB::table('patients')->whereIn('id',[$patient->id,$other->id])->orderBy('id')->get()->toJson());
        $count=DB::table('portal_patient_link_events')->count();
        app(PortalPatientLinkService::class)->request('account',$account->id); $this->sync();
        $this->assertSame($count,DB::table('portal_patient_link_events')->count());
    }
    public function test_ambiguous_accounts_are_not_chosen_and_changes_preserve_history(): void
    {
        $patient=$this->patient();$a=$this->account();$this->sync();$b=$this->account();$this->sync();
        $links=DB::table('portal_patient_links')->where('patient_id',$patient->id)->get();
        $this->assertCount(2,$links);$this->assertSame(['review_required'],$links->pluck('status')->unique()->values()->all());
        $a->active=false;$a->save();$this->sync();
        $this->assertSame('revoked',DB::table('portal_patient_links')->where('portal_account_id',$a->id)->value('status'));
        $this->assertSame('candidate',DB::table('portal_patient_links')->where('portal_account_id',$b->id)->value('status'));
        $this->assertGreaterThan(2,DB::table('portal_patient_link_events')->count());
    }
    public function test_inconsistent_legacy_and_foreign_documents_do_not_create_false_links(): void
    {
        $patient=$this->patient('not-a-rut');$this->account('000AbCd77','AR');$this->sync();
        $this->assertFalse(DB::table('portal_patient_links')->where('patient_id',$patient->id)->exists());
        $this->assertFalse(DB::table('portal_document_lookup_keys')->where('patient_id',$patient->id)->exists());
        foreach (DB::table('portal_document_lookup_keys')->get() as $key) $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$key->document_hash);
    }
    public function test_transaction_rollback_leaves_no_link_request(): void
    {
        $count=DB::table('portal_link_sync_requests')->count();DB::beginTransaction();$this->patient();DB::rollBack();
        $this->assertSame($count,DB::table('portal_link_sync_requests')->count());
    }
    public function test_tenant_trigger_rejects_wrong_clinic(): void
    {
        $p=$this->patient();$other=$this->patient('123456785');$a=$this->account();$this->sync();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('portal_patient_links')->where('patient_id',$p->id)->update(['customer_id'=>$other->customer_id]);
    }
    public function test_link_events_cannot_be_rewritten(): void
    {
        $this->patient();$this->account();$this->sync();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('portal_patient_link_events')->update(['status'=>'revoked']);
    }
    public function test_key_rotation_keeps_matches_without_duplicate_links(): void
    {
        $patient=$this->patient();$account=$this->account();$this->sync();
        $id=DB::table('portal_patient_links')->where('patient_id',$patient->id)->value('id');
        config(['identification'=>IdentifierStorageKeys::config('v2')+['application_key'=>config('app.key')]]);
        app(PortalPatientLinkService::class)->request('account',$account->id);
        app(PortalPatientLinkService::class)->request('patient',$patient->id);$this->sync();
        $rows=DB::table('portal_patient_links')->where('patient_id',$patient->id)->get();
        $this->assertCount(1,$rows);$this->assertSame($id,$rows[0]->id);$this->assertSame('candidate',$rows[0]->status);
    }
    public function test_database_forbids_clinical_access_from_documentary_links(): void
    {
        $patient=$this->patient();$this->account();$this->sync();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('portal_patient_links')->where('patient_id',$patient->id)->update(['clinical_access'=>true]);
    }
    public function test_rollback_refuses_to_destroy_link_history(): void
    {
        $this->patient();$this->account();$this->sync();
        $migration=require database_path('migrations/2026_11_08_000100_create_portal_patient_linking.php');
        $this->expectException(\RuntimeException::class);$migration->down();
    }
    public function test_real_mysql_processes_do_not_pick_one_of_concurrent_accounts(): void
    {
        $patient=$this->patient();DB::commit();
        $workers=[];
        foreach ([1,2] as $n) {
            $worker=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/portal-link-worker.php')],base_path(),[
                'VITA_LINK_TEST_RUT'=>$this->rut,'VITA_LINK_TEST_EMAIL'=>Str::uuid().'@example.test']);
            $worker->setTimeout(60);$worker->start();$workers[]=$worker;
        }
        foreach ($workers as $worker) { $worker->wait();$this->assertTrue($worker->isSuccessful(),'Isolated concurrency worker failed.'); }
        $rows=DB::table('portal_patient_links')->where('patient_id',$patient->id)->get();
        $this->assertCount(2,$rows);
        $this->assertSame(['review_required'],$rows->pluck('status')->unique()->values()->all());
        $this->assertSame([0],$rows->pluck('clinical_access')->unique()->values()->all());
    }
}
