<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\Rule;

class SpecialtyController extends Controller
{

    public function index()
    {
     
        return view('customer.specialties.index');
    }

       // datatable 
    public function getSpecialties()
    {
        $query = Specialty::query()
            ->where('customer_id', app('customer')->id);

        return DataTables::of($query)
            ->addIndexColumn() //contador automático
            ->addColumn('actions', function ($row) {
                return '';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ], [
            'name.required' => 'El nombre de la especialidad es obligatorio.',
            'status.required' => 'Debe seleccionar un estado.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $specialty = Specialty::create([
            'customer_id' => app('customer')->id,
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'data' => $specialty
        ]);
    }


      //cambiar estado
    public function toggleStatus(Request $request)
    {
        $id = $request->route('id');

        $specialty = Specialty::where('id', $id)->firstOrFail();

        $specialty->update([
            'status' => $request->status
        ]);

        return response()->json(['success' => true]);
    }


    public function update(Request $request, $id)
    {
         $id = $request->route('id');

        $specialty = Specialty::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('specialties')
                    ->where(function ($query) {
                        return $query->where('customer_id', app('customer')->id);
                    })
                    ->ignore($specialty->id),
            ],
            'status' => 'required|in:0,1',
        ], [
            'name.required' => 'El nombre de la especialidad es obligatorio.',
            'name.unique' => 'Esta especialidad ya existe.',
            'status.required' => 'Debe seleccionar un estado.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $specialty->update([
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'data' => $specialty
        ]);
    }
}
