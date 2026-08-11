<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ProfessionalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $specialties = Specialty::where('customer_id', app('customer')->id)
            ->where('status', 1)
            ->get();
        return view('customer.professional.index', compact('specialties'));
    }



    // datatable profesional
    public function getProfessional()
    {
        $query = User::where('customer_id', app('customer')->id)
            ->whereHas('roles', function ($q) {
                $q->where('id', 3);
            })
            ->with([
                'specialties' => function ($query) {
                    $query->where('specialties.customer_id', app('customer')->id);
                }
            ]);

        return DataTables::of($query)

            // HTML solamente para mostrar en la tabla
            ->addColumn('specialties_html', function ($user) {

                return $user->specialties
                    ->map(function ($specialty) {

                        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700 mr-1">
                            ' . e($specialty->name) . '
                        </span>';
                    })
                    ->implode(' ');
            })

            ->rawColumns(['specialties_html'])

            ->make(true);
    }

    // crear professional
    public function store(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rut' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('customer_id', app('customer')->id);
                }),
            ],
            'password' => 'required|string|min:8',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'phone.required' => 'El celular es obligatorio.',
            'rut.required' => 'El rut es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Debe ingresar un correo válido.',
            'email.unique' => 'Este correo ya existe para este cliente.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::transaction(function () use ($request) {

            $user = User::create([
                'customer_id' => app('customer')->id,
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => $request->password,
                'status'      => $request->status,
                'rut'         => $request->rut,
                'phone'       => $request->phone
            ]);

            $user->roles()->attach(3);
            $user->specialties()->attach(
                collect($request->specialties)
                    ->mapWithKeys(function ($specialtyId) {
                        return [
                            $specialtyId => [
                                'customer_id' => app('customer')->id
                            ]
                        ];
                    })
                    ->toArray()
            );
            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    // Actualizar profesional
    public function update(Request $request) {}


    //cambiar estado
    public function toggleStatus(Request $request)
    {
        $id = $request->route('id');

        $professional = User::where('id', $id)->where('customer_id', app('customer')->id)->firstOrFail();

        $professional->update([
            'status' => $request->status
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $id = $request->route('id');

        $professional = User::where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $professional->status = 0;
        $professional->save();

        $professional->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profesional movido a papelera'
        ]);
    }

    public function trashData(Request $request)
    {
        $professionals = User::onlyTrashed()
            ->where('customer_id', app('customer')->id)
            ->with('specialties');

        return DataTables::of($professionals)

            ->addColumn('specialties_html', function ($professional) {

                return $professional->specialties
                    ->map(function ($specialty) {
                        return '<span class="inline-block bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs mr-1">'
                            . e($specialty->name)
                            . '</span>';
                    })
                    ->implode('');
            })

            ->editColumn('deleted_at', function ($professional) {

                return $professional->deleted_at;
            })

            ->rawColumns([
                'specialties_html'
            ])

            ->make(true);
    }

    public function restore(Request $request)
    {
        $id = $request->route('id');

        $professional = User::withTrashed()
            ->where('id', $id)
            ->where('customer_id', app('customer')->id)
            ->firstOrFail();

        $professional->restore();



        return response()->json([
            'success' => true,
            'message' => 'Profesional restaurado correctamente'
        ]);
    }
}
