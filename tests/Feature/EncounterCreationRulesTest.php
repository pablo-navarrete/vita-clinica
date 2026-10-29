<?php

namespace Tests\Feature;

use App\Http\Controllers\customer\EncounterController;
use App\Http\Controllers\customer\MedicalRecordController;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicAppointmentMessenger;
use App\Support\ClinicBranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EncounterCreationRulesTest extends TestCase
{
    public function test_creation_and_continuation_rules_without_changing_existing_clinical_data(): void
    {
        // This integration check uses an assigned doctor and rolls back all its fixture records.
        $fixture = null;
        foreach (User::whereNotNull('customer_id')->where('status', 1)->get() as $candidate) {
            foreach (ClinicBranchContext::availableBranches($candidate, (int) $candidate->customer_id) as $branch) {
                $specialty = $candidate->specialtiesForBranch((int) $candidate->customer_id, $branch->id)->where('specialties.status', 1)->first();
                if ($specialty && $candidate->hasClinicRole('Doctor', (int) $candidate->customer_id, $branch->id)) {
                    $fixture = [$candidate, $branch, $specialty];
                    break 2;
                }
            }
        }
        if (!$fixture || !Patient::exists()) $this->markTestSkipped('Requires an assigned doctor and an existing patient schema fixture.');
        [$doctor, $branch, $specialty] = $fixture;
        $clinic = Customer::findOrFail($doctor->customer_id);
        $this->app->instance('customer', $clinic);
        $this->actingAs($doctor);
        session(['active_branch_customer_id' => $clinic->id, 'active_branch_id' => $branch->id]);
        $this->mock(ClinicAppointmentMessenger::class)->shouldReceive('send')->andReturn(['whatsapp_sent' => false]);
        DB::beginTransaction();
        try {
            $patient = Patient::firstOrFail()->replicate();
            $patient->fill(['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id,
                'name' => 'Encounter rules fixture', 'rut' => 'TEST-' . bin2hex(random_bytes(4))]);
            $patient->save();
            $makeAppointment = fn () => Appointment::create([
                'customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id,
                'patient_id' => $patient->id, 'professional_id' => $doctor->id, 'specialty_id' => $specialty->id,
                'start_at' => now(), 'end_at' => now()->addMinutes(30), 'status' => 0, 'modality' => 'in_person',
            ]);
            $send = function (array $data) use ($clinic, $doctor, $patient) {
                $request = $this->bindRequest($clinic, $doctor, 'POST', $data);
                return (new EncounterController())->store($request, $clinic->domain, $patient->id);
            };
            $payload = ['chief_complaint' => 'Test consultation', 'modality' => 'in_person', 'specialty_id' => $specialty->id];
            $firstAppointment = $makeAppointment();
            \Illuminate\Support\Facades\Mail::fake();
            config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => str_repeat('x', 32), 'cache.default' => 'array']);
            \Illuminate\Support\Facades\Event::fake([\App\Events\TelemedicineSignal::class]);
            $roomController = new \App\Http\Controllers\customer\TelemedicineController();
            $roomRequest = $this->bindRequest($clinic, $doctor, 'POST', []);
            $roomResult = $roomController->create($roomRequest, $firstAppointment->id)->getData(true);
            $this->assertArrayHasKey('room_url', $roomResult);
            $session = \App\Models\TelemedicineSession::where('appointment_id', $firstAppointment->id)->firstOrFail();
            $this->assertNull($session->patient_consent_at);
            $tokenResult = $roomController->staffToken($roomRequest, $session->id)->getData(true);
            $this->assertArrayHasKey('iceServers', $tokenResult);
            $this->assertSame('in_progress', $session->fresh()->status);
            $this->assertNull($session->fresh()->started_at);
            $this->assertNull($session->fresh()->patient_consent_at);
            $telemedicineResponse = $send(array_merge($payload, ['appointment_id' => $firstAppointment->id, 'modality' => 'telemedicine']));
            $this->assertSame(1, $patient->encounters()->count());
            $this->assertSame($session->id, $patient->encounters()->firstOrFail()->telemedicine_session_id);
            $send($payload + ['appointment_id' => $firstAppointment->id]);
            $first = $patient->encounters()->firstOrFail();
            $response = $send($payload + ['appointment_id' => $firstAppointment->id]);
            $this->assertSame(1, $patient->encounters()->count());
            $this->assertStringContainsString('encounter_id=' . $first->id, $response->getTargetUrl());

            $secondAppointment = $makeAppointment();
            $this->bindRequest($clinic, $doctor, 'GET', ['start_encounter' => $secondAppointment->id]);
            $startView = (new MedicalRecordController())->show($clinic->domain, $patient->id);
            $this->assertTrue($startView->getData()['openEncounterModal']);
            $this->assertSame($secondAppointment->id, $startView->getData()['preselectedAppointmentId']);
            $send(array_merge($payload, ['appointment_id' => $secondAppointment->id, 'modality' => 'telemedicine']));
            $automaticAppointmentRoom = $patient->encounters()->where('appointment_id', $secondAppointment->id)->firstOrFail()->telemedicineSession;
            $this->assertNotNull($automaticAppointmentRoom);
            $this->assertSame('scheduled', $automaticAppointmentRoom->status);
            $this->assertNull($automaticAppointmentRoom->patient_consent_at);
            $this->assertSame(2, $patient->encounters()->count());
            try {
                $send($payload);
                $this->fail('An open walk-in must require acknowledgement.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('confirm_open_encounter', $exception->errors());
            }
            $send($payload + ['confirm_open_encounter' => 1]);
            $this->assertSame(3, $patient->encounters()->count());

            $this->bindRequest($clinic, $doctor, 'GET', ['encounter_id' => $first->id]);
            $data = (new MedicalRecordController())->show($clinic->domain, $patient->id)->getData();
            $this->assertSame($first->id, $data['currentEncounter']->id);
            $this->assertCount(3, $data['ownActiveEncounters']);
            $data['errors'] = new \Illuminate\Support\ViewErrorBag();
            $html = view('customer.patient.medical-record', $data)->render();
            $this->assertStringContainsString('id="active-encounter-picker"', $html);
            $this->assertStringContainsString('name="confirm_open_encounter"', $html);
            $this->assertSame(1, substr_count($html, 'name="clinical_assessment"'));
            $this->assertStringContainsString('videoLoaded: true', $html);
            $this->assertStringContainsString('embedded=1', $html);
            $this->assertStringNotContainsString('window.open(', $html);
            request()->headers->remove('Sec-Fetch-Dest');
            request()->query->remove('_vita_content');
            request()->request->remove('_vita_content');
            $shellHtml = view('customer.patient.medical-record', $data)->render();
            $this->assertStringContainsString('id="vita-module-frame"', $shellHtml);
            $this->assertStringContainsString('id="vita-call-frame"', $shellHtml);
            $this->assertStringNotContainsString('id="clinical-workspace"', $shellHtml);
            request()->headers->set('X-Requested-With', 'XMLHttpRequest');
            $ajaxHtml = view('customer.patient.medical-record', $data)->render();
            $this->assertStringContainsString('id="clinical-workspace"', $ajaxHtml);
            $this->assertStringNotContainsString('id="vita-module-frame"', $ajaxHtml);
            request()->headers->remove('X-Requested-With');
            request()->headers->set('Sec-Fetch-Dest', 'iframe');
            request()->query->set('_vita_content', 0);
            $chromeHtml = view('customer.patient.medical-record', $data)->render();
            $this->assertStringContainsString('id="vita-module-slot"', $chromeHtml);
            $this->assertStringContainsString('id="vita-content-current"', $chromeHtml);
            $this->assertStringNotContainsString('id="clinical-workspace"', $chromeHtml);
            request()->query->set('_vita_content', 1);

            $this->bindRequest($clinic, $doctor, 'GET', ['start_encounter' => $secondAppointment->id]);
            $response = (new MedicalRecordController())->show($clinic->domain, $patient->id);
            $this->assertStringContainsString('encounter_id=', $response->getTargetUrl());

            $patient->encounters()->update(['status' => 2, 'ended_at' => now()]);
            $this->bindRequest($clinic, $doctor, 'GET', ['history_encounter_id' => $first->id]);
            $selectedHistoryData = (new MedicalRecordController())->show($clinic->domain, $patient->id)->getData();
            $this->assertSame(1, $selectedHistoryData['encounters']->total());
            $this->assertSame($first->id, $selectedHistoryData['encounters']->first()->id);
            $selectedHistoryData['errors'] = new \Illuminate\Support\ViewErrorBag();
            $selectedHistoryHtml = view('customer.patient.medical-record', $selectedHistoryData)->render();
            $this->assertStringContainsString("tab: 'history', mobilePanel: 'record'", $selectedHistoryHtml);
            $this->assertStringContainsString('id="history-encounter-' . $first->id . '"', $selectedHistoryHtml);

            $send($payload);
            $this->assertSame(4, $patient->encounters()->count());
            $send($payload + ['appointment_id' => $firstAppointment->id]);
            $this->assertSame(4, $patient->encounters()->count());

            $appointmentCount = Appointment::where('patient_id', $patient->id)->count();
            $directRequest = $this->bindRequest($clinic, $doctor, 'POST', ['specialty_id' => $specialty->id]);
            $direct = $roomController->createWithoutAppointment($directRequest, $clinic->domain, $patient->id)->getData(true);
            $directSession = \App\Models\TelemedicineSession::findOrFail($direct['session_id']);
            $this->assertNull($directSession->appointment_id);
            $this->assertSame($branch->id, $directSession->clinic_branch_id);
            $this->assertSame($patient->id, $directSession->patient_id);
            $this->assertSame($appointmentCount, Appointment::where('patient_id', $patient->id)->count());
            $this->assertSame($direct['session_id'], $roomController->createWithoutAppointment($directRequest, $clinic->domain, $patient->id)->getData(true)['session_id']);
            $this->assertArrayHasKey('iceServers', $roomController->staffToken($directRequest, $clinic->domain, $directSession->id)->getData(true));
            $this->assertNull($directSession->fresh()->patient_consent_at);
            $this->assertFalse($roomController->directStatus($directRequest, $clinic->domain, $patient->id, $directSession->id)->getData(true)['ready']);
            $send(array_merge($payload, ['modality' => 'telemedicine', 'telemedicine_session_id' => $directSession->id, 'confirm_open_encounter' => 1]));
            $this->assertSame(5, $patient->encounters()->count());
            $patientRequest = $this->bindRequest($clinic, $doctor, 'POST', ['consent' => 1]);
            $roomController->patientToken($patientRequest, $clinic->domain, $directSession->id);
            $this->assertNotNull($directSession->fresh()->patient_consent_at);
            $this->assertTrue($roomController->directStatus($patientRequest, $clinic->domain, $patient->id, $directSession->id)->getData(true)['ready']);
            $send(array_merge($payload, ['modality' => 'telemedicine', 'telemedicine_session_id' => $directSession->id, 'confirm_open_encounter' => 1]));
            $directEncounter = $patient->encounters()->latest('id')->firstOrFail();
            $this->assertNull($directEncounter->appointment_id);
            $this->assertSame($directSession->id, $directEncounter->telemedicine_session_id);
            $this->assertSame('telemedicine', $directEncounter->modality);
            $this->assertSame($appointmentCount, Appointment::where('patient_id', $patient->id)->count());
            $send(array_merge($payload, ['modality' => 'telemedicine', 'confirm_open_encounter' => 1]));
            $automatic = $patient->encounters()->latest('id')->firstOrFail();
            $this->assertNotSame($directEncounter->id, $automatic->id);
            $this->assertNotNull($automatic->telemedicine_session_id);
            $this->assertNull($automatic->telemedicineSession->patient_consent_at);
            $this->assertSame('scheduled', $automatic->telemedicineSession->status);
            $this->assertSame($appointmentCount, Appointment::where('patient_id', $patient->id)->count());
            $authRequest = $this->bindRequest($clinic, $doctor, 'POST', [
                'action' => 'auth', 'socket_id' => '123.456', 'channel_name' => 'private-telemedicine.' . $directSession->room_name,
            ]);
            $auth = $roomController->staffToken($authRequest, $clinic->domain, $directSession->id)->getData(true);
            $this->assertSame('test-key:' . hash_hmac('sha256', '123.456:private-telemedicine.' . $directSession->room_name, str_repeat('x', 32)), $auth['auth']);
            $authRequest->merge(['channel_name' => 'private-telemedicine.other-room']);
            try {
                $roomController->staffToken($authRequest, $clinic->domain, $directSession->id);
                $this->fail('A room must not authorize subscriptions to other rooms.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $otherDevice = $this->bindRequest($clinic, $doctor, 'POST', ['client_id' => 'd8c7e8c2-3817-4f25-bd02-aa6c7d333a02']);
            try {
                $roomController->staffToken($otherDevice, $clinic->domain, $directSession->id);
                $this->fail('Only one doctor device can join a room at a time.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
            $signalRequest = $this->bindRequest($clinic, $doctor, 'POST', ['action' => 'signal', 'signal' => ['type' => 'ready']]);
            $roomController->staffToken($signalRequest, $clinic->domain, $directSession->id);
            \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\TelemedicineSignal::class, fn ($event) => $event->sender === 'staff' && $event->signal['type'] === 'ready');
            $signalRequest->merge(['signal' => ['type' => 'connected']]);
            $clock = $roomController->staffToken($signalRequest, $clinic->domain, $directSession->id)->getData(true);
            $this->assertNotNull($clock['consultationStartedAt']);
            $this->assertSame($directSession->fresh()->started_at->getTimestampMs(), $clock['consultationStartedAt']);
            $startedAt = $clock['consultationStartedAt'];
            try {
                $this->travel(31)->minutes();
                $signalRequest->merge(['action' => 'join']);
                $afterThirtyMinutes = $roomController->staffToken($signalRequest, $clinic->domain, $directSession->id)->getData(true);
                $this->assertSame($startedAt, $afterThirtyMinutes['consultationStartedAt']);
                $this->assertSame('in_progress', $directSession->fresh()->status);
                $this->assertNull($directSession->fresh()->ended_at);
            } finally { $this->travelBack(); }

            try {
                $roomController->patientToken($signalRequest, $clinic->domain, $directSession->id);
                $this->fail('Patient signaling requires explicit consent.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('consent', $exception->errors());
            }
            $directSession->forceFill(['expires_at' => now()->subMinute()])->save();
            try {
                $roomController->patientToken($patientRequest, $clinic->domain, $directSession->id);
                $this->fail('An expired direct room must not issue access tokens.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(410, $exception->getStatusCode());
            }
            $superAdmin = $doctor->replicate();
            $superAdmin->fill(['customer_id' => null, 'email' => 'super-test-' . bin2hex(random_bytes(6)) . '@example.invalid',
                'rut' => 'TEST-' . bin2hex(random_bytes(4)), 'status' => 1])->save();
            $superRole = \App\Models\Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
            $superAdmin->roles()->attach($superRole->id);
            $this->actingAs($superAdmin);
            $superRequest = $this->bindRequest($clinic, $superAdmin, 'GET', []);
            $controller = new \App\Http\Controllers\customer\MyEncounterController();
            $superView = $controller->index($superRequest);
            $this->assertTrue($superView->getData()['isSuperAdmin']);
            $this->assertTrue($superView->getData()['allBranches']);
            $superHtml = $superView->render();
            $this->assertStringContainsString('my-encounters', $superHtml);
            $superRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
            $superRequest->merge(['length' => -1]);
            $superData = $controller->index($superRequest)->getData(true);
            $this->assertContains($automatic->id, array_column($superData['data'], 'id'));
            $closedRow = collect($superData['data'])->firstWhere('id', $first->id);
            $this->assertStringContainsString('history_encounter_id=' . $first->id, $closedRow['record_url']);

            $this->assertNotContains(false, array_map(fn ($row) => (int) $row['customer_id'] === (int) $clinic->id, $superData['data']));
            $superAdmin->forceFill(['status' => null])->save();
            $this->assertTrue($controller->index($this->bindRequest($clinic, $superAdmin, 'GET', []))->getData()['isSuperAdmin']);
            $superAdmin->forceFill(['status' => 0])->save();
            try {
                $controller->index($this->bindRequest($clinic, $superAdmin, 'GET', []));
                $this->fail('An explicitly disabled superadministrator must not have access.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->actingAs($doctor);
            $ownRequest = $this->bindRequest($clinic, $doctor, 'GET', []);
            $this->assertFalse($controller->index($ownRequest)->getData()['isSuperAdmin']);
        } finally {
            DB::rollBack();
        }
    }

    private function bindRequest(Customer $clinic, User $doctor, string $method, array $data): Request
    {
        $request = Request::create('/encounter-rules-check', $method, $data + ['_vita_content' => 1, 'client_id' => 'd8c7e8c2-3817-4f25-bd02-aa6c7d333a01']);
        $this->app->instance('request', $request);
        $request->headers->set('Sec-Fetch-Dest', 'iframe');
        $request->setUserResolver(fn () => $doctor);
        $request->setLaravelSession(app('session.store'));
        $route = new Route($method, 'encounter-rules-check', fn () => null);
        $route->bind($request);
        $route->setParameter('customer', $clinic->domain);
        $request->setRouteResolver(fn () => $route);
        return $request;
    }
}
