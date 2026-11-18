<?php

namespace App\Http\Controllers;

use App\Models\CustomerSubscription;
use App\Models\FlowBillingAgreement;
use App\Services\Billing\ClinicBillingService;
use Illuminate\Http\Request;

class FlowBillingCallbackController extends Controller
{
    private function token(Request $request): string
    {
        return $request->validate(['token' => 'required|string|regex:/^[a-zA-Z0-9_-]{10,200}$/'])['token'];
    }

    private function portal(CustomerSubscription $s)
    {
        return redirect()->route('customers.subscription.index', ['customer' => $s->customer->domain], 303)->header('Cache-Control', 'no-store');
    }

    public function card(Request $request, ClinicBillingService $billing)
    {
        $attempt = $billing->confirmCard($this->token($request));
        $s = CustomerSubscription::where('customer_id', $attempt->billingCustomer->customer_id)->firstOrFail();

        return $this->portal($s);
    }

    public function payment(Request $request, ClinicBillingService $billing)
    {
        try {
            $billing->paymentCallback($this->token($request));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Verificación pendiente; reintenta la notificación.'], 503);
        }

        return response()->json(['received' => true]);
    }

    public function returned(Request $request, ClinicBillingService $billing)
    {
        $s = $billing->paymentCallback($this->token($request));

        return $this->portal($s);
    }

    public function recurring(Request $request, ClinicBillingService $billing, FlowBillingAgreement $agreement)
    {
        try {
            $billing->recurringCallback($agreement, $this->token($request));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Verificación pendiente; reintenta la notificación.'], 503);
        }

        return response()->json(['received' => true]);
    }
}
