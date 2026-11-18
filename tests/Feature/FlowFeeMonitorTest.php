<?php
namespace Tests\Feature;
use App\Models\{FlowPaymentObservation,PaymentFeeAlert,PaymentFeeRate,Role,User};
use App\Services\Billing\{FlowFeeMonitor,FlowReadClient,PaymentFeeService};
use Illuminate\Support\{Carbon,Str};
use Illuminate\Support\Facades\{DB,Http,Notification};
use Tests\TestCase;
class FlowFeeMonitorTest extends TestCase
{
    private User $admin;
    private string $date;
    private PaymentFeeRate $rate;
    protected function setUp(): void
    {
        parent::setUp(); DB::beginTransaction();
        $last=PaymentFeeRate::max('effective_from');
        $date=$last?Carbon::parse($last)->addDay():Carbon::today('America/Santiago');
        if($date->lt(Carbon::today('America/Santiago'))) $date=Carbon::today('America/Santiago');
        Carbon::setTestNow($date->setTime(12,0)); $this->date=now('America/Santiago')->toDateString();
        $id=Str::random(12);
        $this->admin=User::create(['name'=>'Flow test','email'=>$id.'@example.test','password'=>'test-password','rut'=>$id,'phone'=>'123','status'=>1]);
        $role=Role::firstOrCreate(['customer_id'=>null,'name'=>'Super Administrador']); $this->admin->roles()->attach($role->id);
        $this->rate=app(PaymentFeeService::class)->publish($this->admin,['scenario'=>'Flow test tariff','effective_from'=>$this->date,
            'rate_percent'=>'2.89','vat_percent'=>'19','fixed_amount'=>0,'note'=>'Test fixture']);
        config(['flow.enabled'=>true,'flow.environment'=>'sandbox','flow.api_key'=>'fixture-key-'.$id,'flow.secret_key'=>'fixture-secret','flow.alert_email'=>'finance@example.test']);
        Http::preventStrayRequests(); $this->actingAs($this->admin);
    }
    protected function tearDown(): void { Carbon::setTestNow(); DB::rollBack(); parent::tearDown(); }
    private function payment(int $net=96561): array
    {
        return ['flowOrder'=>123456,'commerceOrder'=>'order-123','status'=>2,'currency'=>'CLP','amount'=>100000,'payer'=>'do-not-store@example.test',
            'paymentData'=>['date'=>$this->date.' 10:00:00','media'=>'webpay','amount'=>100000,'currency'=>'CLP','fee'=>3439,'balance'=>$net,'transferDate'=>$this->date,'cardNumber'=>'NEVER-STORE']];
    }
    private function fake(array $payments): void { Http::fake(['https://sandbox.flow.cl/api/*'=>Http::response(['total'=>count($payments),'hasMore'=>0,'data'=>$payments])]); }
    public function test_signed_readonly_query_records_real_values_idempotently_without_customer_mutation(): void
    {
        $this->fake([$this->payment()]);
        $service=app(FlowFeeMonitor::class); $this->assertSame(1,$service->sync($this->date)); $service->sync($this->date);
        $q=FlowPaymentObservation::where('account_hash',app(FlowReadClient::class)->accountHash());
        $this->assertSame(1,$q->count()); $row=$q->first();
        $this->assertSame(96561,$row->net); $this->assertSame(3439,$row->reported_fee);
        $this->assertStringNotContainsString('do-not-store',json_encode($row));
        $this->assertStringNotContainsString('NEVER-STORE',json_encode($row));
        $this->assertSame(0,PaymentFeeAlert::where('observation_id',$row->id)->count());
        Http::assertSent(function($request){
            $params=$request->data(); $signature=$params['s']; unset($params['s']); ksort($params);
            $plain=''; foreach($params as $key=>$value) $plain.=$key.$value;
            return $request->method()==='GET' && hash_equals($signature,hash_hmac('sha256',$plain,'fixture-secret'));
        });
        $this->get('http://'.config('app.domain').'/billing/fees?_vita_content=1')->assertOk()->assertSee('96.561')->assertSee('sandbox');
        $this->assertSame(0,DB::table('subscription_payments')->where('reference','order-123')->count());
    }
    public function test_deviation_alerts_email_and_acknowledgement_do_not_change_plan_prices(): void
    {
        $this->fake([$this->payment(96000)]); $service=app(FlowFeeMonitor::class);
        $service->sync($this->date); $service->sync($this->date);
        $alert=PaymentFeeAlert::where('account_hash',app(FlowReadClient::class)->accountHash())->firstOrFail();
        $this->assertSame(1,PaymentFeeAlert::where('account_hash',$alert->account_hash)->count());
        $this->assertSame(4000,$alert->details['observed_deduction']);
        Notification::fake();
        $this->artisan('flow:send-fee-alerts')->assertSuccessful();
        Notification::assertSentOnDemand(\App\Notifications\PaymentFeeReview::class);
        $this->assertNotNull($alert->fresh()->emailed_at);
        Notification::fake(); $this->artisan('flow:send-fee-alerts')->assertSuccessful(); Notification::assertNothingSent();
        $this->patch('http://'.config('app.domain').'/billing/fees/alerts/'.$alert->id)->assertRedirect();
        $this->assertNotNull($alert->fresh()->acknowledged_at);
        $this->assertSame(28900,$this->rate->fresh()->rate_units);
    }
    public function test_invalid_missing_and_foreign_currency_data_are_not_treated_as_real_fees(): void
    {
        $p=$this->payment(); unset($p['paymentData']['fee']); $this->fake([$p]);
        try { app(FlowFeeMonitor::class)->sync($this->date); $this->fail('Missing fee must not become zero'); }
        catch(\RuntimeException $e) { $this->assertStringContainsString('Importe CLP',$e->getMessage()); }
        $this->assertSame(0,FlowPaymentObservation::where('account_hash',app(FlowReadClient::class)->accountHash())->count());
        $p=$this->payment(); $p['currency']='USD'; $this->fake([$p]);
        try { app(FlowFeeMonitor::class)->sync($this->date); $this->fail('Reject currency conversion'); }
        catch(\RuntimeException $e) { $this->assertStringContainsString('CLP',$e->getMessage()); }
    }
    public function test_unconfigured_client_never_calls_network_and_provider_error_does_not_leak_keys(): void
    {
        Http::fake(); config(['flow.enabled'=>false]);
        $this->assertFalse(app(FlowReadClient::class)->configured());
        $this->artisan('flow:sync-fees')->assertSuccessful(); Http::assertNothingSent();
        config(['flow.enabled'=>true]);
        Http::fake(['*'=>Http::response(['message'=>'fixture-secret'],401)]);
        try { app(FlowFeeMonitor::class)->sync($this->date); $this->fail('Expected sanitized failure'); }
        catch(\RuntimeException $e) { $this->assertStringNotContainsString('fixture-secret',$e->getMessage()); }
    }
    public function test_separate_accounts_and_environments_and_pagination(): void
    {
        Http::fakeSequence()->push(['data'=>[$this->payment()],'hasMore'=>1,'total'=>2])->push(['data'=>[$this->payment()],'hasMore'=>0,'total'=>2]);
        $this->assertSame(2,app(FlowFeeMonitor::class)->sync($this->date));
        $this->assertSame(1,FlowPaymentObservation::where('account_hash',app(FlowReadClient::class)->accountHash())->count());
        config(['flow.api_key'=>'different-account']);
        $this->get('http://'.config('app.domain').'/billing/fees?_vita_content=1')->assertOk()->assertDontSee('#123456');
        config(['flow.enabled'=>true,'flow.environment'=>'production']);
        $this->get('http://'.config('app.domain').'/billing/fees?_vita_content=1')->assertOk()->assertDontSee('#123456');
    }
}
