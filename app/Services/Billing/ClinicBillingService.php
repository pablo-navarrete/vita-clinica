<?php

namespace App\Services\Billing;

use App\Models\CustomerSubscription;
use App\Models\FlowBillingAgreement;
use App\Models\FlowBillingCustomer;
use App\Models\FlowCardRegistration;
use App\Models\FlowCheckoutOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClinicBillingService
{
    public function __construct(private FlowBillingClient $flow, private SubscriptionService $subscriptions) {}

    public function authorize(User $actor, int $customerId): void
    {
        abort_unless(app(\App\Policies\ClinicBillingPolicy::class)->manage($actor, $customerId), 403, 'Solo el administrador de la sede activa puede gestionar la suscripción de su clínica.');
    }

    public function accountMatches(string $environment, string $hash): void
    {
        abort_unless($environment === config('flow.environment') && hash_equals($hash, $this->flow->account()), 409, 'La operación corresponde a otra cuenta o ambiente de Flow. Solicita conciliación antes de continuar.');
        abort_if(app()->environment('production') && $environment !== 'production', 409, 'Un pago de pruebas no puede acreditar una suscripción de producción.');
    }

    public function agreement(CustomerSubscription $s): ?FlowBillingAgreement
    {
        return FlowBillingAgreement::where('customer_subscription_id', $s->id)->whereIn('state', ['creating', 'active', 'ending', 'review'])->latest()->first();
    }

    private function event(CustomerSubscription $s, int $actor, string $action, array $details = []): void
    {
        $s->events()->create(['actor_id' => $actor, 'action' => $action, 'details' => $details, 'created_at' => now()]);
    }

    private function locked(User $actor, CustomerSubscription $s, callable $fn): mixed
    {
        $this->authorize($actor, (int) $s->customer_id);

        return Cache::lock('clinic-billing:'.$s->id, 180)->block(5, function () use ($fn, $s) {
            $fresh = $s->fresh();
            abort_unless((int) $fresh->revision === (int) $s->revision, 409, 'La suscripción cambió. Actualiza los datos antes de confirmar.');

            return $fn($fresh);
        });
    }

    private function openService(CustomerSubscription $s): void
    {
        abort_if($s->cancel_at_period_end || $s->cancelled_at, 409, 'La baja está registrada. Solicita al administrador de VITA una nueva contratación.');
    }

    private function billingCustomer(CustomerSubscription $s): FlowBillingCustomer
    {
        $environment = config('flow.environment');
        $account = $this->flow->account();
        $row = FlowBillingCustomer::where('customer_id', $s->customer_id)->where('environment', $environment)->where('account_hash', $account)->first();
        if ($row) {
            abort_unless($row->state === 'ready' && $row->provider_id, 409, 'El registro del cliente en Flow requiere revisión. No se repetirá automáticamente.');

            return $row;
        }
        $row = FlowBillingCustomer::create(['customer_id' => $s->customer_id, 'environment' => $environment, 'account_hash' => $account,
            'external_id' => 'vita-'.Str::uuid(), 'state' => 'creating']);
        $response = $this->flow->request('POST', 'customer/create', ['name' => $s->customer->name, 'email' => $s->customer->email, 'externalId' => $row->external_id]);
        abort_unless(is_string($response['customerId'] ?? null) && ($response['externalId'] ?? null) === $row->external_id, 409, 'No se pudo verificar el cliente creado en Flow.');
        $row->update(['provider_id' => $response['customerId'], 'state' => 'ready']);

        return $row;
    }

    public function registerCard(User $actor, CustomerSubscription $s): string
    {
        $this->flow->assertCanContract();

        return $this->locked($actor, $s, function ($s) use ($actor) {
            $this->openService($s);
            $callback = $this->flow->callback('card-return');
            $agreement = $this->agreement($s);
            if ($agreement) {
                $this->accountMatches($agreement->billingCustomer->environment, $agreement->billingCustomer->account_hash);
                abort_unless($agreement->state === 'active', 409, 'Primero actualiza el estado de la renovación.');
            }
            $customer = $this->billingCustomer($s);
            $attempt = FlowCardRegistration::create(['id' => (string) Str::uuid(), 'flow_billing_customer_id' => $customer->id, 'initiated_by' => $actor->id, 'state' => 'creating']);
            $response = $this->flow->request('POST', 'customer/register', ['customerId' => $customer->provider_id, 'url_return' => $callback]);
            $url = $this->flow->redirect($response);
            $attempt->update(['token' => $response['token'], 'token_hash' => hash('sha256', $response['token']), 'redirect_url' => $url, 'state' => 'pending']);
            $this->event($s, $actor->id, 'card_registration_requested', ['attempt_id' => $attempt->id, 'consent' => 'vita-card-v1', 'accepted_at' => now()->toIso8601String()]);

            return $url;
        });
    }

    public function confirmCard(string $token): FlowCardRegistration
    {
        $attempt = FlowCardRegistration::where('token_hash', hash('sha256', $token))->firstOrFail();
        $customer = $attempt->billingCustomer;
        $this->accountMatches($customer->environment, $customer->account_hash);
        $result = $this->flow->request('GET', 'customer/getRegisterStatus', ['token' => $token]);
        abort_unless(($result['customerId'] ?? null) === $customer->provider_id, 409, 'El registro de tarjeta no corresponde al cliente.');
        if ((string) ($result['status'] ?? '') !== '1') {
            $attempt->update(['state' => 'failed']);

            return $attempt;
        }
        $last = (string) ($result['last4CardDigits'] ?? '');
        abort_unless(preg_match('/^\d{4}$/', $last), 409, 'Flow no confirmó la tarjeta registrada.');
        DB::transaction(function () use ($attempt, $customer, $result, $last) {
            $s = CustomerSubscription::where('customer_id', $customer->customer_id)->lockForUpdate()->firstOrFail();
            $current = FlowCardRegistration::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($current->state === 'confirmed') {
                return;
            }
            abort_unless($current->state === 'pending', 409, 'Este registro de tarjeta ya no está vigente.');
            // An older callback must not reactivate automatic charges after a newer cancellation.
            abort_if($s->cancel_at_period_end || $s->cancelled_at, 409, 'La suscripción ya fue dada de baja. Revisa la tarjeta en Flow.');
            $agreement = $this->agreement($s);
            if ($agreement) {
                abort_unless($agreement->state === 'active' && $agreement->updated_at->lte($attempt->created_at), 409, 'La modalidad cambió durante el registro. Actualiza su estado.');
                $agreement->update(['collection_mode' => 'automatic']);
            }
            $customer->update(['card_brand' => mb_substr((string) ($result['creditCardType'] ?? 'Tarjeta'), 0, 40), 'card_last_four' => $last]);
            $current->update(['state' => 'confirmed']);
            $this->event($s, $attempt->initiated_by, 'card_registered', ['brand' => $customer->card_brand, 'last_four' => $last]);
        });

        return $attempt->fresh();
    }

    public function checkout(User $actor, CustomerSubscription $s): string
    {
        $this->flow->assertCanContract();

        return $this->locked($actor, $s, function ($s) use ($actor) {
            $this->openService($s);
            $agreement = $this->agreement($s);
            if ($agreement) {
                $this->accountMatches($agreement->billingCustomer->environment, $agreement->billingCustomer->account_hash);
                abort_unless($agreement->state === 'active' && $agreement->collection_mode === 'manual', 409, 'La renovación automática está activa o pendiente. No se creará un cobro manual adicional.');
                abort_if($s->next_renewal_on->toDateString() > now('America/Santiago')->toDateString(), 422, 'Podrás pagar cuando comience el próximo período.');
                $provider = $this->providerSubscription($agreement);
                foreach ($provider['invoices'] ?? [] as $invoice) {
                    if (substr((string) ($invoice['period_start'] ?? ''), 0, 10) !== $s->next_renewal_on->toDateString()) {
                        continue;
                    }
                    $invoice = $this->verifiedInvoice($agreement, $invoice['id']);
                    $payment = $invoice['payment'] ?? [];
                    if ((int) ($payment['status'] ?? 0) === 2) {
                        $this->settleInvoice($agreement, $invoice, $payment);
                        abort(409, 'El pago fue confirmado. Actualiza el módulo.');
                    }
                    $link = $invoice['paymentLink'] ?? '';

                    return $this->verifiedLink($link);
                }
                abort(409, 'Flow aún no ha generado el importe del próximo período. No se creará otro cobro.');
            }
            $quote = $this->subscriptions->upcoming($s);
            abort_if($quote['period_start'] > now('America/Santiago')->toDateString(), 422, 'Podrás pagar cuando comience el próximo período.');
            abort_if($quote['amount'] <= 0, 422, 'Un período sin costo requiere confirmación administrativa, sin generar un cargo en Flow.');
            abort_if($quote['amount'] < 350, 422, 'El importe es inferior al mínimo de $350 CLP para pagos con Flow. Actualiza las condiciones de la suscripción antes de pagar.');
            $this->flow->callback('payment');
            $previous = FlowCheckoutOrder::where('customer_subscription_id', $s->id)->whereDate('period_start', $quote['period_start'])->orderByDesc('attempt')->first();
            // Terminal state reserved for explicitly audited sandbox recovery.
            $retry = $previous && $previous->state === 'not_created' && $previous->environment === 'sandbox'
                && config('flow.environment') === 'sandbox' && ! app()->environment('production');
            if ($previous && $previous->state === 'failed') {
                $this->accountMatches($previous->environment, $previous->account_hash);
                $remote = $this->flow->request('GET', 'payment/getStatusByCommerceId', ['commerceId' => $previous->commerce_order]);
                $this->settleOrder($previous, $remote);
                abort_unless(in_array((int) ($remote['status'] ?? 0), [3, 4], true), 409, 'La orden anterior no está cancelada o rechazada. Confirma su estado antes de generar otro intento.');
                $retry = true;
            }
            $order = DB::transaction(function () use ($s, $actor, $quote, $retry, $previous) {
                $locked = CustomerSubscription::whereKey($s->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $locked->revision === (int) $s->revision, 409, 'El contrato cambió antes de preparar el pago.');
                if ($previous && ! $retry) {
                    return $previous;
                }
                abort_if(FlowCheckoutOrder::where('customer_subscription_id', $s->id)->whereIn('state', ['prepared', 'sending', 'pending'])->exists(), 409, 'Ya existe un cobro en proceso.');

                return FlowCheckoutOrder::create(['customer_subscription_id' => $s->id, 'period_start' => $quote['period_start'], 'attempt' => $previous ? ((int) $previous->attempt + 1) : 1,
                    'id' => (string) Str::uuid(), 'environment' => config('flow.environment'), 'account_hash' => $this->flow->account(),
                    'commerce_order' => 'vita-'.Str::uuid(), 'quote' => $quote, 'state' => 'prepared', 'initiated_by' => $actor->id]);
            });
            $this->accountMatches($order->environment, $order->account_hash);
            if ($order->redirect_url && $order->state === 'pending') {
                return $order->redirect_url;
            }
            abort_unless($order->state === 'prepared', 409, 'La orden ya fue enviada a Flow. Usa Actualizar estado antes de reintentar; no se creará otro cargo.');
            $order->update(['state' => 'sending']);
            $result = $this->flow->request('POST', 'payment/create', ['commerceOrder' => $order->commerce_order, 'subject' => 'VITA · '.$quote['plan_name'],
                'currency' => 'CLP', 'amount' => $quote['amount'], 'email' => $s->customer->email,
                'urlConfirmation' => $this->flow->callback('payment'), 'urlReturn' => $this->flow->callback('payment-return'), 'timeout' => 3600]);
            $url = $this->flow->redirect($result);
            abort_unless(preg_match('/^\d+$/', (string) ($result['flowOrder'] ?? '')), 409, 'No se pudo verificar la orden Flow.');
            $order->update(['flow_order' => (string) $result['flowOrder'], 'token' => $result['token'], 'redirect_url' => $url, 'state' => 'pending']);
            $this->event($s, $actor->id, 'flow_checkout_created', ['order_id' => $order->id, 'period_start' => $quote['period_start'], 'amount' => $quote['amount']]);

            return $url;
        });
    }

    private function verifiedLink(string $url): string
    {
        $host = config('flow.environment') === 'production' ? 'www.flow.cl' : 'sandbox.flow.cl';
        abort_unless(parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === $host && ! parse_url($url, PHP_URL_USER), 409, 'Flow no confirmó un enlace válido.');

        return $url;
    }

    public function paymentCallback(string $token): ?CustomerSubscription
    {
        $payment = $this->flow->request('GET', 'payment/getStatus', ['token' => $token]);
        $order = FlowCheckoutOrder::where('commerce_order', $payment['commerceOrder'] ?? '')->firstOrFail();
        $this->accountMatches($order->environment, $order->account_hash);
        $this->settleOrder($order, $payment);

        return $order->subscription;
    }

    private function settleOrder(FlowCheckoutOrder $order, array $payment): void
    {
        abort_unless(($payment['commerceOrder'] ?? null) === $order->commerce_order && (! $order->flow_order || (string) ($payment['flowOrder'] ?? '') === $order->flow_order), 409, 'La orden Flow no coincide.');
        if ((int) ($payment['status'] ?? 0) !== 2) {
            if (in_array((int) ($payment['status'] ?? 0), [3, 4], true)) {
                DB::transaction(function () use ($order) {
                    CustomerSubscription::whereKey($order->customer_subscription_id)->lockForUpdate()->firstOrFail();
                    $fresh = FlowCheckoutOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    if ($fresh->state === 'paid') {
                        return;
                    }
                    $paid = DB::table('flow_billing_receipts')->where('customer_subscription_id', $fresh->customer_subscription_id)
                        ->where('environment', $fresh->environment)->where('account_hash', $fresh->account_hash)->where('flow_order', $fresh->flow_order)->exists();
                    $fresh->update(['state' => $paid ? 'paid' : 'failed']);
                });
            }

            return;
        }
        $this->settle($order->subscription, $order->quote, $payment, $order->initiated_by, $order->environment, $order->account_hash);
        $order->update(['state' => 'paid', 'flow_order' => (string) $payment['flowOrder'], 'paid_at' => now()]);
    }

    public function settle(CustomerSubscription $s, array $quote, array $payment, int $actor, string $environment, string $account): void
    {
        $this->accountMatches($environment, $account);
        abort_unless((string) ($payment['status'] ?? '') === '2' && ($payment['currency'] ?? null) === 'CLP'
            && preg_match('/^\d+(\.0+)?$/', (string) ($payment['amount'] ?? '')) && (int) $payment['amount'] === (int) $quote['amount']
            && ($payment['paymentData']['currency'] ?? null) === 'CLP' && preg_match('/^\d+(\.0+)?$/', (string) ($payment['paymentData']['amount'] ?? ''))
            && (int) $payment['paymentData']['amount'] === (int) $quote['amount']
            && preg_match('/^\d{1,80}$/', (string) ($payment['flowOrder'] ?? '')), 409, 'El pago no coincide con el importe y moneda contratados. Requiere conciliación.');
        $paid = substr((string) ($payment['paymentData']['date'] ?? ''), 0, 10);
        abort_unless(Carbon::hasFormat($paid, 'Y-m-d') && $paid <= now('America/Santiago')->toDateString() && $paid >= $quote['period_start'], 409, 'Flow no confirmó una fecha de pago válida para este período.');
        DB::transaction(function () use ($s, $quote, $payment, $actor, $environment, $account, $paid) {
            $s = CustomerSubscription::whereKey($s->id)->lockForUpdate()->firstOrFail();
            $receipt = DB::table('flow_billing_receipts')->where('environment', $environment)->where('account_hash', $account)->where('flow_order', (string) $payment['flowOrder'])->first();
            if ($receipt) {
                abort_unless((int) $receipt->customer_subscription_id === (int) $s->id, 409, 'Pago ya vinculado a otra suscripción.');

                return;
            }
            abort_if($s->cancelled_at || ($s->cancel_at_period_end && $s->access_ends_on && $quote['period_start'] >= $s->access_ends_on->toDateString()), 409, 'El pago llegó después de solicitar la baja. Requiere conciliación antes de modificar el período.');
            abort_unless($s->next_renewal_on->toDateString() === $quote['period_start'], 409, 'El período ya cambió o fue pagado. Se requiere conciliación, sin duplicar la renovación.');
            $current = $this->subscriptions->upcoming($s);
            foreach (['amount', 'price_id', 'branch_quantity', 'period_start', 'period_end', 'version_hash'] as $key) {
                abort_unless(($current[$key] ?? null) === ($quote[$key] ?? null), 409, 'Las condiciones cambiaron. El pago requiere conciliación.');
            }
            $record = $s->payments()->create(['customer_id' => $s->customer_id, 'billing_plan_price_id' => $quote['price_id'],
                'period_start' => $quote['period_start'], 'period_end' => $quote['period_end'], 'paid_on' => $paid, 'amount' => $quote['amount'], 'currency' => 'CLP',
                'method' => 'flow', 'reference' => 'flow:'.substr($account, 0, 12).':'.$payment['flowOrder'], 'note' => 'Pago verificado mediante la API de Flow.',
                'breakdown' => $quote + ['flow_environment' => $environment], 'recorded_by' => $actor, 'created_at' => now()]);
            if ($quote['change_id'] ?? null) {
                $change = $s->pendingChange;
                abort_unless($change && (int) $change->id === (int) $quote['change_id'], 409);
                $change->update(['status' => 'applied', 'applied_at' => now()]);
                $s->billing_plan_price_id = $quote['price_id'];
                $s->branch_quantity = $quote['branch_quantity'];
            }
            $s->paid_through_on = $quote['period_end'];
            $s->next_renewal_on = $quote['period_end'];
            $s->revision++;
            $s->save();
            DB::table('flow_billing_receipts')->insert(['customer_subscription_id' => $s->id, 'subscription_payment_id' => $record->id,
                'environment' => $environment, 'account_hash' => $account, 'flow_order' => (string) $payment['flowOrder'],
                'commerce_order' => (string) $payment['commerceOrder'], 'amount' => $quote['amount'], 'verified_at' => now()]);
            $this->event($s, $actor, 'flow_payment_verified', ['flow_order' => (string) $payment['flowOrder'], 'period_start' => $quote['period_start'], 'period_end' => $quote['period_end']]);
        });
    }

    public function enableAutomatic(User $actor, CustomerSubscription $s): void
    {
        $this->flow->assertCanContract();
        $this->locked($actor, $s, function ($s) use ($actor) {
            $this->openService($s);
            $this->flow->callback('recurring');
            abort_if($this->agreement($s), 409, 'Ya existe una renovación registrada. Actualiza su estado o registra nuevamente la tarjeta para reactivarla.');
            abort_if(FlowCheckoutOrder::where('customer_subscription_id', $s->id)->whereNotIn('state', ['paid', 'failed', 'not_created'])->exists(), 409, 'Hay un pago manual pendiente. Confírmalo antes de activar cargos automáticos.');
            abort_if($s->pendingChange, 409, 'Hay un cambio de condiciones pendiente. Aplícalo mediante pago manual antes de autorizar la renovación automática.');
            $customer = $this->billingCustomer($s);
            $remote = $this->flow->request('GET', 'customer/get', ['customerId' => $customer->provider_id]);
            abort_unless(($remote['customerId'] ?? null) === $customer->provider_id && ($remote['externalId'] ?? null) === $customer->external_id
                && preg_match('/^\d{4}$/', (string) ($remote['last4CardDigits'] ?? '')), 422, 'Registra primero una tarjeta válida.');
            $quote = $this->subscriptions->upcoming($s);
            abort_if($quote['period_start'] < now('America/Santiago')->toDateString(), 422, 'Regulariza primero los períodos pendientes mediante pago manual.');
            abort_if($quote['amount'] <= 0, 422, 'Los períodos sin costo se gestionan sin cargos automáticos.');
            $id = (string) Str::uuid();
            $agreement = DB::transaction(function () use ($s, $customer, $quote, $actor, $id) {
                $locked = CustomerSubscription::whereKey($s->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $locked->revision === (int) $s->revision, 409, 'El contrato cambió antes de autorizar la renovación.');

                return FlowBillingAgreement::create(['id' => $id, 'customer_subscription_id' => $s->id, 'flow_billing_customer_id' => $customer->id,
                    'plan_id' => 'vita-'.str_replace('-', '', $id), 'state' => 'creating', 'quote' => $quote, 'starts_on' => $quote['period_start'],
                    'initiated_by' => $actor->id, 'accepted_at' => now(), 'consent_version' => 'vita-recurring-v1']);
            });
            $plan = $this->flow->request('POST', 'plans/create', ['planId' => $agreement->plan_id, 'name' => 'VITA '.$quote['plan_name'], 'currency' => 'CLP',
                'amount' => $quote['amount'], 'interval' => $quote['interval'] === 'month' ? 3 : 4, 'interval_count' => 1, 'trial_period_days' => 0,
                'days_until_due' => (int) $s->price->version->grace_days, 'urlCallback' => $this->flow->callback('recurring/'.$id)]);
            abort_unless(($plan['planId'] ?? null) === $agreement->plan_id && ($plan['currency'] ?? null) === 'CLP' && (int) ($plan['amount'] ?? -1) === $quote['amount']
                && (int) ($plan['interval'] ?? 0) === ($quote['interval'] === 'month' ? 3 : 4) && (int) ($plan['interval_count'] ?? 0) === 1
                && (int) ($plan['trial_period_days'] ?? -1) === 0, 409, 'El plan de Flow no coincide con las condiciones autorizadas.');
            $provider = $this->flow->request('POST', 'subscription/create', ['planId' => $agreement->plan_id, 'customerId' => $customer->provider_id,
                'subscription_start' => $quote['period_start'], 'trial_period_days' => 0]);
            $this->bindAgreement($agreement, $provider);
            $this->event($s, $actor->id, 'automatic_renewal_enabled', ['agreement_id' => $id, 'quote' => $quote, 'consent_version' => 'vita-recurring-v1']);
        });
    }

    private function bindAgreement(FlowBillingAgreement $agreement, array $provider): void
    {
        abort_unless(($provider['customerId'] ?? null) === $agreement->billingCustomer->provider_id && ($provider['planId'] ?? null) === $agreement->plan_id
            && is_string($provider['subscriptionId'] ?? null), 409, 'La suscripción devuelta por Flow no corresponde a la clínica.');
        $agreement->update(['provider_id' => $provider['subscriptionId'], 'state' => 'active']);
    }

    private function providerSubscription(FlowBillingAgreement $agreement): array
    {
        $customer = $agreement->billingCustomer;
        $this->accountMatches($customer->environment, $customer->account_hash);
        if (! $agreement->provider_id) {
            $found = [];
            for ($start = 0; $start < 10000; $start += 100) {
                $page = $this->flow->request('GET', 'customer/getSubscriptions', ['customerId' => $customer->provider_id, 'start' => $start, 'limit' => 100]);
                abort_unless(is_array($page['data'] ?? null), 409, 'No se pudo conciliar la suscripción remota.');
                foreach ($page['data'] as $item) {
                    if (($item['planId'] ?? null) === $agreement->plan_id) {
                        $found[] = $item;
                    }
                }
                if (empty($page['hasMore'])) {
                    break;
                }
            }
            abort_unless(count($found) === 1, 409, 'Flow no confirmó una única suscripción. Solicita revisión; no se repetirá la contratación.');
            $this->bindAgreement($agreement, $found[0]);
            $agreement->refresh();
        }
        $provider = $this->flow->request('GET', 'subscription/get', ['subscriptionId' => $agreement->provider_id]);
        abort_unless(($provider['customerId'] ?? null) === $customer->provider_id && ($provider['subscriptionId'] ?? null) === $agreement->provider_id
            && ($provider['planId'] ?? null) === $agreement->plan_id, 409, 'La suscripción Flow no coincide.');

        return $provider;
    }

    private function verifiedInvoice(FlowBillingAgreement $agreement, int|string $id): array
    {
        $invoice = $this->flow->request('GET', 'invoice/get', ['invoiceId' => $id]);
        abort_unless(($invoice['subscriptionId'] ?? null) === $agreement->provider_id && ($invoice['customerId'] ?? null) === $agreement->billingCustomer->provider_id
            && ($invoice['currency'] ?? null) === 'CLP' && preg_match('/^\d+(\.0+)?$/', (string) ($invoice['amount'] ?? ''))
            && (int) ($invoice['amount'] ?? -1) === $agreement->quote['amount'], 409, 'El importe Flow no corresponde al contrato.');
        $start = substr((string) ($invoice['period_start'] ?? ''), 0, 10);
        $end = substr((string) ($invoice['period_end'] ?? ''), 0, 10);
        abort_unless(Carbon::hasFormat($start, 'Y-m-d') && Carbon::hasFormat($end, 'Y-m-d') && $start >= $agreement->starts_on->toDateString(), 409, 'El calendario del importe no corresponde al contrato.');
        $expected = $this->subscriptions->nextEnd($agreement->subscription, Carbon::parse($start), $agreement->quote['interval'])->toDateString();
        abort_unless(in_array($end, [$expected, Carbon::parse($expected)->subDay()->toDateString()], true), 409, 'El calendario Flow requiere conciliación antes de pagar.');

        return $invoice;
    }

    private function settleInvoice(FlowBillingAgreement $agreement, array $invoice, array $payment): void
    {
        $s = $agreement->subscription->fresh();
        $start = substr((string) ($invoice['period_start'] ?? ''), 0, 10);
        $end = substr((string) ($invoice['period_end'] ?? ''), 0, 10);
        abort_unless(Carbon::hasFormat($start, 'Y-m-d') && Carbon::hasFormat($end, 'Y-m-d')
            && $start >= $agreement->starts_on->toDateString(), 409, 'El período Flow no corresponde a lo autorizado.');
        $q = $agreement->quote;
        $q['period_start'] = $start;
        $q['period_end'] = $this->subscriptions->nextEnd($s, Carbon::parse($start), $q['interval'])->toDateString();
        // Flow may represent the final inclusive date; VITA always stores an exclusive end.
        abort_unless(in_array($end, [$q['period_end'], Carbon::parse($q['period_end'])->subDay()->toDateString()], true), 409, 'El calendario Flow difiere del contratado; requiere conciliación.');
        abort_unless((string) ($invoice['payment']['flowOrder'] ?? '') === (string) ($payment['flowOrder'] ?? '')
            && ($invoice['payment']['commerceOrder'] ?? null) === ($payment['commerceOrder'] ?? null), 409, 'El pago no pertenece al importe de esta suscripción.');
        $this->settle($s, $q, $payment, $agreement->initiated_by, $agreement->billingCustomer->environment, $agreement->billingCustomer->account_hash);
    }

    public function recurringCallback(FlowBillingAgreement $agreement, string $token): CustomerSubscription
    {
        $this->accountMatches($agreement->billingCustomer->environment, $agreement->billingCustomer->account_hash);
        $payment = $this->flow->request('GET', 'payment/getStatus', ['token' => $token]);
        $provider = $this->providerSubscription($agreement);
        foreach ($provider['invoices'] ?? [] as $item) {
            if ((string) ($item['payment']['flowOrder'] ?? '') !== (string) ($payment['flowOrder'] ?? '')) {
                continue;
            }
            $invoice = $this->verifiedInvoice($agreement, $item['id']);
            if ((int) ($payment['status'] ?? 0) === 2) {
                $this->settleInvoice($agreement, $invoice, $payment);
            }

            return $agreement->subscription;
        }
        abort(409, 'No se pudo vincular el pago al importe de la suscripción. Se requiere conciliación.');
    }

    public function refresh(User $actor, CustomerSubscription $s): void
    {
        $this->locked($actor, $s, fn ($s) => $this->reconcile($s));
    }

    public function reconcile(CustomerSubscription $s): void
    {
        // Read-only provider operations. Flow alone executes recurring charges.
        foreach (FlowCheckoutOrder::where('customer_subscription_id', $s->id)->whereIn('state', ['sending', 'pending'])->get() as $order) {
            $this->accountMatches($order->environment, $order->account_hash);
            $payment = $this->flow->request('GET', 'payment/getStatusByCommerceId', ['commerceId' => $order->commerce_order]);
            $this->settleOrder($order, $payment);
        }
        $agreement = $this->agreement($s);
        if ($agreement) {
            $provider = $this->providerSubscription($agreement);
            $invoices = collect($provider['invoices'] ?? [])->sortBy('period_start');
            foreach ($invoices as $item) {
                if ((int) ($item['payment']['status'] ?? 0) !== 2) {
                    continue;
                }
                $invoice = $this->verifiedInvoice($agreement, $item['id']);
                $this->settleInvoice($agreement, $invoice, $invoice['payment']);
            }
        }
    }

    public function disableAutomatic(User $actor, CustomerSubscription $s): void
    {
        $this->locked($actor, $s, function ($s) use ($actor) {
            $agreement = $this->agreement($s);
            abort_unless($agreement, 409, 'No hay una renovación automática registrada.');
            $this->providerSubscription($agreement);
            $customer = $agreement->billingCustomer;
            abort_if(FlowCardRegistration::where('flow_billing_customer_id', $customer->id)->whereIn('state', ['creating', 'pending'])->exists(), 409, 'Finaliza o confirma primero el registro de tarjeta pendiente para no reactivar cargos mediante un retorno anterior.');
            $remote = $this->flow->request('GET', 'customer/get', ['customerId' => $customer->provider_id]);
            abort_unless(($remote['customerId'] ?? null) === $customer->provider_id, 409, 'Flow no confirmó la identidad del cliente.');
            if (! empty($remote['last4CardDigits'])) {
                $this->flow->request('POST', 'customer/unRegister', ['customerId' => $customer->provider_id]);
                $remote = $this->flow->request('GET', 'customer/get', ['customerId' => $customer->provider_id]);
            }
            abort_unless(($remote['customerId'] ?? null) === $customer->provider_id && empty($remote['last4CardDigits']), 409, 'Flow aún no confirmó que la tarjeta fue retirada.');
            DB::transaction(function () use ($agreement, $customer, $s, $actor) {
                CustomerSubscription::whereKey($s->id)->lockForUpdate()->firstOrFail();
                $agreement->update(['collection_mode' => 'manual']);
                $customer->update(['card_brand' => null, 'card_last_four' => null]);
                FlowCardRegistration::where('flow_billing_customer_id', $customer->id)->whereIn('state', ['creating', 'pending'])->update(['state' => 'cancelled']);
                $this->event($s, $actor->id, 'automatic_renewal_disabled', ['agreement_id' => $agreement->id, 'mode' => 'manual', 'card_removed' => true]);
            });
        });
    }

    public function cancelService(User $actor, CustomerSubscription $s): void
    {
        $this->locked($actor, $s, function ($s) use ($actor) {
            abort_if(FlowCheckoutOrder::where('customer_subscription_id', $s->id)->whereIn('state', ['sending', 'pending'])->exists(), 409, 'Confirma primero el pago pendiente antes de solicitar la baja.');
            $agreement = $this->agreement($s);
            if ($agreement) {
                $this->reconcile($s);
                $provider = $this->providerSubscription($agreement);
                // Stop provider billing immediately; VITA independently preserves paid access.
                $result = (int) ($provider['status'] ?? 0) === 4 ? $provider : $this->flow->request('POST', 'subscription/cancel', ['subscriptionId' => $agreement->provider_id, 'at_period_end' => 0]);
                abort_unless(($result['subscriptionId'] ?? null) === $agreement->provider_id && (int) ($result['status'] ?? 0) === 4, 409, 'Flow no confirmó la cancelación de renovación.');
                $agreement->update(['state' => 'stopped', 'stopped_at' => now()]);
            }
            DB::transaction(function () use ($s, $actor) {
                $s = CustomerSubscription::whereKey($s->id)->lockForUpdate()->firstOrFail();
                $s->cancel_at_period_end = true;
                $s->access_ends_on = $s->paid_through_on ?? $s->trial_ends_on ?? $s->starts_on;
                $s->revision++;
                $s->save();
                if ($s->pendingChange) {
                    $s->pendingChange->update(['status' => 'cancelled']);
                }
                $this->event($s, $actor->id, 'service_cancellation_requested', ['effective_on' => $s->access_ends_on->toDateString()]);
            });
        });
    }
}
