<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use App\Models\Patient;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         return view('customer.patient.index');
    }

    // datatable
    public function getPatient()
    {
        $query = Patient::query()
            ->where('customer_id', app('customer')->id);

        return DataTables::of($query)
            ->addIndexColumn() //contador automático
            ->addColumn('actions', function ($row) {
                return '';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    // crear paciente
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'name' => 'required|string|max:255',

            'rut' => [
                'required',
                'string',
                'max:20',
                Rule::unique('patients')
                    ->where('customer_id', app('customer')->id)
            ],

            'birth_date' => 'required|date|before_or_equal:today',

            'gender' => 'required|in:male,female,other',

            'phone' => 'required|string|max:20',

            'email' => 'required|email|max:255',

            'address' => 'required|string|max:255',

            'status' => 'required|in:0,1'

        ], [

            'name.required' => 'El nombre del paciente es obligatorio',
            'rut.required' => 'El RUT es obligatorio',
            'rut.unique' => 'Ya existe un paciente con este RUT',
            'birth_date.date' => 'La fecha de nacimiento no es válida',
            'birth_date.required' => 'La fecha de nacimiento es obligatoria',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura',
            'gender.in' => 'Sexo inválido',
            'address.required' => 'La dirección es obligatoria',
            'email.email' => 'El correo electrónico no es válido',
            'email.required' => 'El correo electrónico es obligatorio',
            'status.required' => 'Debe seleccionar un estado',

        ]);

        if ($validator->fails()) {

            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $patient = Patient::create([

            'customer_id' => app('customer')->id,

            'name' => $request->name,

            'rut' => $request->rut,

            'birth_date' => $request->birth_date,

            'gender' => $request->gender,

            'phone' => $request->phone,

            'email' => $request->email,

            'address' => $request->address,

            'status' => $request->status,

        ]);

        return response()->json([
            'success' => true,
            'data' => $patient
        ]);
    }


    //cambiar estado
    public function toggleStatus(Request $request)
    {

    
        $id = $request->route('id');

        $patient = Patient::where('id', $id)->firstOrFail();

        $patient->update([
            'status' => $request->status
        ]);

        return response()->json(['success' => true]);
    }

}
