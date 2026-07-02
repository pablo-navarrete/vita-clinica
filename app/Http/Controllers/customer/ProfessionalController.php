<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use App\Models\Professional;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
            return view('customer.professional.index');
    }

  

    // datatable profesional
    public function getProfessional()
    {
        $query = Professional::query()
            ->where('customer_id', app('customer')->id);

        return DataTables::of($query)
            ->addIndexColumn() //contador automático
            ->addColumn('actions', function ($row) {
                return '';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

       // crear professional
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'specialty'          => 'required|string|max:255',
            'status'        => 'required|in:0,1'
        ], [
            'name.required' => 'El nombre de Profesional es obligatorio',
            'specialty.required' => 'Especialidad es obligatorio',
            'status.required' => 'Debe seleccionar un estado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }


        $professional = Professional::create([
            'name'           => $request->name,
            'specialty'      => $request->specialty,
            'status'         => $request->status,
            'customer_id' => app('customer')->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $professional
        ]);
    }

    // Actualizar profesional
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ], [
            'name.required' => 'El nombre del profesional es obligatorio',
            'specialty.required' => 'Especialidad es obligatorio',
            'status.required' => 'Debe seleccionar un estado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $id = $request->route('id');

        $professional = Professional::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $professional->update([
            'name' => $request->name,
            'specialty'=>$request->specialty,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'data' => $professional
        ]);
    }

    //cambiar estado
    public function toggleStatus(Request $request)
    {
        $id = $request->route('id');

        $professional = Professional::where('id', $id)->firstOrFail();

        $professional->update([
            'status' => $request->status
        ]);

        return response()->json(['success' => true]);
    }

   public function destroy(Request $request)
    {
        $id = $request->route('id');

        $professional = Professional::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $professional->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profesional eliminado permanentemente'
        ]);
    }
   

 
}
