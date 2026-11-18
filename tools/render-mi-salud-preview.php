<?php
// Synthetic view rendering only: no inserts, no clinical reads, no real account.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\View::share('errors',new Illuminate\Support\ViewErrorBag);
$directory=storage_path('framework/testing/mi-salud-preview');
if(!is_dir($directory))mkdir($directory,0700,true);
$countries=app(App\Services\Identification\CountryCatalog::class)->all();
$types=app(App\Services\Identification\DocumentTypeRegistry::class)->available('CL');
foreach(['register','login','panel','profile','verify-token','reset'] as $page) {
    $account=in_array($page,['panel','profile'])?new App\Models\PortalAccount(['name'=>'Camila','email'=>'preview@example.invalid']):null;
    if ($account) Illuminate\Support\Facades\Auth::guard('mi_salud')->setUser($account);
    else Illuminate\Support\Facades\Auth::guard('mi_salud')->forgetUser();
    $identification=$page==='profile'?['country'=>'AR','type'=>'passport','display'=>'000PREVIEW']:null;
    file_put_contents($directory.'/'.$page.'.html',view('mi-salud.page',compact('page','account','countries','types','identification'))->render());
}
echo "Synthetic portal previews rendered.\n";
