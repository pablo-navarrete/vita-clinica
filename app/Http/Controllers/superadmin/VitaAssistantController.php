<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\ClinicBranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VitaAssistantController extends Controller
{
    public function ask(Request $request)
    {
        $user=Auth::guard('web')->user()?->fresh();
        abort_unless($request->getHost()===config('app.domain') && $user && $user->customer_id===null && (int)$user->status===1
            && ClinicBranchContext::isSuperAdministrator($user),403);
        $data=$request->validate(['message'=>'required|string|max:1200']);
        $message=Str::ascii(mb_strtolower($data['message']));
        $response=['context'=>'superadmin','reply'=>'Soy Viti, tu asistente del superadmin VITA. Puedo consultar cuántos clientes nuevos se registraron este mes.',
            'actions'=>[['label'=>'Ver clientes','url'=>route('superadmin.customer')]]];
        $customers=preg_match('/\b(clientes?|clinicas?|centros?)\b/',$message);
        $count=preg_match('/cuant|cantidad|numero|nuev|registr|cread|ingres|altas/',$message);
        $otherPeriod=preg_match('/\b(ayer|hoy|semana|anual|ano|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|[12][0-9]{3})\b|mes (pasado|anterior|proximo)|ultimos? (meses|[0-9])/',$message);
        if ($customers && $count && !$otherPeriod) {
            $now=CarbonImmutable::now(config('app.timezone','America/Santiago'));
            $start=$now->startOfMonth();
            $total=Customer::where('created_at','>=',$start)->where('created_at','<',$start->addMonth())->count();
            $month=$start->locale('es')->translatedFormat('F \d\e Y');
            $response['reply']=$total===1 ? "En {$month} se registró 1 cliente nuevo en VITA."
                : "En {$month} se registraron {$total} clientes nuevos en VITA.";
            $response['reply'].=' Incluye clientes activos e inactivos, según su fecha de creación.';
            $response['summary']=['new_customers'=>$total,'year'=>$start->year,'month'=>$start->month,'timezone'=>$now->timezoneName];
        } elseif ($customers && $otherPeriod) {
            $response['reply']='Por ahora puedo consultar los clientes registrados en el mes actual. Pregúntame: ¿Cuántos clientes nuevos hay este mes?';
        }
        return response()->json($response)->header('Cache-Control','private, no-store');
    }
}
