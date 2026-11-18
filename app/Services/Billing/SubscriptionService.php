<?php
namespace App\Services\Billing;

use App\Models\{Customer,CustomerSubscription,BillingPlan,BillingPlanPrice,SubscriptionEvent,SubscriptionPayment,SubscriptionChange,User};
use Illuminate\Support\{Carbon,Facades\DB};
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public const PAYMENT_MODES = ['automatic'=>'Automática con Flow','manual'=>'Manual','unclassified'=>'Pendiente de clasificar'];

    public function classification(CustomerSubscription $s): array
    {
        $agreement = $s->openBillingAgreement;
        $mode = $agreement ? $agreement->collection_mode : ($s->payment_mode ?? 'unclassified');
        $contract = $s->cancelled_at ? 'Cancelada' : ($s->cancel_at_period_end
            ? ($s->access_ends_on && Carbon::today('America/Santiago')->lt($s->access_ends_on) ? 'Cancelación programada' : 'Cancelada')
            : ($s->contract_status === 'legacy_transition' ? 'En transición' : 'Pendiente de aceptación'));
        $today = Carbon::today('America/Santiago');
        $financial = ($s->paid_through_on && $today->lt($s->paid_through_on)) ? 'Al día'
            : ($today->lt($s->starts_on) || ($s->trial_ends_on && $today->lt($s->trial_ends_on)) ? 'Sin pago exigible'
            : ($today->lt($s->next_renewal_on) ? 'Sin pago exigible' : 'Pago pendiente'));
        if ($financial === 'Pago pendiente' && $today->gte($s->next_renewal_on->copy()->addDays((int)$s->price->version->grace_days))) $financial = 'Vencida';
        return [
            'payment_mode_label'=>self::PAYMENT_MODES[$mode] ?? self::PAYMENT_MODES['unclassified'],
            'contract_label'=>$contract,
            'financial_label'=>$financial,
            'access_label'=>'Sin restricción comercial (transición)',
            'authorization_label'=>!$agreement ? 'Sin acuerdo Flow autorizado' : match($agreement->state) {
                'active' => $agreement->collection_mode === 'automatic' ? 'Acuerdo automático activo' : 'Acuerdo en pago manual',
                'review'=>'Acuerdo en revisión', 'ending'=>'Cierre pendiente', default=>'Configuración pendiente',
            },
        ];
    }
    public const STATES=['scheduled'=>'Programada','trial'=>'En prueba','active'=>'Período pagado','grace'=>'En gracia',
        'overdue'=>'Pago pendiente','ending'=>'Cancelación programada','cancelled'=>'Cancelada'];
    private function authorize(User $actor): void { abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor),403); }
    private function assertNoGatewayWork(CustomerSubscription $s): void
    {
        abort_if(\App\Models\FlowBillingAgreement::where('customer_subscription_id',$s->id)->whereIn('state',['creating','active','ending','review'])->exists()
            || \App\Models\FlowCheckoutOrder::where('customer_subscription_id',$s->id)->whereIn('state',['prepared','sending','pending'])->exists(),409,
            'Hay una operación o contrato vigente en Flow. Concílialo desde Mi suscripción antes de modificar condiciones o registrar otro pago.');
    }
    private function event(User $actor,CustomerSubscription $subscription,string $action,array $details): void {
        SubscriptionEvent::create(['customer_subscription_id'=>$subscription->id,'actor_id'=>$actor->id,'action'=>$action,'details'=>$details,'created_at'=>now()]);
    }
    public function quote(BillingPlanPrice $price,int $branches): array
    {
        $version=$price->version; abort_unless($version && $version->status==='published',422,'Selecciona condiciones comerciales confirmadas.');
        abort_unless(hash_equals($version->sha256,hash('sha256',$version->snapshot)),409,'No se pudo verificar el plan.');
        $terms=json_decode($version->snapshot,true,512,JSON_THROW_ON_ERROR);
        $limit=collect($terms['limits'])->firstWhere('code','branches');
        if($branches<1 || ($limit && $limit['value']!==null && $branches>(int)$limit['value'])) {
            throw ValidationException::withMessages(['branch_quantity'=>'La cantidad de sedes excede lo permitido por el plan.']);
        }
        $extra=max(0,$branches-(int)$terms['included_branches']);
        $amount=(int)$price->amount+$extra*(int)$price->additional_branch_amount;
        if(!is_int($amount) || $amount>9007199254740991) {
            throw ValidationException::withMessages(['branch_quantity'=>'El total supera el importe máximo soportado.']);
        }
        return ['base'=>(int)$price->amount,'included_branches'=>(int)$terms['included_branches'], 'branch_quantity'=>$branches,
            'additional_quantity'=>$extra,'additional_unit_amount'=>(int)$price->additional_branch_amount,
            'amount'=>$amount,'currency'=>'CLP',
            'plan_name'=>$terms['plan_name'],'version_id'=>$version->id,'version_hash'=>$version->sha256,'interval'=>$price->interval];
    }
    private function selectable(int $priceId): BillingPlanPrice
    {
        $price=BillingPlanPrice::with('version.plan')->findOrFail($priceId);
        $plan=BillingPlan::withTrashed()->lockForUpdate()->findOrFail($price->version->billing_plan_id);
        abort_unless(!$plan->trashed() && $plan->active && (int)$plan->currentVersion?->id===(int)$price->billing_plan_version_id,422,'Selecciona un precio vigente de un plan activo.');
        return $price;
    }
    private function locked(CustomerSubscription $subscription,int $revision): CustomerSubscription
    {
        $locked=CustomerSubscription::lockForUpdate()->findOrFail($subscription->id);
        if((int)$locked->revision!==$revision) throw ValidationException::withMessages(['subscription'=>'La suscripción cambió. Recarga sus datos antes de continuar.']);
        return $locked;
    }
    public function create(User $actor,array $data): CustomerSubscription
    {
        $this->authorize($actor);
        if (!in_array($data['payment_mode'] ?? 'automatic', ['automatic','manual'], true)) {
            throw ValidationException::withMessages(['payment_mode'=>'Selecciona una modalidad automática o manual.']);
        }
        return DB::transaction(function()use($actor,$data){
            $customer=Customer::lockForUpdate()->findOrFail($data['customer_id']);
            if(CustomerSubscription::where('customer_id',$customer->id)->exists()) throw ValidationException::withMessages(['customer_id'=>'Esta clínica ya tiene su suscripción principal.']);
            $price=$this->selectable((int)$data['billing_plan_price_id']);
            $this->quote($price,(int)$data['branch_quantity']);
            $start=Carbon::parse($data['starts_on']);
            $trial=($data['use_trial']??false) && (int)$price->version->trial_days>0 ? $start->copy()->addDays((int)$price->version->trial_days) : null;
            $due=$trial??$start;
            $s=CustomerSubscription::create(['customer_id'=>$customer->id,'billing_plan_price_id'=>$price->id,'branch_quantity'=>$data['branch_quantity'],
                'starts_on'=>$start->toDateString(),'trial_ends_on'=>$trial?->toDateString(),'next_renewal_on'=>$due->toDateString(),
                'anchor_day'=>$due->day,'anchor_month'=>$due->month,'created_by'=>$actor->id,'revision'=>1,
                'payment_mode'=>$data['payment_mode'] ?? 'automatic','contract_status'=>'pending_acceptance']);
            $this->event($actor,$s,'created',['note'=>$data['note'] ?? null,'price_id'=>$price->id,'starts_on'=>$s->starts_on->toDateString(),'trial_ends_on'=>$trial?->toDateString(),'payment_mode'=>$s->payment_mode,'contract_status'=>$s->contract_status]);
            return $s;
        });
    }
    public function nextEnd(CustomerSubscription $s,Carbon $start,string $interval): Carbon
    {
        if($interval==='month') {
            $next=$start->copy()->startOfMonth()->addMonth();
            return $next->day(min((int)$s->anchor_day,$next->daysInMonth));
        }
        $next=Carbon::create($start->year+1,(int)$s->anchor_month,1);
        return $next->day(min((int)$s->anchor_day,$next->daysInMonth));
    }
    public function upcoming(CustomerSubscription $s): array
    {
        $change=$s->pendingChange;
        $due=$s->next_renewal_on->copy();
        $eligible=$change && $change->effective_on->lte($due);
        $price=$eligible ? $change->price : $s->price;
        $branches=$eligible ? (int)$change->branch_quantity : (int)$s->branch_quantity;
        return $this->quote($price,$branches)+['price_id'=>$price->id,'period_start'=>$due->toDateString(),
            'period_end'=>$this->nextEnd($s,$due,$price->interval)->toDateString(),'change_id'=>$eligible?$change->id:null];
    }
    public function state(CustomerSubscription $s): string
    {
        $today=Carbon::today('America/Santiago');
        if($s->cancelled_at) return 'cancelled';
        if($s->cancel_at_period_end) return $s->access_ends_on && $today->lt($s->access_ends_on) ? 'ending':'cancelled';
        if($today->lt($s->starts_on)) return 'scheduled';
        if($s->paid_through_on && $today->lt($s->paid_through_on)) return 'active';
        if($s->trial_ends_on && $today->lt($s->trial_ends_on)) return 'trial';
        $grace=(int)$s->price->version->grace_days;
        return $grace>0 && $today->lt($s->next_renewal_on->copy()->addDays($grace))?'grace':'overdue';
    }
    public function recordPayment(User $actor,CustomerSubscription $subscription,array $data): void
    {
        $this->authorize($actor);
        DB::transaction(function()use($actor,$subscription,$data){
            $s=$this->locked($subscription,(int)$data['revision']);
            $this->assertNoGatewayWork($s);
            abort_if($s->cancelled_at || $s->cancel_at_period_end,409,'No se pueden renovar suscripciones canceladas.');
            $quote=$this->upcoming($s);
            if(Carbon::parse($data['paid_on'])->lt(Carbon::parse($quote['period_start']))) {
                throw ValidationException::withMessages(['paid_on'=>'En esta etapa se registran pagos de períodos ya iniciados; no se admiten anticipos.']);
            }
            if((int)$data['amount']!==$quote['amount']) throw ValidationException::withMessages(['amount'=>'El importe debe coincidir con el plan y las sedes del próximo período.']);
            if($s->payments()->where('reference',$data['reference'])->exists()) throw ValidationException::withMessages(['reference'=>'La referencia de pago ya fue registrada.']);
            $s->payments()->create(['customer_id'=>$s->customer_id,'billing_plan_price_id'=>$quote['price_id'],
                'period_start'=>$quote['period_start'],'period_end'=>$quote['period_end'],'paid_on'=>$data['paid_on'],
                'amount'=>$quote['amount'],'currency'=>'CLP','method'=>'manual','reference'=>$data['reference'],'note'=>$data['note'],
                'breakdown'=>$quote,'recorded_by'=>$actor->id,'created_at'=>now()]);
            if($quote['change_id']) {
                $change=SubscriptionChange::lockForUpdate()->findOrFail($quote['change_id']);
                $change->update(['status'=>'applied','applied_at'=>now()]);
                $s->billing_plan_price_id=$quote['price_id']; $s->branch_quantity=$quote['branch_quantity'];
            }
            $s->paid_through_on=$quote['period_end']; $s->next_renewal_on=$quote['period_end']; $s->revision++;
            $s->save();
            $this->event($actor,$s,'manual_payment_recorded',['reference'=>$data['reference'],'breakdown'=>$quote]);
        });
    }
    public function scheduleChange(User $actor,CustomerSubscription $subscription,array $data): void
    {
        $this->authorize($actor);
        DB::transaction(function()use($actor,$subscription,$data){
            $s=$this->locked($subscription,(int)$data['revision']);
            $this->assertNoGatewayWork($s);
            abort_if($s->cancelled_at || $s->cancel_at_period_end,409,'La suscripción está cancelada.');
            $price=$this->selectable((int)$data['billing_plan_price_id']); $quote=$this->quote($price,(int)$data['branch_quantity']);
            abort_unless($price->interval===$s->price->interval,422,'El cambio entre mensual y anual requiere un ajuste de ciclo que se incorporará posteriormente.');
            if($s->pendingChange) throw ValidationException::withMessages(['subscription'=>'Ya existe un cambio pendiente. Cancélalo antes de programar otro.']);
            $change=$s->changes()->create(['billing_plan_price_id'=>$price->id,'branch_quantity'=>$data['branch_quantity'],
                'effective_on'=>$s->next_renewal_on->toDateString(),'status'=>'pending','reason'=>$data['reason'],'created_by'=>$actor->id]);
            $s->revision++; $s->save();
            $this->event($actor,$s,'change_scheduled',['change_id'=>$change->id,'effective_on'=>$change->effective_on->toDateString(),'quote'=>$quote,'reason'=>$data['reason']]);
        });
    }
    public function cancelChange(User $actor,CustomerSubscription $subscription,int $revision): void
    {
        $this->authorize($actor);
        DB::transaction(function()use($actor,$subscription,$revision){
            $s=$this->locked($subscription,$revision); $change=$s->pendingChange; abort_unless($change,409);
            $change->update(['status'=>'cancelled']); $s->revision++; $s->save();
            $this->event($actor,$s,'change_cancelled',['change_id'=>$change->id]);
        });
    }
    public function cancel(User $actor,CustomerSubscription $subscription,array $data): void
    {
        $this->authorize($actor);
        DB::transaction(function()use($actor,$subscription,$data){
            $s=$this->locked($subscription,(int)$data['revision']);
            $this->assertNoGatewayWork($s);
            abort_if($s->cancelled_at || $s->cancel_at_period_end,409,'La cancelación ya está registrada.');
            $s->cancel_at_period_end=true;
            $s->access_ends_on=$s->paid_through_on??$s->trial_ends_on??$s->starts_on;
            if($s->pendingChange) $s->pendingChange->update(['status'=>'cancelled']);
            $s->revision++; $s->save();
            $this->event($actor,$s,'cancellation_scheduled',['effective_on'=>$s->access_ends_on->toDateString(),'reason'=>$data['reason']]);
        });
    }
}
