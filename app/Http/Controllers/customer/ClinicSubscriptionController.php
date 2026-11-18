<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\FlowBillingCustomer;
use App\Services\Billing\ClinicBillingService;
use App\Services\Billing\FlowBillingClient;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;

class ClinicSubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            app(ClinicBillingService::class)->authorize($request->user(), (int) app('customer')->id);

            return $next($request);
        });
    }

    private function subscription(): CustomerSubscription
    {
        return CustomerSubscription::where('customer_id', app('customer')->id)->firstOrFail();
    }

    public function index(ClinicBillingService $billing, SubscriptionService $service, FlowBillingClient $flow)
    {
        $s = CustomerSubscription::where('customer_id', app('customer')->id)->first();
        $isPaidUp = $s && $s->paid_through_on && now('America/Santiago')->startOfDay()->lt($s->paid_through_on);

        return view('customer.billing.subscription', ['subscription' => $s, 'quote' => $s ? $service->upcoming($s) : null,
            'state' => $s ? SubscriptionService::STATES[$service->state($s)] : null, 'isPaidUp' => $isPaidUp,
            'agreement' => $s ? $billing->agreement($s) : null,
            'billingCustomer' => FlowBillingCustomer::where('customer_id', app('customer')->id)->where('environment', config('flow.environment'))->where('account_hash', $flow->account())->first(),
            'payments' => $s ? $s->payments()->orderByDesc('id')->get() : null, 'ready' => $flow->ready()]);
    }

    public function conditions(SubscriptionService $service)
    {
        $subscription = CustomerSubscription::where('customer_id', app('customer')->id)->with('price.version')->first();
        $version = $subscription?->price?->version;
        $terms = null;
        if ($version) {
            abort_unless(hash_equals($version->sha256, hash('sha256', $version->snapshot)), 409, 'No se pudo verificar la versión de las condiciones.');
            $terms = json_decode($version->snapshot, true, 512, JSON_THROW_ON_ERROR);
        }
        return view('customer.billing.conditions', [
            'generalPolicy'=>\Illuminate\Support\Facades\DB::table('subscription_policy_versions')->orderByDesc('id')->first(),
            'subscription'=>$subscription, 'version'=>$version, 'terms'=>$terms,
            'quote'=>$subscription ? $service->quote($subscription->price, (int)$subscription->branch_quantity) : null,
            'classification'=>$subscription ? $service->classification($subscription) : null,
        ]);
    }

    private function perform(Request $request, callable $fn)
    {
        try {
            return $fn();
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return back()->with('error', 'Ya hay una operación de suscripción en curso. Actualiza su estado antes de reintentar.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if (! in_array($e->getStatusCode(), [409, 422], true) || $request->expectsJson()) {
                throw $e;
            }

            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function action(Request $request, ClinicBillingService $billing, string $customer, string $action)
    {
        abort_unless(in_array($action, ['pay', 'card', 'automatic', 'manual', 'cancel', 'refresh'], true), 404);
        if ($action !== 'refresh') {
            $request->validate(['acceptance' => 'required|accepted']);
        }
        if (in_array($action, ['card', 'automatic', 'cancel'], true)) {
            $request->validate(['password' => 'required|current_password']);
        }
        $s = $this->subscription();
        if ($action !== 'refresh') {
            $data = $request->validate(['revision' => 'required|integer|min:1']);
            if ((int) $s->revision !== (int) $data['revision']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['revision' => 'La suscripción cambió. Actualiza los datos antes de confirmar.']);
            }
        }

        return $this->perform($request, function () use ($request, $billing, $s, $action) {
            if ($action === 'pay') {
                return redirect()->away($billing->checkout($request->user(), $s));
            }
            if ($action === 'card') {
                return redirect()->away($billing->registerCard($request->user(), $s));
            }
            if ($action === 'automatic') {
                $billing->enableAutomatic($request->user(), $s);
            } elseif ($action === 'manual') {
                $billing->disableAutomatic($request->user(), $s);
            } elseif ($action === 'cancel') {
                $billing->cancelService($request->user(), $s);
            } else {
                $billing->refresh($request->user(), $s);
            }

            return redirect()->route('customers.subscription.index', ['customer' => $s->customer->domain])->with('success', match ($action) {
                'automatic' => 'Renovación automática autorizada en Flow. El período pagado solo cambia al confirmar un pago.',
                'manual' => 'Cargos automáticos desactivados. Puedes continuar pagando manualmente.',
                'cancel' => 'Baja registrada. Se conserva el período pagado y la información clínica.',
                default => 'Estado consultado y pagos conciliados con Flow.',
            });
        });
    }
}
