<?php
namespace App\Services\Billing;
use Illuminate\Support\Facades\Http;
class FlowReadClient
{
    public function configured(): bool
    {
        return (bool)config('flow.enabled') && in_array(config('flow.environment'),['sandbox','production'],true)
            && filled(config('flow.api_key')) && filled(config('flow.secret_key'));
    }
    public function accountHash(): string { return hash('sha256',(string)config('flow.api_key')); }
    public function transactions(string $date,int $start=0): array
    {
        if(!$this->configured()) throw new \RuntimeException('Configura FLOW_ENABLED, FLOW_ENVIRONMENT, FLOW_API_KEY y FLOW_SECRET_KEY en el servidor.');
        $params=['apiKey'=>config('flow.api_key'),'date'=>$date,'limit'=>100,'start'=>$start]; ksort($params,SORT_STRING);
        $sign=''; foreach($params as $key=>$value) $sign.=$key.$value;
        $params['s']=hash_hmac('sha256',$sign,config('flow.secret_key'));
        $base=config('flow.environment')==='production'?'https://www.flow.cl/api':'https://sandbox.flow.cl/api';
        try {
            $response=Http::connectTimeout(5)->timeout(20)->withoutRedirecting()->acceptJson()->get($base.'/payment/getTransactions',$params);
        } catch(\Throwable $e) { throw new \RuntimeException('No se pudo conectar con Flow. Intenta nuevamente.'); }
        // Never expose/log signed URLs, keys, provider error bodies or payer/card details.
        if(!$response->successful()) throw new \RuntimeException('Flow rechazó la consulta. Verifica las credenciales y el ambiente configurado.');
        $data=$response->json();
        if(!is_array($data) || !is_array($data['data']??null) || !array_key_exists('hasMore',$data)
            || !in_array($data['hasMore'],[0,1,false,true],true) || count($data['data'])>100) {
            throw new \RuntimeException('Flow devolvió una respuesta no válida. No se confirmó la sincronización.');
        }
        return $data;
    }
}
