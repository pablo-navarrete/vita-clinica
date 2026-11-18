<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\{Patient, PortalClinicalGrant};
use App\Policies\PortalClinicalAccessPolicy;
use App\Services\MiSalud\PortalClinicalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PortalClinicalAccessController extends Controller
{
    private function patient(Request $request, int $id): Patient
    {
        $patient=Patient::where('customer_id',app('customer')->id)->findOrFail($id);
        abort_unless(app(PortalClinicalAccessPolicy::class)->manage($request->user(),$patient),403);
        return $patient;
    }

    public function index(Request $request, string $customer, int $id)
    {
        $patient=$this->patient($request,$id);
        $grants=PortalClinicalGrant::where('customer_id',$patient->customer_id)->where('patient_id',$patient->id)
            ->with(['account:id,name,email','approver:id,name'])->latest('approved_at')->get();
        return view('customer.patient.portal-access',compact('patient','grants'));
    }

    public function store(Request $request, string $customer, int $id, PortalClinicalAccessService $access)
    {
        $patient=$this->patient($request,$id);
        $data=$request->validate(['email'=>'required|email|max:255','verification_method'=>'required|in:in_person,verified_contact',
            'identity_confirmed'=>'required|accepted','password'=>'required|string|max:1024']);
        $access->approve($request->user(),$patient,$data);
        return redirect()->route('patients.portal-access.index',compact('customer','id'))->with('success','Acceso habilitado. El paciente puede consultar su ficha y atenciones firmadas en Mis atenciones.');
    }

    public function revoke(Request $request, string $customer, int $id, string $grant, PortalClinicalAccessService $access)
    {
        $patient=$this->patient($request,$id);
        $data=$request->validate(['password'=>'required|string|max:1024']);
        if (!Hash::check($data['password'],$request->user()->getAuthPassword())) throw ValidationException::withMessages(['password'=>'La contraseña no es correcta.']);
        $access->revoke($request->user(),$patient,$grant);
        return redirect()->route('patients.portal-access.index',compact('customer','id'))->with('success','Acceso revocado. La ficha dejó de estar disponible para esa cuenta de Mi Salud.');
    }
}
