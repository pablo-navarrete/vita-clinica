<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicBranch;
use App\Models\Insurance;
use App\Models\MedicalRecord;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserAbsence;
use App\Models\WebsiteFooter;
use App\Models\WebsiteNavigationButton;
use App\Models\WebsiteSocialSetting;
use App\Models\WebsiteTopbar;
use App\Notifications\AppointmentNotification;
use App\Support\PatientAuditLogger;
use App\Support\ClinicAppointmentMessenger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function index()
    {
        $portalPersonal=[];
        if ($intent=request()->session()->get('portal_booking_grant')) {
            try {
                $payload=app(\App\Services\MiSalud\PortalBookingService::class)->grant($intent,request()->session()->getId(),(int)app('customer')->id);
                $portalPersonal=array_filter($payload['personal'],fn($v)=>$v!==null && $v!=='');
                request()->session()->now('_old_input',array_replace($payload['selection'],request()->old(),$portalPersonal));
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return $this->portalCancel(request(),app('customer')->domain,$intent);
            }
        }
        $customerId = (int) app('customer')->id;
        $settings = $this->enabledSettings($customerId);
        $publicMenus = Menu::query()->with('websitePage')->where('customer_id', $customerId)->where('status', true)->orderBy('position')->orderBy('id')->get();
        $websiteFooter = WebsiteFooter::query()->where('customer_id', $customerId)->first();
        $websiteSocialSettings = WebsiteSocialSetting::query()->where('customer_id', $customerId)->first();
        $websiteTopbar = WebsiteTopbar::query()->where('customer_id', $customerId)->first();
        $publicSpecialties = app(\App\Services\SpecialtyCatalog::class)->offered($customerId);
        $publicInsurances = Insurance::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get();
        $publicBranches = ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get();

        return response()->view('customer.web.booking', [
            'portalPersonal'=>$portalPersonal,
            'publicPreview' => false,
            'hasConfiguredMenus' => true,
            'publicMenus' => $publicMenus,
            'publicSpecialties' => $publicSpecialties,
            'publicInsurances' => $publicInsurances,
            'publicBranches' => $publicBranches,
            'websiteNavigationButton' => $settings,
            'websiteFooter' => $websiteFooter,
            'websiteSocialSettings' => $websiteSocialSettings,
            'websiteTopbar' => $websiteTopbar,
            'minimumDate' => now(config('app.timezone', 'America/Santiago'))->toDateString(),
            'bookingModal' => request()->boolean('modal'),
        ])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }

    public function professionals(Request $request): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $this->enabledSettings($customerId);
        $data = $request->validate([
            'specialty_id' => ['required', 'integer'],
            'clinic_branch_id' => ['nullable', 'integer', Rule::exists('clinic_branches', 'id')->where('customer_id', $customerId)->where('status', true)],
        ]);
        $specialty = Specialty::query()->where('customer_id', $customerId)->where('status', true)->find($data['specialty_id']);
        if (! $specialty) {
            return response()->json([]);
        }

        $professionals = User::query()
            ->where('customer_id', $customerId)
            ->where('status', 1)
            ->whereHas('specialties', fn ($query) => $query
                ->whereIn('specialties.id', app(\App\Services\SpecialtyCatalog::class)->equivalentIds($customerId, $specialty->id))
                ->tap(fn ($q) => app(\App\Services\SpecialtyCatalog::class)->publicBookingAssignment($q, $customerId))
                ->where('specialties.customer_id', $customerId)
                ->where('specialty_user.customer_id', $customerId)
                ->when(!empty($data['clinic_branch_id']), fn ($specialties) => $specialties->where('specialty_user.clinic_branch_id', (int) $data['clinic_branch_id'])))
            ->when(!empty($data['clinic_branch_id']), fn ($query) => $query->whereHas('branches', fn ($branches) => $branches
                ->where('clinic_branches.customer_id', $customerId)
                ->where('clinic_branches.status', true)
                ->where('clinic_branch_user.status', true)
                ->whereKey((int) $data['clinic_branch_id'])))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($professionals);
    }

    public function checkRut(Request $request): JsonResponse
    {
        // Legacy clients receive an identical neutral response. No membership or personal lookup.
        return response()->json(['message' => \App\Services\Patients\PublicPatientRegistration::RECEIVED])->header('Cache-Control','no-store');
    }

    public function availability(Request $request): JsonResponse
    {
        $this->resolveCatalogSelection($request);
        $customerId = (int) app('customer')->id;
        $this->enabledSettings($customerId);
        $data = $request->validate([
            'professional_id' => ['required', 'integer'],
            'specialty_id' => ['required', 'integer'],
            'clinic_branch_id' => ['nullable', 'integer', Rule::exists('clinic_branches', 'id')->where('customer_id', $customerId)->where('status', true)],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $professional = $this->professionalForSpecialty($customerId, (int) $data['professional_id'], (int) $data['specialty_id'], isset($data['clinic_branch_id']) ? (int) $data['clinic_branch_id'] : null);
        if (! $professional) {
            return response()->json([]);
        }
        if (!empty($data['clinic_branch_id']) && !$professional->branches()->where('clinic_branches.id', $data['clinic_branch_id'])->wherePivot('status', true)->exists()) {
            return response()->json([]);
        }

        $branchId = $this->resolveProfessionalBranchForSpecialty($professional, $customerId, (int) $data['specialty_id'], $data['clinic_branch_id'] ?? null);
        if ($branchId && !$professional->specialtiesForBranch($customerId, $branchId)->whereKey($data['specialty_id'])->exists()) return response()->json([]);
        return response()->json($branchId ? $this->availableSlots($customerId, $professional->id, $data['date'], $branchId) : []);
    }

    public function weeklyAvailability(Request $request): JsonResponse
    {
        $this->resolveCatalogSelection($request);
        $customerId = (int) app('customer')->id;
        $this->enabledSettings($customerId);
        $data = $request->validate([
            'professional_id' => ['required', 'integer'],
            'specialty_id' => ['required', 'integer'],
            'clinic_branch_id' => ['nullable', 'integer', Rule::exists('clinic_branches', 'id')->where('customer_id', $customerId)->where('status', true)],
            'date' => ['required', 'date'],
        ]);

        $professional = $this->professionalForSpecialty($customerId, (int) $data['professional_id'], (int) $data['specialty_id'], isset($data['clinic_branch_id']) ? (int) $data['clinic_branch_id'] : null);
        if (! $professional) {
            return response()->json(['availability' => []]);
        }
        if (!empty($data['clinic_branch_id']) && !$professional->branches()->where('clinic_branches.id', $data['clinic_branch_id'])->wherePivot('status', true)->exists()) {
            return response()->json(['availability' => []]);
        }
        $branchId = $this->resolveProfessionalBranchForSpecialty($professional, $customerId, (int) $data['specialty_id'], $data['clinic_branch_id'] ?? null);
        if ($branchId && !$professional->specialtiesForBranch($customerId, $branchId)->whereKey($data['specialty_id'])->exists()) return response()->json(['availability' => []]);

        $timezone = config('app.timezone', 'America/Santiago');
        $today = now($timezone)->startOfDay();
        $weekStart = Carbon::parse($data['date'], $timezone)->startOfWeek(Carbon::MONDAY);
        $availability = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->copy()->addDays($offset);
            $availability[$day->toDateString()] = $day->lt($today)
                ? []
                : ($branchId ? $this->availableSlots($customerId, $professional->id, $day->toDateString(), $branchId) : []);
        }

        return response()->json(['availability' => $availability]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($intent=$request->session()->get('portal_booking_grant')) {
            return $this->storePortal($request,$intent);
        }
        $booking = $this->bookingData($request);
        app(\App\Services\Patients\PublicPatientRegistration::class)->submit($booking['data'], (int) $booking['data']['clinic_branch_id'], $request->input('registration_operation'));
        return redirect()->route('customer.public-booking.index', ['customer' => app('customer')->domain])
            ->with('booking_success', \App\Services\Patients\PublicPatientRegistration::RECEIVED);
    }

    public function portalStart(Request $request)
    {
        $this->enabledSettings((int)app('customer')->id);
        $selection=$request->validate(['specialty_id'=>'nullable|integer','professional_id'=>'nullable|integer',
            'clinic_branch_id'=>'nullable|integer','date'=>'nullable|date','time'=>'nullable|date_format:H:i',
            'modality'=>'nullable|in:in_person,telemedicine','reason'=>'nullable|string|max:500','whatsapp_consent'=>'sometimes|nullable|accepted']);
        $request->session()->forget('portal_booking_grant');
        $id=app(\App\Services\MiSalud\PortalBookingService::class)->begin((int)app('customer')->id,$request->session()->getId(),$selection);
        return redirect()->away(route('mi-salud.booking.show',['intent'=>$id]));
    }

    public function portalReturn(Request $request,string $customer,string $intent)
    {
        $nonce=base64_encode(random_bytes(18));
        return response()->view('customer.web.portal-booking-return',compact('nonce','intent'))
            ->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer')->header('X-Content-Type-Options','nosniff')
            ->header('Content-Security-Policy',"default-src 'none'; script-src 'nonce-$nonce'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    }

    public function portalRedeem(Request $request,string $customer,string $intent)
    {
        try {
            $data=app(\App\Services\MiSalud\PortalBookingService::class)->redeemForSession($intent,$request->session()->getId(),(int)app('customer')->id);
        } catch (\Throwable) {
            return $this->portalCancel($request,$customer,$intent);
        }
        $request->session()->put('portal_booking_grant',$intent);
        return redirect()->route('customer.public-booking.index',['customer'=>$customer])->withInput($data['selection']+$data['personal']);
    }

    public function portalCancel(Request $request,string $customer,string $intent)
    {
        $request->session()->forget('portal_booking_grant');$selection=[];
        // Cancellation may restore selection only to the initiating tenant and session, without personal data.
        $row=DB::table('portal_booking_authorizations')->where('id',$intent)->where('customer_id',app('customer')->id)->first();
        if ($row && hash_equals($row->session_hash,hash('sha256',$request->session()->getId()))) {
            $selection=app(\App\Services\MiSalud\PortalBookingService::class)->payload($row)['selection']??[];
        }
        return redirect()->route('customer.public-booking.index',['customer'=>$customer])->withInput($selection)
            ->with('booking_notice','Puedes continuar con el formulario tradicional. Conservamos la atención seleccionada.');
    }

    private function storePortal(Request $request,string $intent): RedirectResponse
    {
        $bridge=app(\App\Services\MiSalud\PortalBookingService::class);
        try {
            $payload=$bridge->grant($intent,$request->session()->getId(),(int)app('customer')->id);
        } catch (\Throwable) {
            return $this->portalCancel($request,app('customer')->domain,$intent);
        }
        $request->merge(array_filter($payload['personal'],fn($value)=>$value!==null && $value!==''));
        $booking=$this->bookingData($request);
        $ownerId=DB::table('portal_booking_authorizations')->where('id',$intent)->value('portal_account_id');
        $appointment=DB::transaction(function()use($request,$intent,$bridge,$booking,$ownerId){
            \App\Models\PortalAccount::whereKey($ownerId)->lockForUpdate()->firstOrFail();
            app(\App\Services\Patients\PatientRegistrationService::class)->lockClinic((int)$booking['data']['clinic_branch_id']);
            DB::table('portal_booking_authorizations')->where('id',$intent)->lockForUpdate()->first();
            $bridge->grant($intent,$request->session()->getId(),(int)app('customer')->id);
            [$operation,$token]=app(\App\Services\Patients\PublicPatientRegistration::class)->prepareFromPortal($intent,$request->session()->getId(),$booking['data'],(int)$booking['data']['clinic_branch_id']);
            $appointment=app(\App\Services\Patients\PublicPatientRegistration::class)->complete($operation,$token,fn($attempt,$data)=>$this->bookVerified($attempt,$data));
            if ($appointment) $bridge->completed($intent,(int)$appointment->patient_id);
            return $appointment;
        },3);
        if (!$appointment) return redirect()->route('customer.public-booking.index',['customer'=>app('customer')->domain])
            ->withInput($request->except(['_token','authorization_code','identification_number']))
            ->withErrors(['time'=>'No se pudo confirmar la reserva. Revisa el horario o continúa con la verificación de contacto del formulario tradicional. No se creó otro paciente.']);
        $request->session()->forget('portal_booking_grant');
        DB::afterCommit(fn()=>$this->notifyPublicBooking($appointment));
        return redirect()->route('customer.public-booking.index',['customer'=>app('customer')->domain])
            ->with('booking_success','Tu cita fue reservada. Puedes consultar los detalles en la confirmación de la clínica.');
    }

    public function verifyRegistrationPage(Request $request, string $customer, string $operation)
    {
        $nonce = base64_encode(random_bytes(18));
        return response()->view('customer.web.verify-registration', compact('nonce'))->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer')->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; script-src 'nonce-$nonce'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    }

    public function verifyRegistration(Request $request, string $customer, string $operation): RedirectResponse
    {
        $token = is_string($request->input('registration_token')) ? $request->input('registration_token') : '';
        try {
        $appointment = app(\App\Services\Patients\PublicPatientRegistration::class)->complete($operation, $token,
            fn ($attempt, $data) => $this->bookVerified($attempt, $data));
        if ($appointment) DB::afterCommit(fn()=>$this->notifyPublicBooking($appointment));
        } catch (\Throwable $error) {
            if ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) throw $error;
            // No exception object, SQL, request body or bearer token is sent to the logger.
            \Illuminate\Support\Facades\Log::error('Public patient registration failed.', ['operation' => $operation]);
        }
        return redirect()->route('customer.public-booking.index', ['customer' => app('customer')->domain])
            ->with('booking_success', \App\Services\Patients\PublicPatientRegistration::PROCESSED);
    }

    private function notifyPublicBooking(Appointment $appointment): void
    {
            app(ClinicAppointmentMessenger::class)->send($appointment, 'created');
            $appointment->professional->notify(new AppointmentNotification([
                'customer_id' => $appointment->customer_id, 'appointment_id' => $appointment->id, 'event' => 'created',
                'title' => 'Nueva cita agendada desde la web', 'message' => 'Se confirmó una solicitud web verificada.',
                'patient_name' => $appointment->patient->name, 'specialty' => $appointment->specialty?->name,
                'starts_at' => $appointment->start_at->format('Y-m-d H:i:s'), 'ends_at' => $appointment->end_at->format('Y-m-d H:i:s'),
            ]));
    }

    private function bookingData(Request $request): array
    {
        $this->resolveCatalogSelection($request);
        $customerId = (int) app('customer')->id;
        $this->enabledSettings($customerId);
        $data = $request->validate([
            'registration_operation' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:128'],
            'identification_country' => ['sometimes','required','string','size:2'],
            'identification_type' => ['sometimes','required','string','max:32'],
            'identification_number' => ['sometimes','required','string','max:128'],
            'gender' => ['required', Rule::in(['Masculino', 'Femenino', 'Otro'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'insurance_id' => ['nullable', 'integer', Rule::exists('insurances', 'id')->where('customer_id', $customerId)->where('status', true)],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp_consent' => ['sometimes', 'nullable', 'accepted'],
            'specialty_id' => ['required', 'integer', Rule::exists('specialties', 'id')->where('customer_id', $customerId)->where('status', true)],
            'professional_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'modality' => ['required', Rule::in(['in_person', 'telemedicine'])],
            'clinic_branch_id' => [
                $request->input('modality') === 'in_person' && ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->exists() ? 'required' : 'nullable',
                'integer',
                Rule::exists('clinic_branches', 'id')->where('customer_id', $customerId)->where('status', true),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'max:0'],
        ], [
            'website.max' => 'No se pudo procesar la solicitud.',
        ]);

        $professional = $this->professionalForSpecialty($customerId, (int) $data['professional_id'], (int) $data['specialty_id'], isset($data['clinic_branch_id']) ? (int) $data['clinic_branch_id'] : null);
        if (! $professional) {
            throw ValidationException::withMessages(['professional_id' => 'Selecciona un profesional activo para esa especialidad.']);
        }
        $data['clinic_branch_id'] = $this->resolveProfessionalBranchForSpecialty($professional, $customerId, (int) $data['specialty_id'], $data['clinic_branch_id'] ?? null);
        if (ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->exists() && !$data['clinic_branch_id']) {
            throw ValidationException::withMessages(['clinic_branch_id' => 'Selecciona una sede para esta cita.']);
        }
        if (!empty($data['clinic_branch_id']) && !$professional->branches()->where('clinic_branches.id', $data['clinic_branch_id'])->wherePivot('status', true)->exists()) {
            throw ValidationException::withMessages(['professional_id' => 'El profesional no atiende en la sede seleccionada.']);
        }
        if (!empty($data['clinic_branch_id']) && !$professional->specialtiesForBranch($customerId, (int) $data['clinic_branch_id'])->whereKey($data['specialty_id'])->exists()) {
            throw ValidationException::withMessages(['specialty_id' => 'El profesional no tiene esa especialidad asignada en la sede seleccionada.']);
        }

        $timezone = config('app.timezone', 'America/Santiago');
        $startAt = Carbon::parse($data['date'] . ' ' . $data['time'], $timezone);
        $endAt = $startAt->copy()->addMinutes(30);
        if (! $startAt->gt(now($timezone))) {
            throw ValidationException::withMessages(['time' => 'Selecciona una hora futura disponible.']);
        }

        return compact('data', 'professional', 'startAt', 'endAt');
    }

    private function bookVerified(object $attempt, array $submitted): ?Appointment
    {
        $booking = $this->bookingData(Request::create('/', 'POST', $submitted));
        extract($booking);
        $customerId = (int) app('customer')->id;
            // Serializa las reservas del mismo profesional para reducir dobles reservas simultáneas.
            User::query()->where('customer_id', $customerId)->whereKey($professional->id)->lockForUpdate()->firstOrFail();

            $hasSchedule = ProfessionalSchedule::query()
                ->where('customer_id', $customerId)
                ->where('user_id', $professional->id)
                ->where('clinic_branch_id', $data['clinic_branch_id'])
                ->where('day_of_week', $startAt->dayOfWeek)
                ->whereTime('start_time', '<=', $startAt->format('H:i:s'))
                ->whereTime('end_time', '>=', $endAt->format('H:i:s'))
                ->exists();
            if (! $hasSchedule) {
                throw ValidationException::withMessages(['time' => 'El profesional no atiende en ese horario. Selecciona otra hora.']);
            }

            if (UserAbsence::query()->where('customer_id', $customerId)->where('user_id', $professional->id)
                ->whereDate('starts_on', '<=', $startAt->toDateString())->whereDate('ends_on', '>=', $startAt->toDateString())->exists()) {
                throw ValidationException::withMessages(['time' => 'El profesional tiene una ausencia registrada para esa fecha. Selecciona otra hora.']);
            }

            $occupied = Appointment::query()
                ->where('customer_id', $customerId)
                ->where('professional_id', $professional->id)
                ->whereNotIn('status', [4, 5])
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                // Current read after the professional lock, even under MySQL REPEATABLE READ.
                ->lockForUpdate()->first();
            if ($occupied) {
                throw ValidationException::withMessages(['time' => 'Ese horario acaba de ser reservado. Selecciona otra hora.']);
            }

            $patient = app(\App\Services\Patients\PatientRegistrationService::class)->registerVerifiedPublic($attempt, $data);
            if (!$patient) return null;

            $appointment = Appointment::query()->create([
                'customer_id' => $customerId,
                'clinic_branch_id' => $data['clinic_branch_id'] ?? null,
                'patient_id' => $patient->id,
                'professional_id' => $professional->id,
                'specialty_id' => $data['specialty_id'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 0,
                'modality' => $data['modality'],
                'whatsapp_consent_at' => !empty($data['whatsapp_consent']) ? now() : null,
                'reason' => $data['reason'] ?? null,
                'notes' => 'Agendada desde la web pública.',
            ]);

            PatientAuditLogger::record($patient, 'created', 'Cita médica', [], [
                'profesional' => $professional->name,
                'especialidad' => $appointment->specialty?->name,
                'fecha' => $startAt->format('Y-m-d H:i:s'),
                'origen' => 'Web pública',
                'modalidad' => $appointment->modality === 'telemedicine' ? 'Remota (telemedicina)' : 'Presencial',
            ], true);

            return $appointment;
    }

    public function confirmPage(Request $request, string $customer, int $appointmentId)
    {
        $appointment = Appointment::query()
            ->where('customer_id', (int) app('customer')->id)
            ->with(['patient', 'professional', 'specialty', 'clinicBranch'])
            ->findOrFail($appointmentId);

        return view('customer.web.confirm-appointment', compact('appointment', 'customer'));
    }

    public function confirm(Request $request, string $customer, int $appointmentId): RedirectResponse
    {
        $customerId = (int) app('customer')->id;
        $appointment = Appointment::query()
            ->where('customer_id', $customerId)
            ->with(['patient', 'professional', 'specialty', 'clinicBranch'])
            ->findOrFail($appointmentId);

        if ((int) $appointment->status === 0 && Carbon::parse($appointment->start_at)->isFuture()) {
            $appointment->update(['status' => 1]);
            app(ClinicAppointmentMessenger::class)->send($appointment, 'confirmed');
            $appointment->professional?->notify(new AppointmentNotification([
                'customer_id' => $customerId,
                'appointment_id' => $appointment->id,
                'event' => 'confirmed',
                'title' => 'Paciente confirmo su cita',
                'message' => 'El paciente confirmo su asistencia.',
                'patient_name' => $appointment->patient?->name,
                'starts_at' => $appointment->start_at->format('Y-m-d H:i:s'),
            ]));
        }

        $message = (int) $appointment->status === 1
            ? 'Tu asistencia quedo confirmada. Gracias.'
            : 'Esta cita ya no se puede confirmar. Contacta a la clinica.';

        return redirect()->route('customer.public-booking.index', ['customer' => $customer])
            ->with('booking_success', $message);
    }

    private function enabledSettings(int $customerId): WebsiteNavigationButton
    {
        return WebsiteNavigationButton::query()
            ->where('customer_id', $customerId)
            ->where('public_booking_enabled', true)
            ->firstOrFail();
    }

    private function professionalForSpecialty(int $customerId, int $professionalId, int $specialtyId, ?int $branchId = null): ?User
    {
        return User::query()
            ->where('customer_id', $customerId)
            ->where('status', 1)
            ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branches) => $branches
                ->where('clinic_branches.id', $branchId)
                ->where('clinic_branches.status', true)
                ->where('clinic_branch_user.status', true)))
            ->whereHas('specialties', fn ($query) => $query
                ->whereIn('specialties.id', app(\App\Services\SpecialtyCatalog::class)->equivalentIds($customerId, $specialtyId))
                ->tap(fn ($q) => app(\App\Services\SpecialtyCatalog::class)->publicBookingAssignment($q, $customerId))
                ->where('specialties.customer_id', $customerId)
                ->where('specialty_user.customer_id', $customerId)
                ->when($branchId, fn ($specialties) => $specialties->where('specialty_user.clinic_branch_id', $branchId)))
            ->find($professionalId);
    }

    private function resolveCatalogSelection(Request $request): void
    {
        $customerId = (int) app('customer')->id;
        if (!ctype_digit((string)$request->input('specialty_id')) || !ctype_digit((string)$request->input('professional_id'))) return;
        $ids = app(\App\Services\SpecialtyCatalog::class)->equivalentIds($customerId, (int)$request->input('specialty_id'));
        $local = DB::table('specialty_user')->where('customer_id', $customerId)
            ->tap(fn ($q) => app(\App\Services\SpecialtyCatalog::class)->publicBookingAssignment($q, $customerId))
            ->where('user_id', (int)$request->input('professional_id'))->whereIn('specialty_id', $ids)
            ->when($request->filled('clinic_branch_id'), fn ($q) => $q->where('clinic_branch_id', (int)$request->input('clinic_branch_id')))
            ->orderBy('specialty_id')->value('specialty_id');
        if ($local) $request->merge(['specialty_id' => (int)$local]);
    }

    private function availableSlots(int $customerId, int $professionalId, string $date, int $branchId): array
    {
        $timezone = config('app.timezone', 'America/Santiago');
        $day = Carbon::parse($date, $timezone)->startOfDay();
        if (UserAbsence::query()->where('customer_id', $customerId)->where('user_id', $professionalId)
            ->whereDate('starts_on', '<=', $day->toDateString())->whereDate('ends_on', '>=', $day->toDateString())->exists()) {
            return [];
        }
        $schedules = ProfessionalSchedule::query()
            ->where('customer_id', $customerId)
            ->where('user_id', $professionalId)
            ->where('clinic_branch_id', $branchId)
            ->where('day_of_week', $day->dayOfWeek)
            ->get();
        $appointments = Appointment::query()
            ->where('customer_id', $customerId)
            ->where('professional_id', $professionalId)
            ->whereNotIn('status', [4, 5])
            ->whereDate('start_at', $day->toDateString())
            ->get(['start_at', 'end_at']);
        $slots = [];

        foreach ($schedules as $schedule) {
            $slotStart = Carbon::parse($day->toDateString() . ' ' . Carbon::parse($schedule->start_time)->format('H:i:s'), $timezone);
            $scheduleEnd = Carbon::parse($day->toDateString() . ' ' . Carbon::parse($schedule->end_time)->format('H:i:s'), $timezone);
            while ($slotStart->copy()->addMinutes(30)->lte($scheduleEnd)) {
                $slotEnd = $slotStart->copy()->addMinutes(30);
                $occupied = $appointments->contains(fn ($appointment) =>
                    $slotStart < Carbon::parse($appointment->end_at, $timezone)
                    && $slotEnd > Carbon::parse($appointment->start_at, $timezone)
                );
                if (! $occupied && $slotStart->gt(now($timezone))) {
                    $slots[$slotStart->format('H:i')] = [
                        'value' => $slotStart->format('H:i'),
                        'label' => $slotStart->format('H:i') . ' - ' . $slotEnd->format('H:i'),
                    ];
                }
                $slotStart->addMinutes(30);
            }
        }

        ksort($slots);
        return array_values($slots);
    }

    private function resolveProfessionalBranchForSpecialty(User $professional, int $customerId, int $specialtyId, mixed $requestedBranchId): ?int
    {
        if ($requestedBranchId) {
            $branchId = (int) $requestedBranchId;
            return $professional->specialtiesForBranch($customerId, $branchId)->whereKey($specialtyId)->exists()
                ? $branchId : null;
        }

        return DB::table('specialty_user')
            ->join('clinic_branches', 'clinic_branches.id', '=', 'specialty_user.clinic_branch_id')
            ->join('clinic_branch_user', function ($join) use ($professional) {
                $join->on('clinic_branch_user.clinic_branch_id', '=', 'clinic_branches.id')
                    ->where('clinic_branch_user.user_id', '=', $professional->id);
            })
            ->where('specialty_user.customer_id', $customerId)
            ->where('specialty_user.user_id', $professional->id)
            ->where('specialty_user.specialty_id', $specialtyId)
            ->where('clinic_branches.customer_id', $customerId)
            ->where('clinic_branches.status', true)
            ->where('clinic_branch_user.status', true)
            ->tap(fn ($q) => app(\App\Services\SpecialtyCatalog::class)->publicBookingAssignment($q, $customerId))
            ->orderBy('clinic_branches.id')
            ->value('clinic_branches.id');
    }
}
