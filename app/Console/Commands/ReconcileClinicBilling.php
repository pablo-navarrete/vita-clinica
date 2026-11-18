<?php

namespace App\Console\Commands;

use App\Models\CustomerSubscription;
use App\Models\FlowBillingAgreement;
use App\Models\FlowCheckoutOrder;
use App\Models\PaymentFeeAlert;
use App\Services\Billing\ClinicBillingService;
use App\Services\Billing\FlowReadClient;
use Illuminate\Console\Command;

class ReconcileClinicBilling extends Command
{
    protected $signature = 'flow:reconcile-subscriptions';

    protected $description = 'Concilia pagos y renovaciones; nunca genera cargos';

    public function handle(ClinicBillingService $service, FlowReadClient $client): int
    {
        if (! $client->configured()) {
            $this->info('Flow no está configurado.');

            return self::SUCCESS;
        }
        $ids = FlowCheckoutOrder::whereIn('state', ['sending', 'pending'])->pluck('customer_subscription_id')
            ->merge(FlowBillingAgreement::whereIn('state', ['creating', 'active', 'ending', 'review'])->pluck('customer_subscription_id'))->unique();
        $failed = false;
        foreach ($ids as $id) {
            $s = CustomerSubscription::find($id);
            if (! $s) {
                continue;
            }
            try {
                $service->reconcile($s);
            } catch (\Throwable $e) {
                $failed = true;
                PaymentFeeAlert::firstOrCreate(['deduplication_key' => hash('sha256', 'subscription-review:'.config('flow.environment').':'.$client->accountHash().':'.$id.':'.$s->revision)],
                    ['kind' => 'subscription_review', 'environment' => config('flow.environment'), 'account_hash' => $client->accountHash(),
                        'message' => 'Una suscripción requiere revisión de pagos o de su operación pendiente en Flow. No se repitieron cargos ni se amplió el período sin verificarlo.',
                        'details' => ['customer_subscription_id' => $id, 'customer_id' => $s->customer_id], 'created_at' => now()]);
            }
        }
        $this->info('Suscripciones revisadas: '.$ids->count());

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
