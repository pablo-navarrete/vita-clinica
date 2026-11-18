<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan,DB};

try {
    $config=config('database.connections.'.config('database.default'));
    if (!$app->environment('local') || ($config['database']??null)!=='vita_clinica' || !in_array($config['host']??null,['127.0.0.1','localhost','::1'],true)) throw new RuntimeException('Local development only.');
    $source=function() {
        $result=[];
        foreach (['customers','users','patients','medical_records','appointments','encounters','encounter_document_versions','encounter_signatures','portal_accounts','portal_account_identifiers','patient_identifiers'] as $table) {
            $result[$table]=['count'=>DB::table($table)->count(),'checksum'=>DB::selectOne('CHECKSUM TABLE `'.$table.'`')->Checksum];
        }
        return $result;
    };
    $before=$source();
    if (Artisan::call('mi-salud:reconcile-patients',['--index-existing'=>true,'--limit'=>1000])!==0) throw new RuntimeException('Reconciliation failed.');
    if ($source()!==$before) throw new RuntimeException('A source table changed; stop for review.');
    echo json_encode(['source_tables_unchanged'=>true,'pending_requests'=>DB::table('portal_link_sync_requests')->whereNull('completed_at')->count(),
        'links_by_status'=>DB::table('portal_patient_links')->selectRaw('status,COUNT(*) as count')->groupBy('status')->get(),
        'clinical_access_granted'=>DB::table('portal_patient_links')->where('clinical_access',true)->count()],JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable) { fwrite(STDERR,"Reconciliation stopped; inspect without exposing personal data.\n");exit(1); }
