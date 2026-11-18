<?php

namespace Tests\Feature;

use App\Models\ClinicBranch;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\FlowBillingAgreement;
use App\Models\FlowBillingCustomer;
use App\Models\FlowCardRegistration;
use App\Models\FlowCheckoutOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\ClinicBillingService;
use App\Services\Billing\PlanCatalogService;
use App\Services\Billing\PlanVersionService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicBillingPortalTest extends TestCase
{
    private User $global;

    private User $admin;

    private Customer $customer;

    private CustomerSubscription $subscription;

    private ClinicBranch $branch;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'America/Santiago'));
        $key = Str::lower(Str::random(12));
        $this->global = User::create(['name' => 'Global', 'rut' => $key, 'email' => $key.'@global.test', 'password' => Hash::make('test-password'), 'phone' => '123', 'status' => 1]);
        $role = Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
        $this->global->roles()->attach($role->id);
        $this->customer = Customer::create(['name' => 'Portal test', 'legal_name' => 'Portal', 'domain' => 'billing-'.$key, 'holder' => 'Test', 'rut' => $key, 'phone' => '123', 'email' => $key.'@customer.test', 'status' => 1]);
        $this->branch = $this->customer->branches()->create(['name' => 'Principal', 'status' => 1]);
        $this->admin = User::create(['customer_id' => $this->customer->id, 'name' => 'Clinic admin', 'rut' => $key.'a', 'email' => $key.'@admin.test', 'password' => Hash::make('test-password'), 'phone' => '123', 'status' => 1]);
        $role = Role::create(['customer_id' => $this->customer->id, 'name' => 'Administrador']);
        $this->admin->branches()->attach($this->branch->id, ['status' => 1]);
        $this->admin->branchRoles()->attach($role->id, ['customer_id' => $this->customer->id, 'clinic_branch_id' => $this->branch->id]);
        $plan = app(PlanCatalogService::class)->save($this->global, ['name' => 'Clinic plan', 'code' => 'portal_'.$key, 'active' => 1, 'position' => 0]);
        $versions = app(PlanVersionService::class);
        $v = $versions->create($this->global, $plan);
        $versions->save($this->global, $v, ['included_branches' => 1, 'trial_days' => 0, 'grace_days' => 3, 'terms' => 'Test conditions',
            'prices' => ['month' => ['enabled' => 1, 'amount' => 10000, 'additional_branch_amount' => 1000], 'year' => ['enabled' => 0]],
            'limits' => ['users' => ['unlimited' => 1, 'scope' => 'customer'], 'professionals' => ['unlimited' => 1, 'scope' => 'branch'], 'branches' => ['unlimited' => 1, 'scope' => 'customer']]]);
        $versions->publish($this->global, $v);
        $this->subscription = app(SubscriptionService::class)->create($this->global, ['customer_id' => $this->customer->id, 'billing_plan_price_id' => $v->prices()->first()->id,
            'branch_quantity' => 1, 'starts_on' => '2026-11-02', 'use_trial' => 0]);
        config(['flow.enabled' => true, 'flow.environment' => 'sandbox', 'flow.api_key' => 'portal-fixture-key', 'flow.secret_key' => 'portal-fixture-secret',
            'flow.public_url' => 'https://portal-callback.example.test', 'flow.production_payments' => false]);
        Http::preventStrayRequests();
        $this->root = 'http://'.$this->customer->domain.'.'.config('app.domain');
        $this->actingAs($this->admin);
        session(['active_branch_customer_id' => $this->customer->id, 'active_branch_id' => $this->branch->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_conditions_show_the_assigned_snapshot_without_authorizing_charges(): void
    {
        $this->get($this->root.'/subscription-conditions?_vita_content=1')->assertOk()->assertSee('Condiciones y contrato')->assertSee('Test conditions')->assertSee('Clinic plan');
        $this->assertSame(0, FlowBillingAgreement::where('customer_subscription_id',$this->subscription->id)->count());
        $this->assertNull($this->subscription->fresh()->paid_through_on);
        $this->admin->branchRoles()->detach();
        $this->get($this->root.'/subscription-conditions?_vita_content=1')->assertForbidden();
    }

    private function fake(array|callable $callback = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake($callback);
    }

    private function action(string $action, array $data = [])
    {
        return $this->post($this->root.'/my-subscription/'.$action, $data + ['acceptance' => 1, 'password' => 'test-password', 'revision' => $this->subscription->fresh()->revision]);
    }

    private function checkout(): FlowCheckoutOrder
    {
        config(['flow.public_url' => 'https://callbacks.example.com']);
        $this->fake(['*' => Http::response(['url' => 'https://sandbox.flow.cl/app/web/pay.php', 'token' => 'PAYMENT_TOKEN_123456', 'flowOrder' => 10001])]);
        $this->action('pay')->assertRedirect('https://sandbox.flow.cl/app/web/pay.php?token=PAYMENT_TOKEN_123456');

        return FlowCheckoutOrder::where('customer_subscription_id', $this->subscription->id)->firstOrFail();
    }

    private function payment(FlowCheckoutOrder $order, array $overrides = []): array
    {
        return array_replace(['flowOrder' => 10001, 'commerceOrder' => $order->commerce_order, 'currency' => 'CLP', 'amount' => 10000, 'status' => 2,
            'paymentData' => ['date' => '2026-11-02 12:00:00', 'currency' => 'CLP', 'amount' => 10000]], $overrides);
    }

    private function billingCustomer(): FlowBillingCustomer
    {
        return FlowBillingCustomer::create(['customer_id' => $this->customer->id, 'environment' => 'sandbox',
            'account_hash' => hash('sha256', 'portal-fixture-key'), 'external_id' => 'external-'.$this->customer->id, 'provider_id' => 'cus_portal', 'state' => 'ready', 'card_brand' => 'Visa', 'card_last_four' => '1234']);
    }

    private function agreement(string $mode = 'automatic'): FlowBillingAgreement
    {
        $customer = $this->billingCustomer();

        return FlowBillingAgreement::create(['id' => (string) Str::uuid(), 'customer_subscription_id' => $this->subscription->id, 'flow_billing_customer_id' => $customer->id,
            'plan_id' => 'plan_'.Str::random(10), 'provider_id' => 'sus_fixture', 'state' => 'active', 'collection_mode' => $mode,
            'quote' => app(SubscriptionService::class)->upcoming($this->subscription), 'starts_on' => '2026-11-02',
            'initiated_by' => $this->admin->id, 'accepted_at' => now(), 'consent_version' => 'vita-recurring-v1']);
    }

    private function invoice(FlowBillingAgreement $a, int $status = 2): array
    {
        return ['id' => 123, 'subscriptionId' => $a->provider_id, 'customerId' => $a->billingCustomer->provider_id, 'currency' => 'CLP', 'amount' => 10000,
            'period_start' => '2026-11-02 00:00:00', 'period_end' => '2026-12-01 00:00:00',
            'paymentLink' => 'https://sandbox.flow.cl/app/web/pay.php?token=EXISTING_INVOICE_TOKEN',
            'payment' => ['flowOrder' => 789, 'commerceOrder' => 'provider-invoice-123', 'currency' => 'CLP', 'amount' => 10000, 'status' => $status,
                'paymentData' => ['date' => '2026-11-02 12:00:00', 'amount' => 10000, 'currency' => 'CLP']]];
    }

    private function fakeInvoice(FlowBillingAgreement $a, array $invoice): void
    {
        $this->fake(function ($request) use ($a, $invoice) {
            if (str_contains($request->url(), '/subscription/get')) {
                return Http::response(['subscriptionId' => $a->provider_id, 'customerId' => $a->billingCustomer->provider_id, 'planId' => $a->plan_id, 'invoices' => [$invoice]]);
            }
            if (str_contains($request->url(), '/invoice/get')) {
                return Http::response($invoice);
            }

            return Http::response($invoice['payment']);
        });
    }

    public function test_recurring_callback_confirms_exact_invoice_and_is_idempotent(): void
    {
        $a = $this->agreement();
        $invoice = $this->invoice($a);
        $this->fakeInvoice($a, $invoice);
        $url = 'http://'.config('app.domain').'/api/billing/flow/recurring/'.$a->id;
        $this->postJson($url, ['token' => 'RECURRING_TOKEN_123'])->assertOk();
        $this->postJson($url, ['token' => 'RECURRING_TOKEN_123'])->assertOk();
        $this->assertSame('2026-12-02', $this->subscription->fresh()->paid_through_on->toDateString());
        $this->assertSame(1, $this->subscription->payments()->count());
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_manual_mode_uses_existing_flow_invoice_without_creating_a_second_charge(): void
    {
        $a = $this->agreement('manual');
        $invoice = $this->invoice($a, 1);
        $this->fakeInvoice($a, $invoice);
        $this->action('pay')->assertRedirect($invoice['paymentLink']);
        $this->assertSame(0, FlowCheckoutOrder::where('customer_subscription_id', $this->subscription->id)->count());
        $this->assertNull($this->subscription->fresh()->paid_through_on);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_foreign_invoice_or_changed_period_is_rejected_without_extending_subscription(): void
    {
        $a = $this->agreement();
        $invoice = $this->invoice($a);
        $invoice['customerId'] = 'cus_other';
        $this->fakeInvoice($a, $invoice);
        $url = 'http://'.config('app.domain').'/api/billing/flow/recurring/'.$a->id;
        $this->postJson($url, ['token' => 'RECURRING_TOKEN_123'])->assertStatus(409);
        $invoice = $this->invoice($a);
        $invoice['period_end'] = '2027-01-01 00:00:00';
        $this->fakeInvoice($a, $invoice);
        $this->postJson($url, ['token' => 'RECURRING_TOKEN_123'])->assertStatus(409);
        $this->assertSame(0, $this->subscription->payments()->count());
    }

    public function test_rejected_checkout_can_retry_only_after_verifying_remote_terminal_state(): void
    {
        $order = $this->checkout();
        $this->fake(['*' => Http::response($this->payment($order, ['status' => 3]))]);
        app(ClinicBillingService::class)->paymentCallback('PAYMENT_TOKEN_123456');
        $this->assertSame('failed', $order->fresh()->state);
        $this->fake(function ($request) use ($order) {
            if ($request->method() === 'GET') {
                return Http::response($this->payment($order, ['status' => 3]));
            }

            return Http::response(['url' => 'https://sandbox.flow.cl/app/web/pay.php', 'token' => 'PAYMENT_TOKEN_222222', 'flowOrder' => 10002]);
        });
        $this->action('pay')->assertRedirect('https://sandbox.flow.cl/app/web/pay.php?token=PAYMENT_TOKEN_222222');
        $this->assertSame(2, FlowCheckoutOrder::where('customer_subscription_id', $this->subscription->id)->count());
        $this->assertSame(0, $this->subscription->payments()->count());
    }

    public function test_reconciled_not_created_sandbox_attempt_preserves_history_and_allows_new_attempt(): void
    {
        $order = $this->checkout();
        $order->update(['state'=>'not_created','flow_order'=>null,'token'=>null,'redirect_url'=>null]);
        $this->fake(['*'=>Http::response(['url'=>'https://sandbox.flow.cl/app/web/pay.php','token'=>'PAYMENT_TOKEN_222222','flowOrder'=>10002])]);
        $this->action('pay')->assertRedirect('https://sandbox.flow.cl/app/web/pay.php?token=PAYMENT_TOKEN_222222');
        $this->assertSame('not_created',$order->fresh()->state);
        $this->assertSame(2,FlowCheckoutOrder::where('customer_subscription_id',$this->subscription->id)->count());
        $this->assertNull($this->subscription->fresh()->paid_through_on);
        $this->assertSame(0,$this->subscription->payments()->count());
    }

    public function test_receipt_failure_rolls_back_paid_period_and_payment_and_terms_are_immutable(): void
    {
        $order = $this->checkout();
        try {
            DB::table('flow_checkout_orders')->where('id', $order->id)->update(['quote' => json_encode(['amount' => 1])]);
            $this->fail('Immutable quote');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
        \App\Models\SubscriptionEvent::setEventDispatcher(clone ($dispatcher = \App\Models\SubscriptionEvent::getEventDispatcher()));
        \App\Models\SubscriptionEvent::creating(fn () => throw new \RuntimeException('Simulated audit failure'));
        try {
            $this->fake(['*' => Http::response($this->payment($order))]);
            app(ClinicBillingService::class)->paymentCallback('PAYMENT_TOKEN_123456');
            $this->fail('Rollback required');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated audit failure', $e->getMessage());
        } finally {
            \App\Models\SubscriptionEvent::setEventDispatcher($dispatcher);
        }
        $this->assertNull($this->subscription->fresh()->paid_through_on);
        $this->assertSame(0, $this->subscription->payments()->count());
        $this->assertSame(0, DB::table('flow_billing_receipts')->where('customer_subscription_id', $this->subscription->id)->count());
    }

    public function test_production_mutations_are_disabled_and_stale_forms_are_rejected(): void
    {
        $this->fake();
        $this->action('pay', ['revision' => 999])->assertSessionHasErrors('revision');
        config(['flow.environment' => 'production', 'flow.production_payments' => false, 'flow.public_url' => 'https://callbacks.example.com']);
        $this->action('card')->assertRedirect()->assertSessionHas('error');
        Http::assertNothingSent();
        $this->assertSame(0, FlowBillingCustomer::where('customer_id', $this->customer->id)->count());
    }

    public function test_old_failure_notification_cannot_downgrade_a_verified_payment(): void
    {
        $order = $this->checkout();
        $this->fake(['*' => Http::response($this->payment($order))]);
        app(ClinicBillingService::class)->paymentCallback('PAYMENT_TOKEN_123456');
        $this->fake(['*' => Http::response($this->payment($order, ['status' => 3]))]);
        app(ClinicBillingService::class)->paymentCallback('PAYMENT_TOKEN_123456');
        $this->assertSame('paid', $order->fresh()->state);
        $this->assertSame(1, $this->subscription->payments()->count());
    }

    public function test_provider_cancellation_stops_billing_but_preserves_the_paid_period(): void
    {
        app(SubscriptionService::class)->recordPayment($this->global, $this->subscription, ['revision' => 1, 'paid_on' => '2026-11-02', 'amount' => 10000, 'reference' => 'before-agreement', 'note' => 'Confirmed']);
        $a = $this->agreement();
        $this->fake(function ($request) use ($a) {
            return Http::response(['subscriptionId' => $a->provider_id, 'customerId' => $a->billingCustomer->provider_id,
                'planId' => $a->plan_id, 'status' => $request->method() === 'POST' ? 4 : 1, 'invoices' => []]);
        });
        $this->action('cancel')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('stopped', $a->fresh()->state);
        $this->assertSame('2026-12-02', $this->subscription->fresh()->paid_through_on->toDateString());
        $this->assertSame('2026-12-02', $this->subscription->fresh()->access_ends_on->toDateString());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/subscription/cancel') && (int) $request['at_period_end'] === 0);
    }

    public function test_account_changes_do_not_recreate_checkout_under_new_keys(): void
    {
        $this->checkout();
        config(['flow.api_key' => 'different-flow-account']);
        $this->fake();
        $this->action('pay')->assertRedirect()->assertSessionHas('error');
        Http::assertNothingSent();
        $this->assertSame(1, FlowCheckoutOrder::where('customer_subscription_id', $this->subscription->id)->count());
        $this->assertNull($this->subscription->fresh()->paid_through_on);
    }

    public function test_portal_access_is_scoped_to_administrators_of_the_active_branch(): void
    {
        $this->get($this->root.'/my-subscription?_vita_content=1')->assertOk()->assertSee('Pago manual')->assertSee('Dar de baja el servicio');
        $second = $this->customer->branches()->create(['name' => 'Second', 'status' => 1]);
        $this->admin->branches()->attach($second->id, ['status' => 1]);
        $role = Role::create(['customer_id' => $this->customer->id, 'name' => 'Doctor']);
        $this->admin->branchRoles()->attach($role->id, ['customer_id' => $this->customer->id, 'clinic_branch_id' => $second->id]);
        $this->withSession(['active_branch_id' => $second->id])->get($this->root.'/my-subscription')->assertForbidden();
        $this->action('pay')->assertForbidden();
        $this->actingAs($this->global)->get($this->root.'/my-subscription')->assertForbidden();
    }

    public function test_checkout_reuses_pending_order_and_verified_callback_extends_period_once(): void
    {
        $order = $this->checkout();
        $this->action('pay')->assertRedirect($order->redirect_url);
        Http::assertSentCount(1);
        $this->assertNull($this->subscription->fresh()->paid_through_on);
        $payment = $this->payment($order);
        $this->fake(['*' => Http::response($payment)]);
        $callback = 'http://'.config('app.domain').'/api/billing/flow/payment';
        $this->postJson($callback, ['token' => 'PAYMENT_TOKEN_123456'])->assertOk();
        $this->postJson($callback, ['token' => 'PAYMENT_TOKEN_123456'])->assertOk();
        $this->assertSame('2026-12-02', $this->subscription->fresh()->paid_through_on->toDateString());
        $this->assertSame(1, $this->subscription->payments()->count());
        $this->assertSame(1, DB::table('flow_billing_receipts')->where('customer_subscription_id', $this->subscription->id)->count());
        $this->assertSame('flow', $this->subscription->payments()->first()->method);
        $this->assertStringNotContainsString('PAYMENT_TOKEN', json_encode($order->getAttributes()));
    }

    public function test_pending_or_mismatched_payments_never_extend_the_paid_period(): void
    {
        $order = $this->checkout();
        $this->fake(['*' => Http::response($this->payment($order, ['status' => 1]))]);
        app(ClinicBillingService::class)->paymentCallback('PAYMENT_TOKEN_123456');
        $this->assertNull($this->subscription->fresh()->paid_through_on);
        $this->fake(['*' => Http::response($this->payment($order, ['amount' => 1]))]);
        $this->postJson('http://'.config('app.domain').'/api/billing/flow/payment', ['token' => 'PAYMENT_TOKEN_123456'])->assertStatus(409);
        $this->assertSame(0, $this->subscription->payments()->count());
        try {
            app(SubscriptionService::class)->recordPayment($this->global, $this->subscription,
                ['revision' => 1, 'paid_on' => '2026-11-02', 'amount' => 10000, 'reference' => 'duplicate', 'note' => 'Manual duplicate']);
            $this->fail('An outstanding gateway order must block parallel manual payment');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_timeouts_do_not_create_a_second_provider_order_and_refresh_recovers(): void
    {
        config(['flow.public_url' => 'https://callbacks.example.com']);
        $this->fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('private signed URL must not leak'));
        $this->action('pay')->assertRedirect()->assertSessionHas('error');
        $order = FlowCheckoutOrder::where('customer_subscription_id', $this->subscription->id)->firstOrFail();
        $this->assertSame('sending', $order->state);
        $this->action('pay')->assertRedirect()->assertSessionHas('error');
        Http::assertSentCount(0);
        $this->fake(['*' => Http::response($this->payment($order))]);
        $this->action('refresh')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('paid', $order->fresh()->state);
        $this->assertSame(1, $this->subscription->payments()->count());
    }

    public function test_card_registration_stores_only_brand_last_four_and_checks_owner(): void
    {
        config(['flow.public_url' => 'https://callbacks.example.com']);
        $customer = $this->billingCustomer();
        $this->fake(['*' => Http::response(['url' => 'https://sandbox.flow.cl/app/customer/disclaimer.php', 'token' => 'CARD_TOKEN_123456'])]);
        $this->action('card')->assertRedirect();
        $attempt = FlowCardRegistration::where('flow_billing_customer_id', $customer->id)->firstOrFail();
        $this->fake(['*' => Http::response(['customerId' => 'wrong_customer', 'status' => 1, 'last4CardDigits' => '9999'])]);
        $callback = 'http://'.config('app.domain').'/api/billing/flow/card-return';
        $this->postJson($callback, ['token' => 'CARD_TOKEN_123456'])->assertStatus(409);
        $this->fake(['*' => Http::response(['customerId' => 'cus_portal', 'status' => 1, 'last4CardDigits' => '9876', 'creditCardType' => 'Mastercard', 'cardNumber' => 'NEVER STORE PAN'])]);
        $this->post($callback, ['token' => 'CARD_TOKEN_123456'])->assertRedirect($this->root.'/my-subscription');
        $this->assertSame('9876', $customer->fresh()->card_last_four);
        $this->assertSame('confirmed', $attempt->fresh()->state);
        $this->assertStringNotContainsString('CARD_TOKEN', json_encode($attempt->fresh()->getAttributes()));
        $this->assertStringNotContainsString('NEVER STORE PAN', json_encode($customer->fresh()->getAttributes()));
    }

    public function test_automatic_enrollment_and_manual_mode_are_distinct_from_service_cancellation(): void
    {
        config(['flow.public_url' => 'https://callbacks.example.com']);
        $customer = $this->billingCustomer();
        $this->fake(function ($request) use ($customer) {
            if (str_contains($request->url(), '/customer/get')) {
                return Http::response(['customerId' => 'cus_portal', 'externalId' => $customer->external_id, 'last4CardDigits' => '1234']);
            }
            if (str_contains($request->url(), '/plans/create')) {
                return Http::response(['planId' => $request['planId'], 'currency' => 'CLP', 'amount' => 10000, 'interval' => 3, 'interval_count' => 1, 'trial_period_days' => 0]);
            }

            return Http::response(['subscriptionId' => 'sus_portal', 'customerId' => 'cus_portal', 'planId' => $request['planId'], 'status' => 1]);
        });
        $this->action('automatic')->assertRedirect()->assertSessionHas('success');
        $agreement = FlowBillingAgreement::where('customer_subscription_id', $this->subscription->id)->firstOrFail();
        $this->assertSame('active', $agreement->state);
        $this->assertNotNull($agreement->accepted_at);
        $this->assertSame(0, $this->subscription->payments()->count());
        $this->fake(function ($request) use ($agreement) {
            if (str_contains($request->url(), '/subscription/get')) {
                return Http::response(['subscriptionId' => 'sus_portal', 'customerId' => 'cus_portal', 'planId' => $agreement->plan_id, 'invoices' => []]);
            }

            return Http::response(['customerId' => 'cus_portal', 'last4CardDigits' => null]);
        });
        $this->action('manual')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('manual', $agreement->fresh()->collection_mode);
        $this->assertFalse($this->subscription->fresh()->cancel_at_period_end);
        $this->assertNull($customer->fresh()->card_last_four);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/payment/create') || str_contains($r->url(), '/customer/collect'));
    }

    public function test_manual_service_cancellation_preserves_paid_period_and_medical_data(): void
    {
        app(SubscriptionService::class)->recordPayment($this->global, $this->subscription, ['revision' => 1, 'paid_on' => '2026-11-02', 'amount' => 10000, 'reference' => 'manual-before', 'note' => 'Test verified']);
        $this->fake();
        $this->action('cancel')->assertRedirect()->assertSessionHas('success');
        $s = $this->subscription->fresh();
        $this->assertTrue($s->cancel_at_period_end);
        $this->assertSame('2026-12-02', $s->access_ends_on->toDateString());
        $this->assertSame('2026-12-02', $s->paid_through_on->toDateString());
        Http::assertNothingSent();
    }

    public function test_explicit_consent_password_and_public_callback_are_required(): void
    {
        $this->fake();
        $this->postJson($this->root.'/my-subscription/automatic', ['acceptance' => 1, 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson($this->root.'/my-subscription/pay', [])->assertUnprocessable()->assertJsonValidationErrors('acceptance');
        config(['flow.public_url' => null]);
        $this->action('pay')->assertRedirect()->assertSessionHas('error');
        Http::assertNothingSent();
    }
}
