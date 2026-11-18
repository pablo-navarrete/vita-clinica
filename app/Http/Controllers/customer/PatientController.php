<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Insurance;
use App\Models\Appointment;
use App\Models\Specialty;
use Illuminate\Validation\Rule;
use App\Models\Patient;
use App\Models\User;
use App\Support\PatientAuditLogger;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    public function portalProfile(Request $request)
    {
        return response()->json(['profile'=>app(\App\Services\MiSalud\PortalProfileSharingService::class)->lookup($request->user(),$request->all())])
            ->header('Cache-Control','private, no-store')->header('Pragma','no-cache');
    }
    public function destroy(Request $request)
    {
        app(\App\Services\PatientDeletion::class)->delete($request->user(), (int) $request->route('id'));
        return response()->json(['success' => true, 'message' => 'Paciente eliminado correctamente.']);
    }
   
    public function index()
    {

        $customerId = app('customer')->id;
        $activeBranch = \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), (int) $customerId);
        $activeBranchId = $activeBranch?->id;
        $appointmentBranches = $activeBranch
            ? \App\Support\ClinicBranchContext::availableBranches(auth()->user(), (int) $customerId)
            : collect();
        $canChooseAppointmentBranch = $activeBranchId
            && $appointmentBranches->count() > 1
            && \App\Support\ClinicBranchContext::isHeadquartersActive((int) $customerId)
            && \App\Support\ClinicBranchContext::canOperateAcrossBranches(auth()->user(), (int) $customerId)
            && \App\Support\ClinicBranchContext::hasPermissionInBranch(
                auth()->user(), (int) $customerId, (int) $activeBranchId, 'appointments.create'
            );
        $insurances = Insurance::where('customer_id', $customerId)->where('status', 1)->get();
        $user = auth()->user();
        $isCentralAppointmentCreator = $user && $activeBranchId
            && \App\Support\ClinicBranchContext::canAdministerAllBranches($user, (int) $customerId)
            && \App\Support\ClinicBranchContext::hasPermissionInBranch($user, (int) $customerId, (int) $activeBranchId, 'appointments.create');
        $isDoctor = $user && $activeBranchId
            && $user->hasClinicRole('Doctor', (int) $customerId, (int) $activeBranchId)
            && !$isCentralAppointmentCreator;
        $currentProfessionalId = null;
        $currentProfessionalName = null;

        if ($isDoctor) {
            $professional = User::where('customer_id', $customerId)
                ->where('status', 1)
                ->whereKey($user->id)
                ->first(['id', 'name']);

            $currentProfessionalId = $professional?->id;
            $currentProfessionalName = $professional?->name;
        }

        $specialties = Specialty::query()
            ->where('specialties.customer_id', $customerId)
            ->where('specialties.status', 1)
            ->whereHas('users', fn ($users) => $users
                ->where('users.customer_id', $customerId)
                ->where('users.status', 1)
                ->when($isDoctor && $currentProfessionalId, fn ($query) => $query->whereKey($currentProfessionalId))
                ->where('specialty_user.customer_id', $customerId)
                ->where('specialty_user.clinic_branch_id', $activeBranchId ?? 0)
                ->whereHas('branches', fn ($branches) => $branches
                    ->where('clinic_branches.id', $activeBranchId ?? 0)
                    ->where('clinic_branches.status', true)
                    ->where('clinic_branch_user.status', true)))
            ->orderBy('specialties.name')
            ->get();

        return view('customer.patient.index', compact(
            'insurances', 'specialties', 'isDoctor', 'currentProfessionalId', 'currentProfessionalName',
            'activeBranchId', 'appointmentBranches', 'canChooseAppointmentBranch'
        ));
    }

    // datatable
    public function getPatient(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'array'], 'search.value' => ['nullable', 'string', 'max:128'],
            'document_country' => ['nullable', 'required_with:document_type', 'string', 'size:2'],
            'document_type' => ['nullable', 'required_with:document_country', 'string', 'max:32'],
        ]);
        $customerId = app('customer')->id;
        $now = now();
        $query = Patient::with(['insurance', 'clinicBranch'])
            ->withCount(['appointments as total_appointments_count' => function ($appointments) use ($customerId) {
                $appointments->where('customer_id', $customerId);
            }])
            ->withCount(['appointments as future_appointments_count' => function ($appointments) use ($customerId, $now) {
                $appointments
                    ->where('customer_id', $customerId)
                    ->whereIn('status', [0, 1, 2])
                    ->where('start_at', '>=', $now);
            }])
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('document_number')) {
            $request->validate(['document_country'=>'required|string|size:2','document_type'=>'required|string','document_number'=>'required|string|max:128']);
            app(\App\Services\Identification\PatientIdentification::class)->filter($query,$request->input('document_number'),$request->input('document_country'),$request->input('document_type'));
        }
        $response = DataTables::of($query)
            ->filter(fn ($query) => app(\App\Services\Identification\PatientIdentification::class)->filterDataTable($query, $request))
            ->removeColumn('rut')
            ->addColumn('identification_label', fn ($patient) => app(\App\Services\Identification\PatientIdentification::class)->patientTableNumber($patient))
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
            ->addColumn('branch_name', fn ($row) => $row->clinicBranch?->name ?? 'Sin sede')
            ->filterColumn('branch_name', fn ($query, $keyword) => $query->whereHas('clinicBranch', fn ($branchQuery) => $branchQuery->where('name', 'like', "%{$keyword}%")))
            ->addColumn('insurance_id', function ($row) {
                    return $row->insurance_id;
            })
            ->addColumn('actions', function ($row) {
                return '';
            })
            ->rawColumns(['actions'])
            ->make(true);
        // Never echo document search input or SQL diagnostics, even with APP_DEBUG.
        $payload = $response->getData(true);
        unset($payload['input'], $payload['queries']);
        return $response->setData($payload)->header('Cache-Control', 'no-store');

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

    public function identification(Request $request, $customer, int $id)
    {
        $patient = Patient::where('customer_id', app('customer')->id)->findOrFail($id);
        return response()->json(app(\App\Services\Identification\PatientIdentification::class)->form($patient, $request->user()))->header('Cache-Control', 'no-store');
    }

    // Compatibility adapter: no HTTP route and no client-controlled bypass flag.
    private function patientFields(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:Masculino,Femenino,Otro'], 'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'], 'address' => ['required', 'string', 'max:255'],
            'insurance_id' => ['nullable', Rule::exists('insurances','id')->where('customer_id', app('customer')->id)],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['registration_operation' => 'nullable|uuid']);
        $patient = app(\App\Services\Patients\PatientRegistrationService::class)->register($request->user(), $request->all(), 'internal', $request->input('registration_operation'));
        app(\App\Services\Identification\PatientIdentification::class)->decorate($patient);
        return response()->json(['success' => true, 'data' => $patient]);
    }

    public function update(Request $request)
    {
        $customer = (int) app('customer')->id;
        $patient = Patient::where('customer_id',$customer)->findOrFail($request->route('id'));
        $fields = $this->patientFields($request);
        $identification = app(\App\Services\Identification\PatientIdentification::class);
        $doc = $identification->parse($request,$patient);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request,$patient,$fields,$identification,$doc,$customer) {
            abort_unless(\Illuminate\Support\Facades\DB::table('customers')->where('id',$customer)->lockForUpdate()->first(),403);
            $patient->refresh();
            if ($doc) $identification->assertUnique($doc,$request->user(),$patient);
            $before = $patient->only(array_keys($fields));
            $identification->save($patient,$request->user(),$doc,$request,false);
            $patient->update($fields);
            PatientAuditLogger::record($patient,'updated','Datos del paciente',$before,$patient->only(array_keys($fields)));
        });
        $patient->makeHidden('rut')->setAttribute('identification_label',$identification->masked($patient));
        return response()->json(['success'=>true,'message'=>'Paciente actualizado correctamente.','data'=>$patient]);
    }
}
