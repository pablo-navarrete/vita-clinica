<?php
namespace App\Http\Controllers\superadmin\Billing;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SubscriptionPolicyController extends Controller
{
    public function __construct(){ $this->middleware(function($request,$next){
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($request->user()),403);
        return $next($request);
    }); }
    public function edit(){ return view('superadmin.billing.conditions',[
        'policy'=>DB::table('subscription_policy_versions')->orderByDesc('id')->first(),
    ]); }
    public function update(Request $request){
        $data=$request->validate(['title'=>'required|string|max:160','body'=>'required|string|max:50000','revision'=>'required|integer|min:0']);
        DB::transaction(function()use($request,$data){
            // Existing singleton serializes publication, including the first version.
            DB::table('payment_fee_settings')->where('id',1)->lockForUpdate()->first();
            $last=DB::table('subscription_policy_versions')->orderByDesc('id')->first();
            if((int)($last->id??0)!==(int)$data['revision']) throw \Illuminate\Validation\ValidationException::withMessages(['body'=>'Las condiciones cambiaron. Recarga antes de publicar.']);
            DB::table('subscription_policy_versions')->insert(['title'=>$data['title'],'body'=>$data['body'],'created_by'=>$request->user()->id,'created_at'=>now()]);
        });
        return back()->with('success','Condiciones generales actualizadas.');
    }
}
