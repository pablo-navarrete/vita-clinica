<?php
namespace App\Services\Billing;
use App\Models\{PaymentFeeRate,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PaymentFeeService
{
    public function simulatorDefaults(): object
    {
        return DB::table('payment_fee_settings')->where('id', 1)->first();
    }

    public function saveSimulatorDefaults(User $actor, array $data): void
    {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor), 403);
        DB::transaction(function () use ($actor, $data) {
            DB::table('payment_fee_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('payment_fee_settings')->where('id', 1)->update([
                'simulator_rate_units' => $this->units((string) $data['rate_percent']),
                'simulator_vat_units' => $this->units((string) $data['vat_percent']),
                'simulator_updated_at' => now(),
                'simulator_updated_by' => $actor->id,
            ]);
        });
    }

    public function current(): ?PaymentFeeRate
    {
        return PaymentFeeRate::where('effective_from','<=',now('America/Santiago')->toDateString())->orderByDesc('effective_from')->first();
    }
    public function publish(User $actor,array $data): PaymentFeeRate
    {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor),403);
        return DB::transaction(function()use($actor,$data){
            DB::table('payment_fee_settings')->where('id',1)->lockForUpdate()->first();
            $last=PaymentFeeRate::orderByDesc('effective_from')->first();
            if($last && $data['effective_from'] <= $last->effective_from->toDateString()) {
                throw ValidationException::withMessages(['effective_from'=>'La nueva vigencia debe ser posterior a la última tarifa registrada. El historial se conserva.']);
            }
            $rate=PaymentFeeRate::create(['scenario'=>$data['scenario'],'effective_from'=>$data['effective_from'],
                'rate_units'=>$this->units($data['rate_percent']),'vat_units'=>$this->units($data['vat_percent']),
                'fixed_amount'=>$data['fixed_amount'],'note'=>$data['note'],'created_by'=>$actor->id,'created_at'=>now()]);
            if($last && ($last->rate_units!==$rate->rate_units || $last->vat_units!==$rate->vat_units || $last->fixed_amount!==$rate->fixed_amount)) {
                \App\Models\PaymentFeeAlert::create(['deduplication_key'=>hash('sha256','configured_rate:'.$rate->id),
                    'kind'=>'configured_rate_changed','payment_fee_rate_id'=>$rate->id,
                    'message'=>'Cambió una tarifa o el IVA configurado en VITA. Revisa los precios y márgenes de tus planes antes de su vigencia.',
                    'details'=>['previous_rate_id'=>$last->id,'new_rate_id'=>$rate->id,'effective_from'=>$data['effective_from'],'actor_id'=>$actor->id],
                    'created_at'=>now()]);
            }
            return $rate;
        });
    }
    private function units(string $percent): int
    {
        [$whole,$fraction]=array_pad(explode('.',$percent,2),2,'');
        return (int)$whole*10000+(int)str_pad($fraction,4,'0');
    }
    public function estimate(int $gross, PaymentFeeRate $rate): array
    {
        if($gross<0 || $gross>1000000000000) throw new \InvalidArgumentException('Importe fuera del rango del simulador.');
        $fee=intdiv($gross*$rate->rate_units+500000,1000000)+$rate->fixed_amount;
        $vat=intdiv($fee*$rate->vat_units+500000,1000000);
        return ['gross'=>$gross,'commission'=>$fee,'vat'=>$vat,'deduction'=>$fee+$vat,'net'=>$gross-$fee-$vat];
    }
}
