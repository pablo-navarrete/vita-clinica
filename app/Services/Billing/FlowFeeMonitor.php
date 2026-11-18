<?php
namespace App\Services\Billing;
use App\Models\{FlowPaymentObservation,PaymentFeeAlert,PaymentFeeRate};
use Illuminate\Support\{Carbon,Facades\Cache,Facades\DB};
class FlowFeeMonitor
{
    public function __construct(private FlowReadClient $client,private PaymentFeeService $fees) {}
    public function sync(string $date): int
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !Carbon::hasFormat($date,'Y-m-d') || $date>now('America/Santiago')->toDateString()) throw new \InvalidArgumentException('Fecha de consulta inválida.');
        $lock=Cache::lock('flow-fees:'.config('flow.environment').':'.$this->client->accountHash().':'.$date,3600);
        if(!$lock->get()) throw new \RuntimeException('Ya existe una sincronización para esa fecha.');
        $count=0;
        try {
            for($page=0;$page<100;$page++) {
                $result=$this->client->transactions($date,$page*100);
                foreach($result['data'] as $payment) {
                    if(!is_array($payment)) throw new \RuntimeException('Formato de pago inválido.');
                    if(!in_array($payment['status']??null,[2,'2'],true)) continue;
                    $this->record($payment); $count++;
                }
                if(!$result['hasMore']) return $count;
                if(!$result['data']) throw new \RuntimeException('Paginación vacía de Flow. Reintenta la consulta.');
            }
            throw new \RuntimeException('La consulta superó el límite de páginas. Requiere conciliación adicional.');
        } finally { $lock->release(); }
    }
    private function amount(mixed $value,bool $signed=false): int
    {
        $value=(string)$value;
        if(!preg_match($signed?'/^-?\d{1,13}(?:\.0+)?$/':'/^\d{1,13}(?:\.0+)?$/',$value) || abs((float)$value)>1000000000000) throw new \RuntimeException('Importe CLP no soportado en la respuesta de Flow.');
        return (int)$value;
    }
    private function record(array $p): void
    {
        $d=$p['paymentData']??[];
        if(($p['currency']??null)!=='CLP' || ($d['currency']??null)!=='CLP') throw new \RuntimeException('La conciliación actual solo admite pagos CLP sin conversión de moneda.');
        $date=substr((string)($d['date']??''),0,10);
        $order=(string)($p['flowOrder']??''); $commerce=(string)($p['commerceOrder']??''); $media=(string)($d['media']??'');
        if(!preg_match('/^\d{1,80}$/',$order) || strlen($commerce)>160 || strlen($media)>80 || !Carbon::hasFormat($date,'Y-m-d')) throw new \RuntimeException('Identidad o fecha de pago inválida en Flow.');
        $gross=$this->amount($d['amount']??null); $original=$this->amount($p['amount']??null);
        $fee=$this->amount($d['fee']??null); $net=$this->amount($d['balance']??null,true);
        if($gross!==$original || $net>$gross) throw new \RuntimeException('El importe o saldo de Flow requiere revisión.');
        $transfer=filled($d['transferDate']??null)?$d['transferDate']:null;
        if($transfer && !Carbon::hasFormat($transfer,'Y-m-d')) throw new \RuntimeException('Fecha de abono inválida.');
        $data=['environment'=>config('flow.environment'),'account_hash'=>$this->client->accountHash(),'flow_order'=>$order,
            'commerce_order'=>$commerce,'paid_on'=>$date,'media'=>$media,'currency'=>'CLP','gross'=>$gross,'reported_fee'=>$fee,'net'=>$net,'transfer_on'=>$transfer];
        $fingerprint=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
        DB::transaction(function()use($data,$fingerprint,$date,$gross,$net,$media){
            $observation=FlowPaymentObservation::firstOrCreate(['fingerprint'=>$fingerprint],$data+['created_at'=>now()]);
            $rate=PaymentFeeRate::where('effective_from','<=',$date)->orderByDesc('effective_from')->first();
            if(!$rate) return;
            $expected=$this->fees->estimate($gross,$rate); $actual=$gross-$net;
            if(abs($actual-$expected['deduction'])<=1) return; // One-peso rounding tolerance.
            $band=$gross>0?intdiv($actual*10000+intdiv($gross,2),$gross):$actual;
            $key=hash('sha256',implode('|',[$data['environment'],$data['account_hash'],$rate->id,$media,$band,'difference']));
            PaymentFeeAlert::firstOrCreate(['deduplication_key'=>$key],[
                'kind'=>'flow_difference','environment'=>$data['environment'],'account_hash'=>$data['account_hash'],
                'observation_id'=>$observation->id,'payment_fee_rate_id'=>$rate->id,
                'message'=>'Revisa la tarifa Flow y los precios de tus planes: el descuento observado difiere de la estimación. Puede deberse a tarifa, IVA, medio de pago, cuotas u otros ajustes; no confirma por sí solo un cambio de porcentaje.',
                'details'=>['flow_order'=>$data['flow_order'],'media'=>$media,'gross'=>$gross,'expected_deduction'=>$expected['deduction'],'observed_deduction'=>$actual,'observed_net'=>$net],
                'created_at'=>now(),
            ]);
        });
    }
}
