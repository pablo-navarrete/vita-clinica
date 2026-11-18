<?php

namespace Tests\Integration\Identification;

use App\Mail\VerifyPatientRegistration;
use App\Models\{Patient, Appointment, ProfessionalSchedule, WebsiteNavigationButton, Specialty};
use App\Services\Patients\{PatientRegistrationService, PublicPatientRegistration};
use App\Services\Identification\Storage\{IdentifierRepositoryFactory, IdentifierSubject};
use Illuminate\Support\Facades\{DB, Mail, Notification};
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class PatientRegistrationStage5Test extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as signingSetUp; tearDown as signingTearDown; }
    private bool $started = false;
    private string $date;
    private int $specialty;

    protected function setUp(): void
    {
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', getenv('DB_DATABASE') ?: '')) $this->markTestSkipped('Isolated MySQL only.');
        $this->signingSetUp(); $this->started = true;
        config(['patient_registration.enabled' => true, 'patient_registration.channels' => ['internal'=>true,'viti'=>true,'public_booking'=>true], 'identification' => IdentifierStorageKeys::config() + ['application_key' => config('app.key')], 'cache.default' => 'array']);
        Mail::fake(); Notification::fake();
        $this->mock(\App\Support\ClinicAppointmentMessenger::class)->shouldReceive('send')->andReturn([]);
        app(\App\Services\SpecialtyCatalog::class)->sync($this->doctor, $this->clinic->id, $this->branch->id, [-1089]);
        $this->specialty = Specialty::where('customer_id', $this->clinic->id)->where('catalog_id', 1089)->value('id');
        WebsiteNavigationButton::create(['customer_id' => $this->clinic->id, 'public_booking_enabled' => true]);
        $day = now()->addDays(14)->startOfDay(); $this->date = $day->toDateString();
        ProfessionalSchedule::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
            'user_id' => $this->doctor->id, 'day_of_week' => $day->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
        DB::commit(); // Real endpoint transactions; fixtures remain exclusively in the isolated schema.
    }

    protected function tearDown(): void { if ($this->started) $this->signingTearDown(); }

    private function data(string $number = '000Ab12', string $country = 'AR'): array
    {
        return ['name' => 'Stage five patient', 'birth_date' => '1990-01-01', 'gender' => 'Femenino', 'phone' => '912345678',
            'email' => 'patient@example.test', 'address' => 'Chile', 'identification_country' => $country,
            'identification_type' => $country === 'CL' ? 'national_id' : 'passport', 'identification_number' => $number,
            'clinic_branch_id' => $this->branch->id, 'professional_id' => $this->doctor->id, 'specialty_id' => $this->specialty,
            'date' => $this->date, 'time' => '10:00', 'modality' => 'in_person', 'registration_operation' => (string) Str::uuid()];
    }
    public function test_central_registration_links_foreign_portal_document_and_retirement_revokes_candidate(): void
    {
        $number='000'.Str::upper(Str::random(10)); $data=$this->data($number);
        $email=Str::uuid().'@example.test';
        app(\App\Services\MiSalud\PortalAccountService::class)->register(['name'=>'Declared portal patient','email'=>$email,'password'=>'SecurePassword123!',
            'identification_country'=>'AR','identification_type'=>'passport','identification_number'=>$number]);
        $account=\App\Models\PortalAccount::where('email',$email)->firstOrFail();
        $actor=$this->user('Administrador'); $this->actingAs($actor);
        $patientId=$this->postJson($this->root.'/patient',$data)->assertOk()->json('data.id');
        $link=DB::table('portal_patient_links')->where('portal_account_id',$account->id)->where('patient_id',$patientId)->first();
        $this->assertNotNull($link); $this->assertSame('candidate',$link->status); $this->assertSame(0,$link->clinical_access);
        $patient=Patient::findOrFail($patientId);$this->assertNull($patient->rut);$this->assertNotNull($patient->medicalRecord);
        $reference=DB::table('patient_identifiers')->where('patient_id',$patientId)->value('record_uuid');
        app(IdentifierRepositoryFactory::class)->make()->retire(new IdentifierSubject('patient',$patientId,$this->clinic->id),$actor,$reference,'Synthetic retirement');
        $this->assertSame('revoked',DB::table('portal_patient_links')->where('id',$link->id)->value('status'));
        $data['registration_operation']=(string)Str::uuid();$this->postJson($this->root.'/patient',$data)->assertUnprocessable();
    }

    public function test_channel_gates_keep_public_and_viti_blocked_when_internal_is_enabled(): void
    {
        config(['patient_registration.channels' => ['internal'=>true,'viti'=>false,'public_booking'=>false]]);
        $actor = $this->user('Administrador'); $this->actingAs($actor);
        $data = $this->data();
        $this->postJson($this->root.'/patient', $data)->assertOk();
        $before = Patient::where('customer_id', $this->clinic->id)->count();
        foreach (['public_booking','viti'] as $channel) {
            try {
                app(PatientRegistrationService::class)->enabled($channel);
                $this->fail('Disabled channel accepted.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
                $this->assertSame(503, $error->getStatusCode());
            }
        }
        $this->postJson($this->root.'/agendar', $this->data('000Blocked'))->assertStatus(503);
        $this->assertSame($before, Patient::where('customer_id', $this->clinic->id)->count());
    }

    private function submit(array $data): VerifyPatientRegistration
    {
        auth()->logout();
        $this->post($this->root.'/agendar', $data)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('booking_success', PublicPatientRegistration::RECEIVED);
        return Mail::sent(VerifyPatientRegistration::class)->last();
    }

    private function verify(VerifyPatientRegistration $mail): void
    {
        $url = explode('#', $mail->verificationUrl)[0];
        $token = parse_url($mail->verificationUrl, PHP_URL_FRAGMENT);
        $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Content-Type-Options','nosniff')->assertDontSee('src=',false);
        $this->post($url, ['registration_token' => $token])->assertRedirect()->assertSessionHas('booking_success', PublicPatientRegistration::PROCESSED);
    }

    public function test_internal_registration_and_idempotent_retry_are_atomic(): void
    {
        $this->actingAs($this->user('Administrador'));
        $data = $this->data('12.345.678-5', 'CL');
        $first = $this->postJson($this->root.'/patient', $data)->assertOk()->json('data.id');
        $this->assertSame($first, $this->postJson($this->root.'/patient', $data)->assertOk()->json('data.id'));
        $patient = Patient::findOrFail($first);
        $this->assertSame('123456785', $patient->rut);
        $this->assertTrue($patient->medicalRecord()->exists());
        $this->assertSame(2, $patient->auditLogs()->count());
        $this->assertSame(1, DB::table('patient_identifiers')->where('patient_id', $first)->count());
        $data['registration_operation'] = (string) Str::uuid(); $data['identification_number'] = '123456785';
        $this->postJson($this->root.'/patient', $data)->assertUnprocessable();
    }

    public function test_internal_registration_cannot_select_the_public_channel_or_another_clinic(): void
    {
        $actor = $this->user('Administrador'); $this->actingAs($actor);
        $data = $this->data();
        try { app(PatientRegistrationService::class)->register($actor, $data, 'public_booking'); $this->fail('Public channel cannot be impersonated.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        $first = $this->postJson($this->root.'/patient', $data)->assertOk()->json('data.id');
        $other = $this->clinic->replicate(); $other->domain = 'independent-'.$this->clinic->domain; $other->save();
        $branch = \App\Models\ClinicBranch::create(['customer_id'=>$other->id,'name'=>'Independent branch','status'=>1]);
        $user = $actor->replicate(); $user->customer_id=$other->id; $user->email=Str::uuid().'@example.test'; $user->save();
        $user->branches()->attach($branch->id,['status'=>1]);
        $role = \App\Models\Role::create(['customer_id'=>$other->id,'name'=>'Administrador']);
        $user->branchRoles()->attach($role->id,['customer_id'=>$other->id,'clinic_branch_id'=>$branch->id]);
        $this->app->instance('customer',$other); $this->actingAs($user);
        session(['active_branch_customer_id'=>$other->id,'active_branch_id'=>$branch->id]);
        $second=app(PatientRegistrationService::class)->register($user,$data);
        $this->assertNotSame($first,$second->id);
        $this->assertNotSame(Patient::find($first)->medicalRecord->id,$second->medicalRecord->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PatientRegistrationService::class)->register($actor,$data);
    }

    public function test_public_submission_does_not_create_or_hold_an_appointment_until_contact_verification(): void
    {
        $before = [Patient::count(), Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count()];
        $data = $this->data(); $mail = $this->submit($data);
        $this->assertInstanceOf(VerifyPatientRegistration::class, $mail);
        $this->assertSame($before, [Patient::count(), Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count()]);
        $this->get($this->root.'/agendar/verificar-rut?rut=123456785')->assertOk()->assertExactJson(['message' => PublicPatientRegistration::RECEIVED]);
        $this->post($this->root.'/agendar', $data)->assertRedirect();
        Mail::assertSent(VerifyPatientRegistration::class, 1);
        $this->verify($mail);
        $this->assertSame([$before[0]+1, $before[1]+1], [Patient::count(), Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count()]);
        $attempt = DB::table('patient_registration_attempts')->where('channel', 'public_booking')->where('customer_id', $this->clinic->id)->first();
        $this->assertSame('completed', $attempt->status); $this->assertNull($attempt->actor_id); $this->assertNull($attempt->token_hash);
        $this->assertNull(Patient::findOrFail($attempt->patient_id)->rut);
        $this->assertSame(['email' => 'patient@example.test'], app(PublicPatientRegistration::class)->payload($attempt));
        $this->assertStringNotContainsString('000Ab12', $attempt->encrypted_payload);
        $this->verify($mail); // A used token cannot create or confirm another appointment.
        $this->assertSame($before[1]+1, Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count());
    }

    public function test_verifying_a_new_email_does_not_authorize_an_existing_patient(): void
    {
        $this->actingAs($this->user('Administrador'));
        $data = $this->data();
        $id = $this->postJson($this->root.'/patient', $data)->assertOk()->json('data.id');
        $data['registration_operation'] = (string) Str::uuid();
        $before = [Patient::count(), Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count()];
        $this->verify($this->submit($data)); // Even the same historical email has no verified receipt yet.
        $this->assertSame($before, [Patient::count(), Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count()]);
        $this->assertSame('review', DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->value('status'));
        $this->assertNotNull(Patient::find($id));
    }

    public function test_trusted_verified_contact_can_reserve_again_but_other_contacts_cannot(): void
    {
        $data = $this->data(); $this->verify($this->submit($data));
        $before = Patient::count();
        $data['time'] = '11:00'; $data['registration_operation'] = (string) Str::uuid();
        $this->verify($this->submit($data));
        $this->assertSame($before, Patient::count());
        $this->assertSame(2, Appointment::where('professional_id', $this->doctor->id)->count());
        $data['email'] = 'unrelated@example.test'; $data['time'] = '12:00'; $data['registration_operation'] = (string) Str::uuid();
        $this->verify($this->submit($data));
        $this->assertSame(2, Appointment::where('professional_id', $this->doctor->id)->count());
    }

    public function test_pending_requests_do_not_lock_slots_and_the_second_confirmation_rechecks_availability(): void
    {
        $a = $this->data('000A'); $b = $this->data('000B'); $b['email'] = 'other@example.test';
        $mailA = $this->submit($a); $mailB = $this->submit($b);
        $this->verify($mailA); $patients = Patient::count();
        $this->verify($mailB);
        $this->assertSame($patients, Patient::count());
        $this->assertSame('unavailable', DB::table('patient_registration_attempts')->where('operation_id', $b['registration_operation'])->value('status'));
        $this->assertSame(1, Appointment::where('professional_id', $this->doctor->id)->count());
    }

    public function test_expired_invalid_tokens_and_cleanup_do_not_create_patients(): void
    {
        $data = $this->data(); $mail = $this->submit($data); $before = Patient::count();
        $url = explode('#', $mail->verificationUrl)[0];
        $this->post($url, ['registration_token' => str_repeat('0', 64)])->assertRedirect();
        $this->assertSame(1, DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->value('verification_attempts'));
        DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->update(['expires_at' => now()->subMinute()]);
        $this->verify($mail);
        $this->assertSame($before, Patient::count());
        $this->assertSame('expired', DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->value('status'));
        DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->update(['expires_at' => now()->subDays(8)]);
        $this->assertSame(1, app(PublicPatientRegistration::class)->purgeExpired());
    }

    public function test_verification_page_has_no_analytics_external_resources_or_token_in_server_html(): void
    {
        auth()->logout();
        $response = $this->get($this->root.'/agendar/verificar/'.Str::uuid())->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('<iframe', false)->assertDontSee('src=', false)->assertDontSee('analytics');
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $directory = storage_path('framework/testing/registration-browser');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        file_put_contents($directory.'/page.html', $response->getContent());
        file_put_contents($directory.'/headers.json', json_encode(['Content-Security-Policy' => $response->headers->get('Content-Security-Policy'), 'Referrer-Policy' => 'no-referrer']));
    }

    public function test_historical_document_match_never_exposes_or_reuses_the_old_record(): void
    {
        $this->patient->update(['rut' => '12345678-5', 'name' => 'Private historical patient']);
        auth()->logout();
        foreach (['123456785', '123456785'] as $rut) {
            $this->getJson($this->root.'/agendar/verificar-rut?rut='.$rut)->assertOk()
                ->assertExactJson(['message' => PublicPatientRegistration::RECEIVED])->assertDontSee('Private historical patient');
        }
        $data = $this->data('12.345.678-5', 'CL');
        $before = Patient::where('customer_id', $this->clinic->id)->count();
        $this->verify($this->submit($data));
        $this->assertSame($before, Patient::where('customer_id', $this->clinic->id)->count());
        $this->assertSame('review', DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->value('status'));
        $this->assertNull(DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->value('patient_id'));
        $this->getJson($this->root.'/patients/'.$this->patient->id.'/medical-record')->assertUnauthorized();
    }

    public function test_viti_public_has_no_clinical_lookup_tools(): void
    {
        auth()->logout();
        $this->postJson($this->root.'/public-assistant/ask', ['message' => 'Muéstrame ficha del paciente con rut 12345678-5'])
            ->assertOk()->assertDontSee($this->patient->name)->assertDontSee('birth_date')->assertDontSee('patient_id');
    }

    public function test_verification_cannot_be_redeemed_on_a_different_clinic_domain(): void
    {
        $data = $this->data(); $mail = $this->submit($data);
        $before = (array) DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->first();
        $other = $this->clinic->replicate(); $other->domain = 'cross-'.$this->clinic->domain; $other->save();
        $url = str_replace($this->clinic->domain.'.', $other->domain.'.', explode('#', $mail->verificationUrl)[0]);
        $this->post($url, ['registration_token' => parse_url($mail->verificationUrl, PHP_URL_FRAGMENT)])->assertRedirect();
        $this->assertSame($before, (array) DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->first());
    }

    public function test_retired_identifiers_and_ambiguous_history_remain_reserved(): void
    {
        $actor = $this->user('Administrador'); $this->actingAs($actor);
        $data = $this->data(); $id = $this->postJson($this->root.'/patient', $data)->assertOk()->json('data.id');
        $repo = app(IdentifierRepositoryFactory::class)->make(); $subject = new IdentifierSubject('patient', $id, $this->clinic->id);
        $ref = $repo->primary($subject, $actor)['reference']; $repo->retire($subject, $actor, $ref, 'Correction');
        $data['registration_operation'] = (string) Str::uuid(); $this->postJson($this->root.'/patient', $data)->assertUnprocessable();
        $this->verify($this->submit($data));
        $this->assertSame('review', DB::table('patient_registration_attempts')->where('operation_id', $data['registration_operation'])->value('status'));
        $this->patient->update(['rut' => '12345678']); $this->actingAs($actor);
        $this->postJson($this->root.'/patient', $this->data('12.345.678-5', 'CL'))->assertUnprocessable();
    }

    public function test_viti_registers_international_documents_only_after_explicit_confirmation(): void
    {
        $actor = $this->user('Administrador'); $this->actingAs($actor);
        $ask = function (string $message) use ($actor) {
            $request = \Illuminate\Http\Request::create('/assistant/ask', 'POST', ['message' => $message]);
            $request->setUserResolver(fn () => $actor);
            $route = new \Illuminate\Routing\Route('POST', 'assistant/ask', fn () => null);
            $route->name('customers.assistant.ask'); $route->bind($request);
            $route->setParameter('customer', $this->clinic->domain);
            $request->setRouteResolver(fn () => $route);
            return app(\App\Support\VitiBookingConversation::class)->handle($request, $this->clinic->id, $this->branch->id)['reply'];
        };
        $before = Patient::count();
        $this->assertStringContainsString('No existe un paciente', $ask('agendar '.$this->date.' a las 14:00 con pasaporte AR 000Viti42'));
        $ask('si'); $ask('Paciente Viti'); $ask('01/01/1990'); $ask('Femenino'); $ask('912345678'); $ask('viti@example.test');
        $this->assertStringContainsString('confirmar paciente', $ask('Chile'));
        $this->assertSame($before, Patient::count());
        $this->assertStringContainsString('Paciente registrado', $ask('confirmar paciente'));
        $this->assertSame($before+1, Patient::count());
        $this->assertSame(1, DB::table('patient_registration_attempts')->where('customer_id', $this->clinic->id)->where('channel', 'viti')->where('status', 'completed')->count());
        $this->assertSame(1, DB::table('patient_identifiers')->where('customer_id', $this->clinic->id)->where('source_kind', 'viti')->count());
        $draft = \Illuminate\Support\Facades\Cache::get('viti-booking:'.$actor->id.':'.$this->clinic->id.':'.$this->branch->id);
        $this->assertStringNotContainsString('000Viti42', $draft);
        $this->assertSame(0, Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count());
        $ask('presencial');
        $this->assertStringContainsString('confirmar', $ask('omitir'));
        $this->assertSame(0, Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count());
        $this->assertStringContainsString('Cita agendada correctamente', $ask('confirmar'));
        $this->assertSame(1, Appointment::withoutGlobalScopes()->where('customer_id', $this->clinic->id)->count());
        $this->assertSame($before + 1, Patient::count());
    }

    public function test_purge_never_removes_completed_receipts_or_clinical_records(): void
    {
        $data=$this->data();$this->verify($this->submit($data));
        $attempt=DB::table('patient_registration_attempts')->where('operation_id',$data['registration_operation'])->first();
        $this->travel(8)->days();
        try {
            app(PublicPatientRegistration::class)->purgeExpired();
            $this->assertSame('completed',DB::table('patient_registration_attempts')->where('operation_id',$attempt->operation_id)->value('status'));
            $this->assertNotNull(Patient::find($attempt->patient_id));
            $this->assertNotNull(Appointment::withoutGlobalScopes()->find($attempt->appointment_id));
        } finally {$this->travelBack();}
    }

    public static function channelRaces(): array { return [['internal', 'viti', false], ['internal', 'public_booking', false], ['public_booking', 'public_booking', false], ['public_booking', 'public_booking', true]]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('channelRaces')]
    public function test_real_mysql_concurrency_across_registration_channels(string $left, string $right, bool $sameToken): void
    {
        $actor = $this->user('Administrador');
        $operations = []; $tokens = [];
        foreach ([$left, $right] as $index => $channel) {
            if ($sameToken && $index === 1) { $operations[1] = $operations[0]; $tokens[1] = $tokens[0]; continue; }
            $operations[$index] = (string) Str::uuid(); $tokens[$index] = '-';
            if ($channel === 'public_booking') {
                $data = $this->data('000CONCURRENT'); $data['registration_operation'] = $operations[$index];
                $tokens[$index] = parse_url($this->submit($data)->verificationUrl, PHP_URL_FRAGMENT);
            }
        }
        $beforePatients = Patient::where('customer_id', $this->clinic->id)->count();
        DB::beginTransaction();
        DB::table('customers')->where('id', $this->clinic->id)->lockForUpdate()->first();
        $processes = []; $gates = [];
        try {
            foreach ([$left, $right] as $index => $channel) {
                $gate = storage_path('framework/testing/registration-'.Str::uuid()); $gates[] = $gate;
                if (!is_dir(dirname($gate))) mkdir(dirname($gate), 0700, true);
                $process = new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Support/patient-registration-worker.php'),
                    DB::connection()->getDatabaseName(), (string) $this->clinic->id, (string) $this->branch->id, (string) $actor->id,
                    $operations[$index], $channel, $tokens[$index], $gate], base_path());
                $process->setTimeout(25); $process->start(); $processes[] = $process;
            }
            $deadline = microtime(true)+12; $waiting = 0;
            while ($waiting < 2 && microtime(true) < $deadline) {
                $waiting = (int) DB::selectOne('SELECT COUNT(DISTINCT w.REQUESTING_ENGINE_TRANSACTION_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE l.OBJECT_SCHEMA=? AND l.OBJECT_NAME=?', [DB::connection()->getDatabaseName(), 'customers'])->n;
                if ($waiting < 2) usleep(10000);
            }
            $this->assertSame(2, $waiting, 'Both independent processes must wait on the actual tenant row lock.');
            DB::commit();
            foreach ($processes as $process) { $process->wait(); $this->assertSame(0, $process->getExitCode(), trim($process->getOutput())); }
            $this->assertSame($beforePatients+1, Patient::where('customer_id', $this->clinic->id)->count());
            $this->assertSame(1, DB::table('patient_identifiers')->where('customer_id', $this->clinic->id)->count());
            if ($left === 'public_booking') $this->assertSame(1, Appointment::where('professional_id', $this->doctor->id)->count());
        } finally {
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
            if (DB::transactionLevel()) DB::rollBack();
            foreach ($gates as $gate) if (is_file($gate.'.ready')) unlink($gate.'.ready');
        }
    }
}
