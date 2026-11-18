<?php
namespace App\Http\Controllers\MiSalud;

use App\Http\Controllers\Controller;
use App\Services\MiSalud\PortalBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class BookingAuthorizationController extends Controller
{
    public function show(Request $request,string $intent,PortalBookingService $bridge)
    {
        try {
            $row=$bridge->intent($intent);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            if ($error->getStatusCode()!==410) throw $error;
            $row=\Illuminate\Support\Facades\DB::table('portal_booking_authorizations')->where('id',$intent)->first();
            if (!$row) throw $error;
            // Return to the bound clinic session, where the original selection can be recovered safely.
            return redirect()->away($bridge->callback($row));
        }
        if (!Auth::guard('mi_salud')->check()) {
            $request->session()->put('portal_booking_intent',$intent);
            return redirect()->route('mi-salud.login');
        }
        $account=Auth::guard('mi_salud')->user()->fresh();
        abort_unless($account->active && (int)$account->session_version===(int)$request->session()->get('portal_session_version'),403);
        if (!$account->email_verified_at) {
            $request->session()->put('portal_booking_intent',$intent);
            return redirect()->route('mi-salud.verify.notice');
        }
        $request->session()->forget('portal_booking_intent');
        return response()->view('mi-salud.booking-authorization',[
            'clinic'=>\App\Models\Customer::findOrFail($row->customer_id),
            'cancel'=>$bridge->callback($row),'intent'=>$intent,
        ])->header('Cache-Control','no-store');
    }
    public function approve(Request $request,string $intent,PortalBookingService $bridge)
    {
        $request->validate(['authorize'=>'accepted']);
        $account=Auth::guard('mi_salud')->user();
        abort_unless($account && (int)$account->session_version===(int)$request->session()->get('portal_session_version'),403);
        $bridge->authorize($intent,$account);
        $request->session()->forget('portal_booking_intent');
        return redirect()->away($bridge->callback($bridge->intent($intent)),303)
            ->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
}
