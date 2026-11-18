<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache,Notification};
use App\Models\PaymentFeeAlert;
class SendPaymentFeeAlerts extends Command
{
    protected $signature='flow:send-fee-alerts';
    protected $description='Envía avisos financieros pendientes al correo configurado';
    public function handle(): int
    {
        $email=config('flow.alert_email');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) { $this->info('Configura FLOW_ALERT_EMAIL para recibir avisos por correo.'); return self::SUCCESS; }
        $lock=Cache::lock('flow-fee-alert-email',600);
        if(!$lock->get()) return self::SUCCESS;
        try {
            $account=app(\App\Services\Billing\FlowReadClient::class)->accountHash();
            $alerts=PaymentFeeAlert::whereNull('emailed_at')->whereNull('acknowledged_at')->where(function($q)use($account){
                $q->whereNull('environment')->orWhere(fn($q)=>$q->where('environment',config('flow.environment'))->where('account_hash',$account));
            })->orderBy('id')->limit(50)->get();
            foreach($alerts as $alert) {
                Notification::route('mail',$email)->notify(new \App\Notifications\PaymentFeeReview($alert->message));
                $alert->update(['emailed_at'=>now()]);
            }
            $this->info('Avisos enviados: '.$alerts->count()); return self::SUCCESS;
        } catch(\Throwable $e) { $this->error('No se pudieron enviar todos los avisos. Revisa la configuración de correo.'); return self::FAILURE; }
        finally { $lock->release(); }
    }
}
