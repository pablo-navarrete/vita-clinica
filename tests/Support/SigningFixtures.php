<?php
namespace Tests\Support;

use App\Models\{ClinicBranch, Customer, Encounter, EncounterDocumentVersion, EncounterSignature, EncounterSigningOperation, Patient, PatientDocument, Role, User};
use App\Services\Signatures\{EncounterSigningService, EncounterSignatureVerifier};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

trait SigningFixtures
{
    protected Customer $clinic;
    protected User $doctor;
    protected ClinicBranch $branch;
    protected Patient $patient;
    protected Encounter $encounter;
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Storage::fake('signed_encounters'); Storage::fake('local');
        $this->fixtures();
    }

    protected function fixtures(): void
    {
        $id = Str::lower(Str::random(12));
        $this->clinic = Customer::create(['name' => 'Signing test', 'legal_name' => 'Signing test', 'domain' => 'sign-' . $id,
            'holder' => 'Test', 'rut' => $id, 'phone' => '123', 'email' => $id . '@example.test', 'status' => 1]);
        $this->branch = ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Signing branch', 'status' => 1]);
        $this->doctor = $this->user();
        $this->patient = Patient::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id, 'name' => 'Signing Patient', 'rut' => $id, 'gender' => 'male', 'birth_date' => '1990-01-01']);
        $this->encounter = Encounter::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id, 'professional_id' => $this->doctor->id, 'status' => 1,
            'chief_complaint' => 'Motivo de prueba', 'started_at' => now(), 'modality' => 'in_person']);
        $this->root = 'http://' . $this->clinic->domain . '.' . config('app.domain');
        $this->actingAs($this->doctor);
        $this->app->instance('customer', $this->clinic);
        session(['active_branch_customer_id' => $this->clinic->id, 'active_branch_id' => $this->branch->id]);
    }

    protected function user(string $role = 'Doctor'): User
    {
        $id = Str::lower(Str::random(12));
        $user = User::create(['customer_id' => $this->clinic->id, 'name' => 'Doctor test', 'rut' => $id, 'phone' => '123',
            'email' => $id . '@example.test', 'password' => 'correct-password', 'status' => 1]);
        $user->branches()->attach($this->branch->id, ['status' => 1]);
        $role = Role::firstOrCreate(['customer_id' => $this->clinic->id, 'name' => $role]);
        $user->branchRoles()->attach($role->id, ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id]);
        return $user;
    }

    protected function tearDown(): void { DB::rollBack(); parent::tearDown(); }

    protected function endpoint(string $action): string
    {
        return $this->root . '/patients/' . $this->patient->id . '/encounters/' . $this->encounter->id . '/' . $action;
    }

    protected function prepare(): string
    {
        return $this->postJson($this->endpoint('signature-preview'), ['clinical_assessment' => 'Diagnóstico firmado', 'treatment_plan' => 'Tratamiento firmado'])
            ->assertOk()->assertJsonStructure(['operation_id', 'hash', 'html'])->json('operation_id');
    }

    protected function sign(string $operation, array $override = [])
    {
        return $this->patchJson($this->endpoint('finish'), array_merge(['operation_id' => $operation, 'acceptance' => 1, 'password' => 'correct-password'], $override));
    }

    protected function attachment(): PatientDocument
    {
        Storage::disk('local')->put('signing-test.txt', 'private attachment bytes');
        return PatientDocument::create(['customer_id' => $this->clinic->id, 'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id, 'clinic_branch_id' => $this->branch->id, 'uploaded_by' => $this->doctor->id,
            'original_name' => 'test.pdf', 'disk' => 'local', 'path' => 'signing-test.txt', 'mime_type' => 'application/pdf',
            'size' => 24, 'sha256' => hash('sha256', 'private attachment bytes')]);
    }

}
