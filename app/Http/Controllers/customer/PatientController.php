<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Insurance;
use App\Models\Appointment;
use App\Models\Specialty;
use Illuminate\Validation\Rule;
use App\Models\Patient;
use App\Support\PatientAuditLogger;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
   
    public function index()
    {

        $customerId = app('customer')->id;
        $insurances = Insurance::where('customer_id', $customerId)->where('status', 1)->get();
        $specialties = Specialty::where('customer_id', $customerId)
            ->where('status', 1)
            ->orderBy('name')
            ->get();
        return view('customer.patient.index', compact('insurances', 'specialties'));
    }

    // datatable
    public function getPatient()
    {
        $customerId = app('customer')->id;
        $now = now();
        $query = Patient::with('insurance')
            ->withCount(['appointments as total_appointments_count' => function ($appointments) use ($customerId) {
                $appointments->where('customer_id', $customerId);
            }])
            ->withCount(['appointments as future_appointments_count' => function ($appointments) use ($customerId, $now) {
                $appointments
                    ->where('customer_id', $customerId)
                    ->whereIn('status', [0, 1, 2])
                    ->where('start_at', '>=', $now);
            }])
            ->where('customer_id', $customerId);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('future_appointments', function ($row) use ($customerId, $now) {
                return Appointment::where('customer_id', $customerId)
                    ->where('patient_id', $row->id)
                    ->whereIn('status', [0, 1, 2])
                    ->where('start_at', '>=', $now)
                    ->with(['professional:id,name', 'specialty:id,name'])
                    ->orderBy('start_at')
                    ->limit(5)
                    ->get(['id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'status'])
                    ->map(fn ($appointment) => [
                        'id' => $appointment->id,
                        'start_at' => $appointment->start_at,
                        'end_at' => $appointment->end_at,
                        'status' => $appointment->status,
                        'professional' => $appointment->professional?->name ?? 'Sin profesional',
                        'specialty' => $appointment->specialty?->name ?? 'Sin especialidad',
                    ])->values()->all();
            })
            ->addColumn('patient_appointments', function ($row) use ($customerId) {
                return Appointment::where('customer_id', $customerId)
                    ->where('patient_id', $row->id)
                    ->with(['professional:id,name', 'specialty:id,name'])
                    ->orderByDesc('start_at')
                    ->limit(5)
                    ->get(['id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'status'])
                    ->map(fn ($appointment) => [
                        'id' => $appointment->id,
                        'start_at' => $appointment->start_at,
                        'end_at' => $appointment->end_at,
                        'status' => $appointment->status,
                        'professional' => $appointment->professional?->name ?? 'Sin profesional',
                        'specialty' => $appointment->specialty?->name ?? 'Sin especialidad',
                    ])->values()->all();
            })
            ->addColumn('insurance', function ($row) {
                return $row->insurance?->name ?? 'Sin previsión';
            })
            ->addColumn('insurance_id', function ($row) {
                    return $row->insurance_id;
            })
            ->addColumn('actions', function ($row) {
                return '';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function appointments(Request $request, $customer, $id)
    {
        $customerId = app('customer')->id;
        $patient = Patient::where('customer_id', $customerId)->findOrFail($id);
        $scope = $request->query('scope') === 'future' ? 'future' : 'all';

        return view('customer.patient.totalappoinmentpatient', compact('patient', 'scope'));
    }

    public function appointmentsData(Request $request, $customer, $id)
    {
        $customerId = app('customer')->id;
        $patient = Patient::where('customer_id', $customerId)->findOrFail($id);
        $scope = $request->query('scope') === 'future' ? 'future' : 'all';

        $appointments = Appointment::where('customer_id', $customerId)
            ->where('patient_id', $patient->id)
            ->when($scope === 'future', function ($query) {
                $query->whereIn('status', [0, 1, 2])->where('start_at', '>=', now());
            })
            ->with(['professional:id,name', 'specialty:id,name'])
            ->with(['encounter' => fn ($query) => $query->select(
                'encounters.id',
                'encounters.appointment_id',
                'encounters.status',
                'encounters.chief_complaint',
                'encounters.clinical_assessment',
                'encounters.treatment_plan',
                'encounters.cancellation_reason',
                'encounters.started_at',
                'encounters.ended_at'
            )])
            ->orderBy('start_at', 'desc');

        return DataTables::of($appointments)
            ->addIndexColumn()
            ->addColumn('professional_name', fn ($appointment) => $appointment->professional?->name ?? 'Sin profesional')
            ->addColumn('specialty_name', fn ($appointment) => $appointment->specialty?->name ?? 'Sin especialidad')
            ->addColumn('encounter_details', fn ($appointment) => [
                'chief_complaint' => $appointment->encounter?->chief_complaint,
                'clinical_assessment' => $appointment->encounter?->clinical_assessment,
                'treatment_plan' => $appointment->encounter?->treatment_plan,
                'cancellation_reason' => $appointment->encounter?->cancellation_reason,
                'started_at' => $appointment->encounter?->started_at,
                'ended_at' => $appointment->encounter?->ended_at,
            ])
            ->filterColumn('professional_name', function ($query, $keyword) {
                $query->whereHas('professional', fn ($professional) => $professional->where('name', 'like', "%{$keyword}%"));
            })
            ->filterColumn('specialty_name', function ($query, $keyword) {
                $query->whereHas('specialty', fn ($specialty) => $specialty->where('name', 'like', "%{$keyword}%"));
            })
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

            'gender' => 'required',

            'phone' => 'required|string|max:20',

            'email' => 'required|email|max:255',

            'address' => 'required|string|max:255'

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

            'status' => 1,

            'insurance_id' => $request->insurance_id,

        ]);


        $patient->medicalRecord()->create([
            'customer_id' => app('customer')->id,
        ]);

        $patientFields = ['name', 'rut', 'birth_date', 'gender', 'phone', 'email', 'address', 'status', 'insurance_id'];
        PatientAuditLogger::record(
            $patient,
            'created',
            'Datos del paciente',
            [],
            array_intersect_key($patient->getRawOriginal(), array_flip($patientFields))
        );
        PatientAuditLogger::record($patient, 'created', 'Ficha clínica', [], ['ficha' => 'Ficha clínica creada']);
        
        return response()->json([
            'success' => true,
            'data' => $patient
        ]);
    }

    public function update(Request $request)
    {
        $customerId = app('customer')->id;
        $id = $request->route('id');
        $patient = Patient::where('customer_id', $customerId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'rut' => [
                'required',
                'string',
                'max:20',
                Rule::unique('patients', 'rut')
                    ->where('customer_id', $customerId)
                    ->ignore($patient->id),
            ],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:Masculino,Femenino,Otro'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'insurance_id' => [
                'nullable',
                Rule::exists('insurances', 'id')->where('customer_id', $customerId),
            ],
        ], [
            'rut.unique' => 'Ya existe otro paciente con este RUT.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'gender.in' => 'Selecciona un sexo válido.',
            'insurance_id.exists' => 'La previsión seleccionada no es válida.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $before = array_intersect_key($patient->getRawOriginal(), $validated);
        $patient->update($validated);
        $after = array_intersect_key($patient->fresh()->getRawOriginal(), $validated);

        PatientAuditLogger::record($patient, 'updated', 'Datos del paciente', $before, $after);

        return response()->json([
            'success' => true,
            'message' => 'Paciente actualizado correctamente.',
            'data' => $patient->fresh(),
        ]);
    }
}
