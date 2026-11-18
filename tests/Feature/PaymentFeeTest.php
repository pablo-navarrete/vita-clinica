<?php
namespace Tests\Feature;

use App\Models\{PaymentFeeRate,Role,User};
use App\Services\Billing\PaymentFeeService;
use Illuminate\Support\{Carbon,Str};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentFeeTest extends TestCase
{
    private User $admin;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp(); DB::beginTransaction();
        $last=PaymentFeeRate::max('effective_from');
        $date=$last ? Carbon::parse($last)->addDay() : Carbon::today('America/Santiago');
        if($date->lt(Carbon::today('America/Santiago'))) $date=Carbon::today('America/Santiago');
        Carbon::setTestNow($date->setTime(12,0));
        $key=Str::random(12);
        $this->admin=User::create(['name'=>'Fee admin','email'=>$key.'@example.test','password'=>'test-password','rut'=>$key,'phone'=>'123','status'=>1]);
        $role=Role::firstOrCreate(['customer_id'=>null,'name'=>'Super Administrador']); $this->admin->roles()->attach($role->id);
        $this->root='http://'.config('app.domain').'/billing/fees'; $this->actingAs($this->admin);
    }
    protected function tearDown(): void { Carbon::setTestNow(); DB::rollBack(); parent::tearDown(); }
    private function data(): array
    {
        return ['scenario'=>'Test automatic card collection','effective_from'=>now('America/Santiago')->toDateString(),
            'rate_percent'=>'2.8900','fixed_amount'=>0,'vat_percent'=>'19','note'=>'Test-only negotiated tariff'];
    }
    public function test_rates_are_configurable_effective_dated_and_visible_on_plan_forms(): void
    {
        $this->get($this->root.'?_vita_content=1')->assertOk()->assertSee('Comisiones de pago');
        $this->post($this->root,$this->data())->assertRedirect($this->root)->assertSessionHasNoErrors();
        $service=app(PaymentFeeService::class); $current=$service->current();
        $this->assertSame(28900,$current->rate_units);
        $this->assertSame(['gross'=>100000,'commission'=>2890,'vat'=>549,'deduction'=>3439,'net'=>96561],$service->estimate(100000,$current));
        $next=$this->data(); $next['effective_from']=now('America/Santiago')->addDay()->toDateString();
        $next['rate_percent']='3.19'; $next['fixed_amount']=100;
        $this->post($this->root,$next)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($current->id,$service->current()->id);
        $this->get('http://'.config('app.domain').'/billing/plans/create?_vita_content=1')->assertOk()
            ->assertSee('Quiero recibir después de Flow')->assertSee('Guardar porcentajes');
        Carbon::setTestNow(Carbon::now()->addDay());
        $new=$service->current(); $this->assertNotSame($current->id,$new->id);
        $this->assertSame(31900,$new->rate_units);
        $this->assertSame(3290,$service->estimate(100000,$new)['commission']);
        $this->assertSame(190000,$current->fresh()->vat_units);
    }
    public function test_simulator_defaults_can_be_saved_updated_and_reloaded_without_changing_tariffs(): void
    {
        $url = 'http://'.config('app.domain').'/billing/fee-defaults';
        $count = PaymentFeeRate::count();
        $this->putJson($url, ['rate_percent' => '2.89', 'vat_percent' => '19'])->assertOk();
        $settings = app(PaymentFeeService::class)->simulatorDefaults();
        $this->assertSame(28900, (int) $settings->simulator_rate_units);
        $this->assertSame(190000, (int) $settings->simulator_vat_units);
        $this->assertSame($this->admin->id, (int) $settings->simulator_updated_by);
        $this->get('http://'.config('app.domain').'/billing/plans/create?_vita_content=1')->assertOk()
            ->assertSee('value="2.89"', false)->assertSee('value="19"', false);
        $this->putJson($url, ['rate_percent' => '3.19', 'vat_percent' => '0'])->assertOk();
        $this->assertSame(31900, (int) app(PaymentFeeService::class)->simulatorDefaults()->simulator_rate_units);
        $this->assertSame($count, PaymentFeeRate::count());
        $this->putJson($url, ['rate_percent' => '-1', 'vat_percent' => '19'])->assertUnprocessable();
        $this->assertSame(31900, (int) app(PaymentFeeService::class)->simulatorDefaults()->simulator_rate_units);
        $this->admin->roles()->detach();
        $this->putJson($url, ['rate_percent' => '2', 'vat_percent' => '19'])->assertForbidden();
    }

    public function test_validation_and_global_superadmin_access(): void
    {
        $data=$this->data(); $data['rate_percent']='100.0001';
        $this->postJson($this->root,$data)->assertUnprocessable()->assertJsonValidationErrors('rate_percent');
        $data=$this->data(); $data['vat_percent']='1.00001';
        $this->postJson($this->root,$data)->assertUnprocessable()->assertJsonValidationErrors('vat_percent');
        $data=$this->data(); $data['fixed_amount']=-1;
        $this->postJson($this->root,$data)->assertUnprocessable()->assertJsonValidationErrors('fixed_amount');
        $this->post($this->root,$this->data())->assertSessionHasNoErrors();
        $this->postJson($this->root,$this->data())->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->admin->roles()->detach();
        $this->get($this->root)->assertForbidden();
        $this->actingAs($this->admin);
        $this->postJson($this->root,$this->data())->assertForbidden();
    }
    public function test_published_rates_cannot_be_overwritten_or_deleted_in_mysql(): void
    {
        $rate=app(PaymentFeeService::class)->publish($this->admin,$this->data());
        foreach(['update','delete'] as $operation) {
            try {
                $query=DB::table('payment_fee_rates')->where('id',$rate->id);
                $operation==='update' ? $query->update(['fixed_amount'=>999]) : $query->delete();
                $this->fail('Tariff history must be immutable');
            } catch(\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('immutable',$e->getMessage()); }
        }
        $this->assertSame(0,$rate->fresh()->fixed_amount);
        $zero=new PaymentFeeRate(['rate_units'=>0,'vat_units'=>0,'fixed_amount'=>0]);
        $this->assertSame(100000,app(PaymentFeeService::class)->estimate(100000,$zero)['net']);
        $zero->fixed_amount=200;
        $this->assertSame(-100,app(PaymentFeeService::class)->estimate(100,$zero)['net']);
    }
}
