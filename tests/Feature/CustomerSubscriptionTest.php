<?php
namespace Tests\Feature;

use App\Models\{BillingPlan, BillingPlanPrice, Customer, CustomerSubscription, Role, User};
use App\Services\Billing\{PlanCatalogService, PlanVersionService, SubscriptionService};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerSubscriptionTest extends TestCase
{
    private User $admin;
    private Customer $customer;
    private BillingPlanPrice $price;
    private SubscriptionService $service;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp(); DB::beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-01-31 12:00:00', 'America/Santiago'));
        $key=Str::lower(Str::random(12));
        $this->admin=User::create(['name'=>'Subscription admin','email'=>$key.'@example.test','password'=>'test-password','rut'=>$key,'phone'=>'123','status'=>1]);
        $role=Role::firstOrCreate(['customer_id'=>null,'name'=>'Super Administrador']);
        $this->admin->roles()->attach($role->id);
        $this->customer=Customer::create(['name'=>'Customer '.$key,'legal_name'=>'Test customer','holder'=>'Holder','rut'=>$key,'phone'=>'123','email'=>$key.'@customer.test']);
        $plan=app(PlanCatalogService::class)->save($this->admin,['code'=>'sub_'.$key,'name'=>'Subscription plan','active'=>1,'position'=>1]);
        $versions=app(PlanVersionService::class); $v=$versions->create($this->admin,$plan);
        $versions->save($this->admin,$v,['included_branches'=>2,'trial_days'=>7,'grace_days'=>4,'terms'=>'Test terms',
            'prices'=>['month'=>['enabled'=>1,'amount'=>10000,'additional_branch_amount'=>2000],'year'=>['enabled'=>1,'amount'=>100000,'additional_branch_amount'=>20000]],
            'limits'=>['users'=>['unlimited'=>1,'scope'=>'customer'],'professionals'=>['unlimited'=>1,'scope'=>'branch'],'branches'=>['unlimited'=>0,'value'=>5,'scope'=>'customer']],
            'features'=>['appointments']]);
        $versions->publish($this->admin,$v);
        $this->price=$v->prices()->where('interval','month')->firstOrFail();
        $this->service=app(SubscriptionService::class);
        $this->root='http://'.config('app.domain').'/billing/subscriptions';
        $this->actingAs($this->admin);
    }
    protected function tearDown(): void { Carbon::setTestNow(); DB::rollBack(); parent::tearDown(); }
    public function test_general_conditions_are_versioned_and_restricted_to_superadmin(): void
    {
        $url='http://'.config('app.domain').'/billing/conditions';
        $revision=(int)DB::table('subscription_policy_versions')->max('id');
        $this->put($url,['title'=>'Test policy','body'=>'Version one <script>example</script>','revision'=>$revision])->assertRedirect()->assertSessionHasNoErrors();
        $id=(int)DB::table('subscription_policy_versions')->max('id');
        $this->assertGreaterThan($revision,$id);
        $this->get($url.'?_vita_content=1')->assertOk()->assertSee('Version one &lt;script&gt;example&lt;/script&gt;',false);
        $this->putJson($url,['title'=>'Test policy','body'=>'Stale','revision'=>$revision])->assertUnprocessable();
        $this->put($url,['title'=>'Test policy','body'=>'Version two','revision'=>$id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Version one <script>example</script>',DB::table('subscription_policy_versions')->where('id',$id)->value('body'));
        $this->admin->roles()->detach();
        $this->putJson($url,['title'=>'Denied','body'=>'Denied','revision'=>$id])->assertForbidden();
    }
    public static function customerCountries(): array
    {
        return [['CL'], ['AR']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('customerCountries')]
    public function test_customer_can_be_created_with_subscription_atomically(string $country): void
    {
        $name='Inline'.Str::lower(Str::random(10));
        $data=['operating_declaration_accepted'=>true,'name'=>$name,'legal_name'=>$name,'holder'=>'Holder','rut'=>'12.345.678-5','email'=>$name.'@example.test','phone'=>'123','status'=>1,
            'admin_name'=>'Clinic Admin','admin_rut'=>'12.345.678-5','admin_email'=>$name.'-admin@example.test','admin_phone'=>'123',
            'admin_password'=>'test-password-123','admin_password_confirmation'=>'test-password-123',
            'billing_plan_price_id'=>$this->price->id,'subscription_starts_on'=>'2026-01-31','subscription_branches'=>2,'subscription_payment_mode'=>'manual'];
        $url='http://'.config('app.domain').'/customers';
        if ($country !== 'CL') {
            unset($data['rut']);
            $data += ['identification_enabled' => true, 'identification_country' => $country,
                'identification_type' => 'tax_id', 'identification_holder' => 'legal_entity', 'identification_number' => '000TAX-1'];
        }
        $this->postJson($url,$data)->assertOk();
        $customer=Customer::where('name',$name)->firstOrFail();
        $this->assertSame($country === 'CL' ? '12.345.678-5' : null, $customer->rut);
        $s=CustomerSubscription::where('customer_id',$customer->id)->firstOrFail();
        $this->assertSame('manual',$s->payment_mode);
        $this->assertNull($s->paid_through_on);
        $this->assertSame(1,$customer->branches()->count());
        $data['name']='Invalid'.Str::lower(Str::random(10));
        $data['subscription_branches']=999;
        $this->postJson($url,$data)->assertUnprocessable();
        $this->assertDatabaseMissing('customers',['name'=>$data['name']]);
        $this->get('http://'.config('app.domain').'/superadmin/customers?_vita_content=1')->assertOk()->assertSee('Asignar plan (opcional)')->assertSee('Plan y suscripción');
    }
    public function test_commercial_classification_is_informational_and_does_not_authorize_flow(): void
    {
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $s=$this->subscription();
        $this->assertSame('automatic',$s->payment_mode);
        $this->assertSame('pending_acceptance',$s->contract_status);
        $labels=$this->service->classification($s);
        $this->assertSame('Pendiente de aceptación',$labels['contract_label']);
        $this->assertSame('Sin acuerdo Flow autorizado',$labels['authorization_label']);
        $this->assertSame('Sin restricción comercial (transición)',$labels['access_label']);
        $this->assertDatabaseMissing('flow_billing_agreements',['customer_subscription_id'=>$s->id]);
        $this->assertNull($s->paid_through_on);
        $this->get($this->root.'/'.$s->id.'?_vita_content=1')->assertOk()->assertSee('Situación comercial')->assertSee('Pendiente de aceptación');
        $this->getJson('http://'.config('app.domain').'/billing/subscriptions-data?draw=1&start=0&length=100')->assertOk()
            ->assertJsonFragment(['payment_mode_label'=>'Automática con Flow','contract_label'=>'Pendiente de aceptación']);
        $s->update(['payment_mode'=>null,'contract_status'=>'legacy_transition']);
        $labels=$this->service->classification($s->fresh());
        $this->assertSame('Pendiente de clasificar',$labels['payment_mode_label']);
        $this->assertSame('En transición',$labels['contract_label']);
        $this->assertSame('Sin restricción comercial (transición)',$labels['access_label']);
        $this->assertNull($s->fresh()->paid_through_on);
    }

    public function test_manual_mode_is_saved_and_invalid_mode_is_rejected(): void
    {
        $s=$this->subscription(['payment_mode'=>'manual']);
        $this->assertSame('Manual',$this->service->classification($s)['payment_mode_label']);
        $this->assertSame('manual',$s->payment_mode);
        $this->assertDatabaseHas('subscription_events',['customer_subscription_id'=>$s->id,'action'=>'created']);
        $this->postJson($this->root,['customer_id'=>$this->customer->id,'billing_plan_price_id'=>$this->price->id,
            'branch_quantity'=>1,'starts_on'=>'2026-01-31','use_trial'=>0,'payment_mode'=>'courtesy'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_mode');
        $this->assertSame(1,CustomerSubscription::where('customer_id',$this->customer->id)->count());
    }
    public function test_contracted_branch_capacity_enforces_creation_and_reactivation_per_customer(): void
    {
        $s=$this->subscription(['branch_quantity'=>2]);
        $url='http://'.config('app.domain').'/customers/'.$this->customer->id.'/branches';
        $this->post($url,['name'=>'Sede principal'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url,['name'=>'Segunda sede'])->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson($url,['name'=>'Fuera de contrato'])->assertUnprocessable()->assertJsonValidationErrors('branch_limit');
        $branches=$this->customer->branches()->orderBy('id')->get();
        $this->patchJson($url.'/'.$branches[1]->id.'/status',['status'=>false])->assertOk();
        $this->post($url,['name'=>'Reemplazo'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson($url.'/'.$branches[1]->id.'/status',['status'=>true])->assertUnprocessable()->assertJsonValidationErrors('branch_limit');
        $this->patchJson($url.'/'.$branches[0]->id.'/status',['status'=>true])->assertOk();
        $this->assertSame(2,$this->customer->branches()->where('status',true)->count());
        $this->assertSame(3,$this->customer->branches()->count());
        $this->get($url.'?_vita_content=1')->assertOk()->assertSee('2 contratadas');
        $other=Customer::create(['name'=>'Other','legal_name'=>'Other','holder'=>'Holder','rut'=>Str::random(12),'phone'=>'123','email'=>'other@example.test']);
        $otherBranch=$other->branches()->create(['name'=>'Other branch','status'=>false]);
        $this->patchJson($url.'/'.$otherBranch->id.'/status',['status'=>true])->assertNotFound();
        $this->assertFalse($otherBranch->fresh()->status);
        // An unpaid scheduled increase must not unlock additional seats.
        $this->service->scheduleChange($this->admin,$s,['revision'=>1,'billing_plan_price_id'=>$this->price->id,'branch_quantity'=>3,'reason'=>'Increase']);
        $this->postJson($url,['name'=>'Pending increase'])->assertUnprocessable();
        $this->service->recordPayment($this->admin,$s->fresh(),$this->payment($s->fresh()));
        $this->post($url,['name'=>'Paid increase'])->assertRedirect()->assertSessionHasNoErrors();
    }
    public function test_clients_without_subscription_remain_compatible_and_last_active_branch_is_preserved(): void
    {
        $service=app(\App\Services\Billing\BranchCapacityService::class);
        $first=$service->create($this->customer,['name'=>'Principal']);
        $second=$service->create($this->customer,['name'=>'Additional']);
        $service->setStatus($this->customer,$second->id,false);
        try { $service->setStatus($this->customer,$first->id,false); $this->fail('Keep last active branch'); }
        catch(\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('branch_limit',$e->errors()); }
        $this->assertTrue($first->fresh()->status);
    }
    private function data(array $overrides=[]): array
    {
        return array_replace(['customer_id'=>$this->customer->id,'billing_plan_price_id'=>$this->price->id,'branch_quantity'=>3,'starts_on'=>'2026-01-31','use_trial'=>0,'note'=>'Verified contract'], $overrides);
    }
    private function subscription(array $overrides=[]): CustomerSubscription { return $this->service->create($this->admin,$this->data($overrides)); }
    private function payment(CustomerSubscription $s, array $overrides=[]): array
    {
        return array_replace(['revision'=>$s->revision,'paid_on'=>Carbon::today('America/Santiago')->toDateString(),'amount'=>$this->service->upcoming($s)['amount'],'reference'=>Str::random(18),'note'=>'Bank transfer verified'], $overrides);
    }
    public function test_http_pages_single_subscription_and_customer_fields_are_preserved(): void
    {
        $before=$this->customer->fresh()->getAttributes();
        $this->get($this->root.'?_vita_content=1')->assertOk()->assertSee('Crear suscripción');
        $this->get($this->root.'/create?_vita_content=1')->assertOk()->assertSee('Inicio del primer período');
        $this->post($this->root,$this->data())->assertRedirect()->assertSessionHasNoErrors();
        $s=CustomerSubscription::where('customer_id',$this->customer->id)->firstOrFail();
        $this->get($this->root.'/'.$s->id.'?_vita_content=1')->assertOk()->assertSee('Registrar pago manual')->assertSee('12.000');
        $this->getJson('http://'.config('app.domain').'/billing/subscriptions-data?length=10&search[value]='.urlencode($this->customer->name))
            ->assertOk()->assertJsonPath('recordsFiltered',1)->assertJsonPath('data.0.customer_name',$this->customer->name);
        $this->postJson($this->root,$this->data())->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertSame($before,$this->customer->fresh()->getAttributes());
        $this->assertSame(0,$s->payments()->count());
    }
    public function test_preloaded_creation_modal_validates_and_saves_without_redirecting(): void
    {
        $this->get($this->root.'?_vita_content=1')->assertOk()
            ->assertSee('id="subscription-create-modal"',false)
            ->assertSee('data-open-subscription',false)
            ->assertSee('subscription-customer')
            ->assertSee('subscription-create-title');
        $this->postJson($this->root,$this->data(['starts_on'=>'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->assertFalse(CustomerSubscription::where('customer_id',$this->customer->id)->exists());
        $withoutNote=$this->data(); unset($withoutNote['note']);
        $this->postJson($this->root,$withoutNote)->assertCreated()
            ->assertJsonPath('customer_id',$this->customer->id)
            ->assertJsonPath('message','Suscripción creada correctamente.');
        $this->assertSame(1,CustomerSubscription::where('customer_id',$this->customer->id)->count());
        $this->postJson($this->root,$this->data())->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }
    public function test_payments_preserve_month_end_anchor_and_reject_amounts_replays_and_anticipation(): void
    {
        $s=$this->subscription();
        $this->postJson($this->root.'/'.$s->id.'/payments',$this->payment($s,['amount'=>1]))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $data=$this->payment($s,['reference'=>'unique-reference']);
        $this->post($this->root.'/'.$s->id.'/payments',$data)->assertRedirect()->assertSessionHasNoErrors();
        $s->refresh(); $this->assertSame('2026-02-28',$s->paid_through_on->toDateString());
        $this->assertSame('active',$this->service->state($s));
        $this->postJson($this->root.'/'.$s->id.'/payments',$data)->assertUnprocessable()->assertJsonValidationErrors('subscription');
        $this->postJson($this->root.'/'.$s->id.'/payments',$this->payment($s))->assertUnprocessable()->assertJsonValidationErrors('paid_on');
        Carbon::setTestNow(Carbon::parse('2026-02-28 12:00:00','America/Santiago'));
        $this->postJson($this->root.'/'.$s->id.'/payments',$this->payment($s,['reference'=>'unique-reference']))->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->post($this->root.'/'.$s->id.'/payments',$this->payment($s))->assertRedirect()->assertSessionHasNoErrors();
        $s->refresh(); $this->assertSame('2026-03-31',$s->paid_through_on->toDateString());
        $this->assertSame(2,$s->payments()->count());
        $this->assertSame(3,$s->events()->count());
    }
    public function test_trial_grace_overdue_and_annual_leap_year(): void
    {
        $s=$this->subscription(['use_trial'=>1]);
        $this->assertSame('2026-02-07',$s->next_renewal_on->toDateString());
        $this->assertSame('trial',$this->service->state($s));
        Carbon::setTestNow(Carbon::parse('2026-02-07 12:00:00','America/Santiago'));
        $this->assertSame('grace',$this->service->state($s));
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00','America/Santiago'));
        $this->assertSame('overdue',$this->service->state($s));
        $s->anchor_day=29; $s->anchor_month=2;
        $this->assertSame('2025-02-28',$this->service->nextEnd($s,Carbon::parse('2024-02-29'),'year')->toDateString());
        $this->assertSame('2028-02-29',$this->service->nextEnd($s,Carbon::parse('2027-02-28'),'year')->toDateString());
    }
    public function test_plan_and_branch_changes_apply_only_to_next_paid_period_and_keep_history(): void
    {
        $s=$this->subscription(); $this->service->recordPayment($this->admin,$s,$this->payment($s)); $s->refresh();
        $first=$s->payments()->first()->getAttributes();
        $this->post($this->root.'/'.$s->id.'/changes',['revision'=>$s->revision,'billing_plan_price_id'=>$this->price->id,'branch_quantity'=>5,'reason'=>'Two additional branches'])->assertRedirect()->assertSessionHasNoErrors();
        $s->refresh(); $this->assertSame(3,(int)$s->branch_quantity);
        $this->assertSame(16000,$this->service->upcoming($s)['amount']);
        $this->postJson($this->root.'/'.$s->id.'/changes',['revision'=>$s->revision,'billing_plan_price_id'=>$this->price->id,'branch_quantity'=>4,'reason'=>'Duplicate'])->assertUnprocessable();
        $this->get($this->root.'/'.$s->id.'?_vita_content=1')->assertOk()->assertSee('Cambio programado');
        Carbon::setTestNow(Carbon::parse('2026-02-28 12:00:00','America/Santiago'));
        $this->service->recordPayment($this->admin,$s,$this->payment($s)); $s->refresh();
        $this->assertSame(5,(int)$s->branch_quantity);
        $this->assertNull($s->pendingChange);
        $this->assertSame('applied',$s->changes()->first()->status);
        $this->assertSame($first,$s->payments()->orderBy('id')->first()->getAttributes());
        $this->assertSame(12000,(int)$s->payments()->orderBy('id')->first()->amount);
    }
    public function test_cancellation_preserves_paid_period_and_blocks_new_renewals(): void
    {
        $s=$this->subscription(); $this->service->recordPayment($this->admin,$s,$this->payment($s)); $s->refresh();
        $this->post($this->root.'/'.$s->id.'/cancel',['revision'=>$s->revision,'reason'=>'Client request','confirmation'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $s->refresh(); $this->assertSame('ending',$this->service->state($s));
        $this->get($this->root.'/'.$s->id.'?_vita_content=1')->assertOk()->assertDontSee('Registrar pago manual');
        $this->assertSame('2026-02-28',$s->paid_through_on->toDateString());
        $this->assertSame('2026-02-28',$s->access_ends_on->toDateString());
        $this->postJson($this->root.'/'.$s->id.'/payments',$this->payment($s))->assertStatus(409);
        Carbon::setTestNow(Carbon::parse('2026-02-28 12:00:00','America/Santiago'));
        $this->assertSame('cancelled',$this->service->state($s));
        $this->assertSame(1,$s->payments()->count());
    }
    public function test_payment_and_audit_history_are_immutable_in_mysql(): void
    {
        $s=$this->subscription(); $this->service->recordPayment($this->admin,$s,$this->payment($s));
        foreach(['subscription_payments','subscription_events'] as $table) {
            $row=DB::table($table)->where('customer_subscription_id',$s->id)->first();
            foreach(['update','delete'] as $operation) {
                try {
                    $q=DB::table($table)->where('id',$row->id);
                    $operation==='update' ? $q->update(['created_at'=>now()]) : $q->delete();
                    $this->fail('Financial history must be immutable');
                } catch(\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('append only',$e->getMessage()); }
            }
        }
    }
    public function test_invalid_branches_cycle_changes_and_tenant_access_are_rejected(): void
    {
        $this->postJson($this->root,$this->data(['branch_quantity'=>6]))->assertUnprocessable()->assertJsonValidationErrors('branch_quantity');
        $s=$this->subscription();
        $annual=$this->price->version->prices()->where('interval','year')->first();
        $this->postJson($this->root.'/'.$s->id.'/changes',['revision'=>$s->revision,'billing_plan_price_id'=>$annual->id,'branch_quantity'=>3,'reason'=>'Cycle change'])->assertUnprocessable();
        $this->admin->customer_id=$this->customer->id; $this->admin->save();
        $this->get($this->root)->assertForbidden();
        $this->postJson($this->root.'/'.$s->id.'/payments',$this->payment($s))->assertForbidden();
        $this->postJson($this->root.'/'.$s->id.'/changes',['revision'=>1])->assertForbidden();
        $this->postJson($this->root.'/'.$s->id.'/cancel',['revision'=>1])->assertForbidden();
        $this->deleteJson($this->root.'/'.$s->id.'/pending-change',['revision'=>1])->assertForbidden();
    }
    public function test_audit_failure_rolls_back_payment_and_period_change(): void
    {
        $s=$this->subscription();
        $dispatcher=\App\Models\SubscriptionEvent::getEventDispatcher();
        \App\Models\SubscriptionEvent::setEventDispatcher(clone $dispatcher);
        \App\Models\SubscriptionEvent::creating(function(){ throw new \RuntimeException('Simulated audit failure'); });
        try { $this->service->recordPayment($this->admin,$s,$this->payment($s)); $this->fail('Expected rollback'); }
        catch(\RuntimeException $e) { $this->assertSame('Simulated audit failure',$e->getMessage()); }
        finally { \App\Models\SubscriptionEvent::setEventDispatcher($dispatcher); }
        $this->assertSame(0,$s->payments()->count());
        $this->assertNull($s->fresh()->paid_through_on);
        $this->assertSame(1,(int)$s->fresh()->revision);
    }
    public function test_publishing_new_prices_does_not_change_existing_contract_or_old_payments(): void
    {
        $s=$this->subscription(); $oldVersion=$this->price->version;
        $versions=app(PlanVersionService::class);
        $v=$versions->create($this->admin,$oldVersion->plan,$oldVersion);
        $data=$versions->definition($oldVersion); $data['prices']['month']['amount']=50000;
        $versions->save($this->admin,$v,$data); $versions->publish($this->admin,$v);
        $s->refresh(); $this->assertSame(12000,$this->service->upcoming($s)['amount']);
        $this->service->recordPayment($this->admin,$s,$this->payment($s));
        $this->assertSame(12000,(int)$s->payments()->first()->amount);
        $oldVersion->plan->delete();
        $this->get($this->root.'/'.$s->id.'?_vita_content=1')->assertOk()->assertSee('Subscription plan');
        $this->assertSame(12000,$this->service->upcoming($s->fresh())['amount']);
    }
    public function test_pending_change_cancellation_and_payment_customer_ownership_are_enforced(): void
    {
        $s=$this->subscription();
        $this->service->scheduleChange($this->admin,$s,['revision'=>1,'billing_plan_price_id'=>$this->price->id,'branch_quantity'=>4,'reason'=>'Change']);
        $s->refresh();
        $this->delete($this->root.'/'.$s->id.'/pending-change',['revision'=>$s->revision])->assertRedirect()->assertSessionHasNoErrors();
        $s->refresh(); $this->assertNull($s->pendingChange);
        $this->assertSame('cancelled',$s->changes()->first()->status);
        $this->assertSame(12000,$this->service->upcoming($s)['amount']);
        $this->service->recordPayment($this->admin,$s,$this->payment($s));
        $row=$s->payments()->first()->getAttributes(); unset($row['id']);
        $row['customer_id']=$this->customer->id+999999;
        $row['period_start']='2026-02-28'; $row['period_end']='2026-03-31'; $row['reference']='invalid-owner';
        try { DB::table('subscription_payments')->insert($row); $this->fail('Payment must belong to subscription customer'); }
        catch(\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('ownership',$e->getMessage()); }
    }
}
