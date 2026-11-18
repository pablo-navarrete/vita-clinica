<?php

namespace Tests\Feature;

use App\Http\Controllers\customer\VitaAssistantController;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\User;
use App\Support\ClinicAppointmentMessenger;
use App\Support\ClinicBranchContext;
use App\Support\VitiBookingConversation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VitiBookingConversationTest extends TestCase
{
    public function test_natural_requests_for_a_slot_are_recognized(): void
    {
        $method = new \ReflectionMethod(VitiBookingConversation::class, 'asksForAvailability');
        $service = new VitiBookingConversation();
        foreach (['dame una hora', 'quiero otra hora', 'qué cupos hay', 'cuándo puedo agendar',
            'muéstrame una fecha', 'necesito un turno', 'tienes hora para mañana', 'horas disponibles'] as $message) {
            $this->assertTrue($method->invoke($service, $message), $message);
        }
        foreach (['5 12345678-5', 'confirmar', 'a las 10:00', 'ultima cita del paciente'] as $message) {
            $this->assertFalse($method->invoke($service, $message), $message);
        }
    }

    public function test_slot_number_can_be_sent_with_patient_rut(): void
    {
        $method = new \ReflectionMethod(VitiBookingConversation::class, 'slotChoice');
        $service = new VitiBookingConversation();
        foreach (['5', '5 12345678-5', '5 rut 12.345.678-5', 'opcion 5 con rut 12345678-5'] as $message) {
            $this->assertSame(5, $method->invoke($service, $message));
        }
        $this->assertNull($method->invoke($service, '12345678-5'));
        $this->assertNull($method->invoke($service, '15:30 12345678-5'));
    }

    public function test_specialty_names_match_accents_and_whole_names(): void
    {
        $method = new \ReflectionMethod(VitiBookingConversation::class, 'mentionsName');
        $service = new VitiBookingConversation();
        $this->assertTrue($method->invoke($service, 'agenda en cardiologia con ana perez', 'Cardiología'));
        $this->assertTrue($method->invoke($service, 'con ana perez el lunes', 'Ana Pérez'));
        $this->assertFalse($method->invoke($service, 'agenda en neurologia', 'Urología'));
    }

    public function test_relative_dates_and_invalid_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00', 'America/Santiago'));
        try {
            $service = new VitiBookingConversation();
            $this->assertSame('2026-11-02', $service->dateFromText('horarios del proximo lunes'));
            $this->assertSame('2026-10-29', $service->dateFromText('manana'));
            $this->assertNull($service->dateFromText('31/02/2027'));
            Carbon::setTestNow(Carbon::parse('2026-11-02 12:00', 'America/Santiago'));
            $this->assertSame('2026-11-09', $service->dateFromText('proximo lunes'));
        } finally { Carbon::setTestNow(); }
    }

    public function test_booking_uses_real_availability_and_requires_confirmation_and_can_register_a_missing_patient(): void
    {
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', getenv('DB_DATABASE') ?: '')) $this->markTestSkipped('Isolated MySQL required.');
        config(['patient_registration.enabled' => true, 'patient_registration.channels' => ['internal'=>true,'viti'=>true,'public_booking'=>true], 'identification' => \Tests\Support\IdentifierStorageKeys::config()+['application_key'=>config('app.key')]]);
        $fixture = null;
        foreach (User::whereNotNull('customer_id')->where('status', 1)->get() as $doctor) {
            foreach (ClinicBranchContext::availableBranches($doctor, (int) $doctor->customer_id) as $branch) {
                if ($doctor->hasClinicRole('Doctor', (int) $doctor->customer_id, $branch->id)
                    && $doctor->specialtiesForBranch((int) $doctor->customer_id, $branch->id)->where('specialties.status', 1)->exists()
                    && collect(['appointments.create', 'patients.view', 'patients.create'])->every(fn ($p) => ClinicBranchContext::hasPermissionInBranch($doctor, (int) $doctor->customer_id, $branch->id, $p))) {
                    $fixture = [$doctor, $branch]; break 2;
                }
            }
        }
        if (!$fixture || !Patient::exists()) $this->markTestSkipped('Requires an assigned doctor and patient schema fixture.');
        [$doctor, $branch] = $fixture;
        $clinic = Customer::findOrFail($doctor->customer_id);
        $this->app->instance('customer', $clinic);
        $this->actingAs($doctor);
        session(['active_branch_customer_id' => $clinic->id, 'active_branch_id' => $branch->id]);
        config(['cache.default' => 'array']);
        Notification::fake();
        $this->mock(ClinicAppointmentMessenger::class)->shouldReceive('send')->andReturn(['whatsapp_sent' => false]);
        DB::beginTransaction();
        try {
            $existingNumber = 'EXIST'.\Illuminate\Support\Str::random(12);
            $newNumber = 'NEW'.\Illuminate\Support\Str::random(12);
            $patient = app(\App\Services\Patients\PatientRegistrationService::class)->register($doctor, [
                'identification_country'=>'AR','identification_type'=>'passport','identification_number'=>$existingNumber,
                'name'=>'Viti booking fixture','birth_date'=>'1990-01-01','gender'=>'Femenino','phone'=>'123','email'=>'viti@example.test','address'=>'Chile',
            ]);
            $date = now('America/Santiago')->addWeeks(70)->next(Carbon::MONDAY)->startOfDay();
            ProfessionalSchedule::create(['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id,
                'user_id' => $doctor->id, 'day_of_week' => $date->dayOfWeek, 'start_time' => '22:00:00', 'end_time' => '23:00:00']);
            $ask = function (string $message) use ($clinic, $doctor) {
                $request = Request::create('/assistant/ask', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['message' => $message]));
                $request->setUserResolver(fn () => $doctor);
                $request->setLaravelSession(app('session.store'));
                $route = new Route('POST', 'assistant/ask', fn () => null);
                $route->bind($request); $route->setParameter('customer', $clinic->domain);
                $request->setRouteResolver(fn () => $route);
                $this->app->instance('request', $request);
                return (new VitaAssistantController())->ask($request, $clinic->domain)->getData(true)['reply'];
            };
            $count = Appointment::withoutGlobalScopes()->count();
            $suggestion = $ask('dime una fecha disponible con horas');
            $this->assertMatchesRegularExpression('/Horas disponibles .*el \p{L}+ \d{2} de \p{L}+/u', $suggestion);
            $this->assertMatchesRegularExpression('/\d{2}:\d{2}/', $suggestion);
            $this->assertSame($count, Appointment::withoutGlobalScopes()->count());
            $ask('cancelar');
            $unavailable = $ask('horarios disponibles el 2020-01-01');
            $this->assertStringContainsString('No hay horas disponibles', $unavailable);
            $recovered = $ask('dame una hora');
            $this->assertMatchesRegularExpression('/Horas disponibles .*el \p{L}+ \d{2} de \p{L}+/u', $recovered);
            $this->assertStringNotContainsString('2020-01-01', $recovered);
            $this->assertMatchesRegularExpression('/\d{2}:\d{2}/', $recovered);
            $this->assertSame($count, Appointment::withoutGlobalScopes()->count());
            $ask('cancelar');
            $reply = $ask('horarios disponibles el ' . $date->toDateString());
            $this->assertStringContainsString('22:00', $reply);
            preg_match('/^(\d+)\. .*22:00/m', $reply, $slotNumber);
            $this->assertNotEmpty($slotNumber);
            $reply = $ask($slotNumber[1] . ' pasaporte AR ' . $existingNumber);
            if (str_contains($reply, 'especialidad por')) $reply = $ask('1');
            $this->assertStringContainsString('presencial', $reply);
            $this->assertStringContainsString('motivo', $ask('presencial'));
            $review = $ask('Control de prueba');
            $this->assertStringContainsString('Aún no está reservada', $review);
            $this->assertStringContainsString($patient->name, $review);
            $this->assertSame($count, Appointment::withoutGlobalScopes()->count());
            $this->assertStringContainsString('Cita agendada correctamente', $ask('confirmar'));
            $this->assertSame($count + 1, Appointment::withoutGlobalScopes()->count());
            $ask('confirmar');
            $this->assertSame($count + 1, Appointment::withoutGlobalScopes()->count());
            $reply = $ask('agendar ' . $date->toDateString() . ' a las 22:00 con pasaporte AR ' . $existingNumber);
            $this->assertStringContainsString('no está disponible', $reply);
            $ask('cancelar');
            $patients = Patient::where('customer_id', $clinic->id)->count();
            $reply = $ask('agendar ' . $date->toDateString() . ' a las 22:30 con pasaporte AR ' . $newNumber);
            $this->assertStringContainsString('No existe un paciente', $reply);
            $this->assertStringContainsString('nombre completo', $ask('si'));
            $ask('Paciente de prueba Viti');
            $ask('01/01/1990'); $ask('Femenino'); $ask('912345678');
            $ask('test-viti@example.invalid');
            $review = $ask('Dirección de prueba 123');
            $this->assertStringContainsString('confirmar paciente', $review);
            $this->assertSame($patients, Patient::where('customer_id', $clinic->id)->count());
            $this->assertStringContainsString('Paciente registrado', $ask('confirmar paciente'));
            $this->assertSame($patients + 1, Patient::where('customer_id', $clinic->id)->count());
            $this->assertSame($count + 1, Appointment::withoutGlobalScopes()->count());
            $ask('cancelar');
            $appointment = Appointment::withoutGlobalScopes()->where('patient_id', $patient->id)->firstOrFail();
            if (ClinicBranchContext::hasPermissionInBranch($doctor, $clinic->id, $branch->id, 'appointments.cancel')) {
                $review = $ask('cancelar cita #' . $appointment->id);
                $this->assertStringContainsString('confirmar', $review);
                $this->assertSame(0, (int) $appointment->fresh()->status);
                $ask('no');
                $this->assertSame(0, (int) $appointment->fresh()->status);
                $this->assertStringContainsString($patient->name, $ask('cancelar cita con pasaporte AR '.$existingNumber));
                $this->assertStringContainsString('confirmar', $ask('1'));
                $this->assertSame(0, (int) $appointment->fresh()->status);
                $this->assertStringContainsString('Cita cancelada correctamente', $ask('confirmar cancelación'));
                $this->assertSame(5, (int) $appointment->fresh()->status);
                $this->assertStringContainsString('ya no', $ask('cancelar cita #' . $appointment->id));
            } else {
                try {
                    $ask('cancelar cita #' . $appointment->id);
                    $this->fail('Cancellation must require permission.');
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
                $this->assertSame(0, (int) $appointment->fresh()->status);
            }
        } finally { DB::rollBack(); }
    }
}
