<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Insurance;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class InsuranceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $canManageCentralConfiguration = $this->canManageCentralConfiguration();
        return view('customer.insurances.index', compact('canManageCentralConfiguration'));
    }

    // datatable Insurance
    public function getInsurances()
    {
        $query = Insurance::query()
            ->where('customer_id', app('customer')->id);

        return DataTables::of($query)
            ->addIndexColumn()

            ->addColumn('actions', function ($row) {

                if (!$this->canManageCentralConfiguration()) return '';

                $isUsed = Patient::where('insurance_id', $row->id)
                    ->where('customer_id', app('customer')->id)
                    ->exists();

                $actions = '
                    <button class="edit-insurances-btn text-blue-500 mr-2">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                ';

                if (!$isUsed) {
                    $actions .= '
                        <button class="delete-btn text-red-500 mr-2">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    ';
                }

                return $actions;
            })

            ->rawColumns(['actions'])
            ->make(true);
    }

    // crear Insurance
    public function store(Request $request)
    {
        abort_unless($this->canManageCentralConfiguration(), 403, 'El catálogo de previsiones se administra desde la sede principal con permisos centrales.');
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'status'        => 'required|in:0,1'
        ], [
            'name.required' => 'El nombre de Previsión es obligatorio',
            'status.required' => 'Debe seleccionar un estado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }


        $insurance = Insurance::create([
            'name'           => $request->name,
            'status'         => $request->status,
            'customer_id' => app('customer')->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $insurance
        ]);
    }

    // Actualizar asistente
    public function update(Request $request)
    {
        abort_unless($this->canManageCentralConfiguration(), 403, 'El catálogo de previsiones se administra desde la sede principal con permisos centrales.');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ], [
            'name.required' => 'El nombre de asistente es obligatorio',
            'status.required' => 'Debe seleccionar un estado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $id = $request->route('id');

        $insurance = Insurance::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $insurance->update([
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'data' => $insurance
        ]);
    }

    //cambiar estado
    public function toggleStatus(Request $request)
    {
        abort_unless($this->canManageCentralConfiguration(), 403, 'El catálogo de previsiones se administra desde la sede principal con permisos centrales.');
        $id = $request->route('id');

        $insurance = Insurance::where('id', $id)->firstOrFail();

        $insurance->update([
            'status' => $request->status
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        abort_unless($this->canManageCentralConfiguration(), 403, 'El catálogo de previsiones se administra desde la sede principal con permisos centrales.');
        $id = $request->route('id');

        $insurance = Insurance::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $insurance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asistente eliminado permanentemente'
        ]);
    }

    private function canManageCentralConfiguration(): bool
    {
        $user = request()->user();
        $customerId = (int) app('customer')->id;
        $branchId = \App\Support\ClinicBranchContext::defaultBranch($user, $customerId)?->id;

        return $user && $branchId
            && \App\Support\ClinicBranchContext::canAdministerAllBranches($user, $customerId)
            && \App\Support\ClinicBranchContext::hasPermissionInBranch($user, $customerId, (int) $branchId, 'clinic_configuration.manage');
    }
}
