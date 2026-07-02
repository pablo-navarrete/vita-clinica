<?php

namespace App\Http\Controllers\superadmin;

use Illuminate\Http\Request;
use App\Models\Customer;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class CustomerController extends Controller
{
    public function index()
    {
        return view('superadmin.customer.index');
    }

    // datatable clientes
    public function getCustomer()
    {
        return DataTables::of(Customer::query())->make(true);
    }

    // crear cliente
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'legal_name'    => 'required|string|max:255',
            'status'        => 'required|in:0,1',
            'rut'           => 'required|string|max:50',
            'email'         => 'required|email|max:255',
            'phone'         => 'required|string|max:50',
            'address'       => 'nullable|string|max:255',
            'holder'        => 'required|string|max:255',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'legal_name.required' => 'La Razón Social es obligatoria',
            'status.required' => 'Debe seleccionar un estado',
            'rut.required' => 'El RUT es obligatorio',
            'email.required' => 'El email es obligatorio',
            'holder.required' => 'El sostenedor es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // generar dominio limpio
        $domain = strtolower($request->name);
        $domain = preg_replace('/[^a-z0-9]/', '', $domain);

        $domain .= '.' . config('app.domain');

        // evitar duplicados de dominio
        if (Customer::where('domain', $domain)->exists()) {
            return response()->json([
                'errors' => [
                    'name' => ['Ya existe un cliente con ese dominio']
                ]
            ], 422);
        }


        $customer = Customer::create([
            'name'           => $request->name,
            'rut'            => $request->rut,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'address'        => $request->address,
            'holder'         => $request->holder,
            'legal_name'     => $request->legal_name,
            'domain'         => $domain,
            'status'         => $request->status,

            // SaaS defaults
            'payment_status' => 1,
            'paid_at'        => now(),
            'expires_at'     => now()->addMonth(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    // Actualizar cliente
    public function update(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [

            'name' => 'required|string|max:255',
            'legal_name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
            'rut' => 'required|string|max:50|unique:customers,rut,' . $customer->id,
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'holder' => 'required|string|max:255',

        ], [

            'name.required' => 'El nombre es obligatorio',
            'legal_name.required' => 'La Razón Social es obligatoria',
            'status.required' => 'Debe seleccionar un estado',
            'rut.required' => 'El RUT es obligatorio',
            'rut.unique' => 'El RUT ya existe',
            'email.required' => 'El email es obligatorio',
            'email.unique' => 'El email ya existe',
            'holder.required' => 'El sostenedor es obligatorio',

        ]);

        if ($validator->fails()) {

            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // GENERAR DOMINIO
        $domain = strtolower($request->name);
        $domain = preg_replace('/[^a-z0-9]/', '', $domain);

        $domain .= '.' . config('app.domain');

        // VALIDAR DOMINIO EXCLUYENDO EL REGISTRO ACTUAL
        $exists = Customer::where('domain', $domain)
            ->where('id', '!=', $customer->id)
            ->exists();

        if ($exists) {

            return response()->json([
                'errors' => [
                    'name' => ['Ya existe un cliente con ese dominio']
                ]
            ], 422);
        }

        $customer->update([

            'name' => $request->name,
            'legal_name' => $request->legal_name,
            'rut' => $request->rut,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'holder' => $request->holder,
            'domain' => $domain,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    //cambiar estado
    public function toggleStatus(Request $request, Customer $customer)
    {
        $customer->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true
        ]);
    }

    //cambiar estado pago
    public function togglePaymentStatus(Request $request, Customer $customer)
    {
        $isPaid = $request->boolean('payment_status');

        $data = [
            'payment_status' => $isPaid,
        ];

        if ($isPaid) {
            $data['paid_at'] = now();
            $data['expires_at'] = now()->addMonth();
        }

        $customer->update($data);

        return response()->json([
            'success' => true
        ]);
    }

    //eliminar cliente softdelete (logico)
    public function destroy(Customer $customer)
    {
        $customer->update([
            'status' => 0
        ]);

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente'
        ]);
    }

    //papelera clientes
    public function getTrash()
    {
        return DataTables::of(
            Customer::onlyTrashed()
        )->make(true);
    }

    public function restore($id)
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);

        $customer->restore();

        return response()->json([
            'success' => true,
            'message' => 'Restaurado correctamente'
        ]);
    }
}
