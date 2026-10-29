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
    public function index(): View
    {
        $customerId = (int) app('customer')->id;
        $settings = $this->enabledSettings($customerId);
        $publicMenus = Menu::query()->where('customer_id', $customerId)->where('status', true)->orderBy('position')->orderBy('id')->get();
        $websiteFooter = WebsiteFooter::query()->where('customer_id', $customerId)->first();
        $websiteSocialSettings = WebsiteSocialSetting::query()->where('customer_id', $customerId)->first();
        $websiteTopbar = WebsiteTopbar::query()->where('customer_id', $customerId)->first();
        $publicSpecialties = Specialty::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get();
        $publicInsurances = Insurance::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get();
        $publicBranches = ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get();

        return view('customer.web.booking', [
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
        ]);
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
            ->whereHas('roles', fn ($query) => $query->where('roles.customer_id', $customerId)->where('roles.name', 'Doctor'))
            ->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', $specialty->id)
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
        $customerId = (int) app('customer')->id;
        $this->enabledSettings($customerId);
        $data = $request->validate(['rut' => ['required', 'string', 'max:20']]);
        $normalizedRut = $this->normalizeRut($data['rut']);
        $patient = $this->findPatientByRut($customerId, $normalizedRut);

        if (! $patient) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'patient' => $this->publicPatientDetails($patient),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
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
        $customerId = (int) app('customer')->id;
        $this->enabledSettings($customerId);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'rut' => ['required', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['Masculino', 'Femenino', 'Otro'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'insurance_id' => ['nullable', 'integer', Rule::exists('insurances', 'id')->where('customer_id', $customerId)->where('status', true)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp_consent' => ['nullable', 'accepted'],
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

        $normalizedRut = $this->normalizeRut($data['rut']);
        $existingPatient = $this->findPatientByRut($customerId, $normalizedRut);
        if (! $existingPatient) {
            $data = array_merge($data, $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'gender' => ['required', Rule::in(['Masculino', 'Femenino', 'Otro'])],
                'phone' => ['required', 'string', 'max:20'],
            ]));
        }

        $appointment = DB::transaction(function () use ($data, $customerId, $professional, $startAt, $endAt) {
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
                ->exists();
            if ($occupied) {
                throw ValidationException::withMessages(['time' => 'Ese horario acaba de ser reservado. Selecciona otra hora.']);
            }

            $normalizedRut = $this->normalizeRut($data['rut']);
            $patient = $this->findPatientByRut($customerId, $normalizedRut);

            if (! $patient) {
                $patient = Patient::query()->create([
                    'customer_id' => $customerId,
                    'clinic_branch_id' => $data['clinic_branch_id'] ?? null,
                    'name' => trim($data['name']),
                    'rut' => trim($data['rut']),
                    'gender' => $data['gender'],
                    'birth_date' => $data['birth_date'] ?? null,
                    'address' => isset($data['address']) ? trim($data['address']) : null,
                    'insurance_id' => $data['insurance_id'] ?? null,
                    'phone' => trim($data['phone']),
                    'email' => $data['email'] ?? null,
                    'status' => 1,
                ]);
                MedicalRecord::query()->firstOrCreate([
                    'customer_id' => $customerId,
                    'patient_id' => $patient->id,
                ]);
                PatientAuditLogger::record($patient, 'created', 'Datos del paciente', [], [
                    'name' => $patient->name,
                    'rut' => $patient->rut,
                    'birth_date' => $patient->birth_date?->format('Y-m-d'),
                    'phone' => $patient->phone,
                    'email' => $patient->email,
                    'address' => $patient->address,
                    'origen' => 'Agendamiento web pública',
                ]);
            }

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
            ]);

            $professional->notify(new AppointmentNotification([
                'customer_id' => $customerId,
                'appointment_id' => $appointment->id,
                'event' => 'created',
                'title' => 'Nueva cita agendada desde la web',
                'message' => 'Un paciente agendó una cita en tu agenda.',
                'patient_name' => $patient->name,
                'patient_rut' => $patient->rut,
                'specialty' => $appointment->specialty?->name,
                'starts_at' => $startAt->format('Y-m-d H:i:s'),
                'ends_at' => $endAt->format('Y-m-d H:i:s'),
                'reason' => $appointment->reason,
            ]));

            return $appointment;
        });

        app(ClinicAppointmentMessenger::class)->send($appointment, 'created');

        $bookingSuccess = 'Tu cita fue agendada correctamente. La clínica ya recibió la notificación.';
        if ($appointment->modality !== 'telemedicine' && $appointment->clinicBranch) {
            $bookingSuccess .= ' Sede: ' . $appointment->clinicBranch->name;
            if ($appointment->clinicBranch->address) {
                $bookingSuccess .= ', ' . $appointment->clinicBranch->address;
            }
            $bookingSuccess .= '.';
        }

        return redirect()->route('customer.public-booking.index', array_filter([
            'customer' => request()->route('customer'),
            'modal' => $request->boolean('modal') ? 1 : null,
        ]))
            ->with('booking_success', $bookingSuccess);
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

    private function normalizeRut(string $rut): string
    {
        return Str::upper(preg_replace('/[^0-9k]/i', '', $rut));
    }

    private function findPatientByRut(int $customerId, string $normalizedRut): ?Patient
    {
        return Patient::query()
            ->where('customer_id', $customerId)
            ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$normalizedRut])
            ->first();
    }

    private function publicPatientDetails(Patient $patient): array
    {
        return [
            'name' => $patient->name,
            'gender' => $patient->gender,
            'birth_date' => $patient->birth_date?->format('Y-m-d'),
        ];
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
            ->whereHas('roles', fn ($query) => $query->where('roles.customer_id', $customerId)->where('roles.name', 'Doctor'))
            ->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', $specialtyId)
                ->where('specialties.customer_id', $customerId)
                ->where('specialty_user.customer_id', $customerId)
                ->when($branchId, fn ($specialties) => $specialties->where('specialty_user.clinic_branch_id', $branchId)))
            ->find($professionalId);
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
            ->orderBy('clinic_branches.id')
            ->value('clinic_branches.id');
    }
}
