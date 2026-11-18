<?php

namespace App\Services\Patients;

use App\Models\{ClinicBranch, Customer, Patient, User};
use App\Services\Identification\{IdentificationService, InvalidIdentification};
use App\Services\Identification\Storage\IdentifierRepositoryFactory;
use App\Support\{ClinicBranchContext, PatientAuditLogger};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PatientRegistrationService
{
    public function __construct(private PatientIdentityResolver $resolver) {}

    public function document(array $data): \App\Services\Identification\DocumentIdentifier
    {
        Validator::make($data, ['identification_country' => 'sometimes|required|string|size:2',
            'identification_type' => 'sometimes|required|string|max:32', 'identification_number' => 'sometimes|required|string|max:128',
            'rut' => 'sometimes|nullable|string|max:128', 'identification_holder' => 'sometimes|in:natural_person'])->validate();
        try {
            return app(IdentificationService::class)->identify($data['identification_country'] ?? 'CL',
                $data['identification_type'] ?? 'national_id', $data['identification_number'] ?? $data['rut'] ?? null, 'natural_person');
        } catch (InvalidIdentification $error) {
            throw ValidationException::withMessages(['identification_number' => $error->getMessage()]);
        }
    }

    public function enabled(string $channel = 'internal'): void
    {
        abort_unless(config('patient_registration.enabled') && config('patient_registration.channels.'.$channel, false) && \Illuminate\Support\Facades\Schema::hasTable('patient_registration_attempts'), 503, 'El registro está temporalmente en preparación.');
    }

    public function authorize(User $actor, int $branch, string $permission): void
    {
        $actor = $actor->fresh();
        $customer = (int) app('customer')->id;
        abort_unless($actor && (int) $actor->status === 1 && (int) $actor->customer_id === $customer
            && (int) session('active_branch_customer_id') === $customer && (int) session('active_branch_id') === $branch
            && ClinicBranchContext::availableBranches($actor, $customer)->contains('id', $branch)
            && ClinicBranchContext::hasPermissionInBranch($actor, $customer, $branch, $permission), 403);
    }

    public function fields(array $data, bool $public = false): array
    {
        $customer = (int) app('customer')->id;
        return Validator::make($data, [
            'name' => 'required|string|max:255', 'gender' => 'required|in:Masculino,Femenino,Otro',
            'birth_date' => ($public ? 'nullable' : 'required').'|date|before_or_equal:today',
            'phone' => 'required|string|max:20', 'email' => 'required|email|max:255',
            'address' => ($public ? 'nullable' : 'required').'|string|max:255',
            'insurance_id' => ['nullable', \Illuminate\Validation\Rule::exists('insurances', 'id')->where('customer_id', $customer)],
        ])->validate();
    }

    public function lockClinic(int $branch): void
    {
        $clinic = Customer::whereKey(app('customer')->id)->lockForUpdate()->firstOrFail();
        abort_unless($clinic->operating_country_code === 'CL' && (int) $clinic->status === 1, 403);
        abort_unless(ClinicBranch::where('customer_id', $clinic->id)->whereKey($branch)->where('status', true)->exists(), 403);
    }

    public function register(User $actor, array $data, string $channel = 'internal', ?string $operation = null): Patient
    {
        $this->enabled($channel);
        abort_unless(in_array($channel, ['internal', 'viti'], true), 403);
        $branch = (int) session('active_branch_id');
        $this->authorize($actor, $branch, 'patients.create');
        $document = $this->document($data);
        $fields = $this->fields($data);
        $operation ??= (string) Str::uuid();
        Validator::make(['operation' => $operation], ['operation' => 'required|uuid'])->validate();
        try { return DB::transaction(function () use ($actor, $branch, $document, $fields, $channel, $operation) {
            $this->lockClinic($branch);
            $this->authorize($actor, $branch, 'patients.create');
            $crypto = $this->resolver->crypto();
            $requestHashes = $crypto->hashes(['purpose' => 'patient-registration', 'customer' => (int) app('customer')->id,
                'branch' => $branch, 'channel' => $channel, 'actor' => $actor->id, 'fields' => $fields,
                'document' => [$document->definition->country, $document->definition->namespace, $document->number->normalized]]);
            $attempt = DB::table('patient_registration_attempts')->where('operation_id', $operation)->lockForUpdate()->first();
            if ($attempt) {
                if ((int) $attempt->customer_id !== (int) app('customer')->id || (int) $attempt->actor_id !== (int) $actor->id
                    || $attempt->channel !== $channel || (int) $attempt->clinic_branch_id !== $branch
                    || !isset($requestHashes[$attempt->lookup_key_version]) || !hash_equals($attempt->request_hash, $requestHashes[$attempt->lookup_key_version])
                    || $attempt->status !== 'completed') $this->conflict();
                return Patient::where('customer_id', app('customer')->id)->findOrFail($attempt->patient_id);
            }
            $resolution = $this->resolver->resolve((int) app('customer')->id, $document);
            if ($resolution['state'] !== 'new') $this->conflict();
            DB::table('patient_registration_attempts')->insert(['operation_id' => $operation, 'customer_id' => app('customer')->id,
                'clinic_branch_id' => $branch, 'actor_id' => $actor->id, 'channel' => $channel, 'status' => 'processing',
                'lookup_key_version' => $crypto->lookupVersion, 'document_hash' => $this->resolver->hashes((int) app('customer')->id, $document)[$crypto->lookupVersion],
                'request_hash' => $requestHashes[$crypto->lookupVersion], 'created_at' => now(), 'updated_at' => now()]);
            $patient = $this->create($fields, $document, $branch, $actor, $channel, $operation);
            DB::table('patient_registration_attempts')->where('operation_id', $operation)->update(['status' => 'completed', 'patient_id' => $patient->id, 'updated_at' => now()]);
            return $patient;
        }, 3); } catch (\Illuminate\Database\QueryException) {
            throw new \RuntimeException('No se pudo completar el registro transaccional.');
        }
    }

    public function lookup(User $actor, array $data): ?Patient
    {
        $this->authorize($actor, (int) session('active_branch_id'), 'patients.view');
        $result = $this->resolver->resolve((int) app('customer')->id, $this->document($data));
        if ($result['state'] === 'new') return null;
        if ($result['state'] !== 'existing') $this->conflict();
        $patient = $result['patient'];
        abort_unless(!$patient->clinic_branch_id || (int) $patient->clinic_branch_id === (int) session('active_branch_id')
            || ClinicBranchContext::canAdministerAllBranches($actor, (int) app('customer')->id), 403);
        return $patient;
    }

    /** Public entry requires a verified, locked processing attempt and never returns an HTTP projection. */
    public function registerVerifiedPublic(object $attempt, array $data): ?Patient
    {
        abort_unless(DB::transactionLevel() > 0 && $attempt->channel === 'public_booking' && $attempt->status === 'processing'
            && $attempt->contact_verified_at && (int) $attempt->customer_id === (int) app('customer')->id, 403);
        $stored = DB::table('patient_registration_attempts')->where('operation_id', $attempt->operation_id)
            ->where('customer_id', app('customer')->id)->where('channel', 'public_booking')->where('status', 'processing')
            ->whereNotNull('contact_verified_at')->lockForUpdate()->first();
        abort_unless($stored, 403);
        $attempt = $stored;
        $document = $this->document($data);
        $hashes = $this->resolver->hashes((int) $attempt->customer_id, $document);
        abort_unless(isset($hashes[$attempt->lookup_key_version]) && hash_equals($attempt->document_hash, $hashes[$attempt->lookup_key_version]), 403);
        $result = $this->resolver->resolve((int) $attempt->customer_id, $document);
        if ($result['state'] === 'new') return $this->create($this->fields($data, true), $document, (int) $attempt->clinic_branch_id, null, 'public_booking', $attempt->operation_id);
        if ($result['state'] !== 'existing') return null;
        $patient = $result['patient'];
        // Prior successful self-service booking is explicit ownership evidence, not a document-only candidate link.
        $grant=\Illuminate\Support\Facades\Schema::hasTable('portal_booking_authorizations') ? DB::table('portal_booking_authorizations')->where('registration_operation',$attempt->operation_id)
            ->where('customer_id',$attempt->customer_id)->whereNotNull('redeemed_at')->whereNull('completed_at')->first() : null;
        if ($grant) {
            $bridge=app(\App\Services\MiSalud\PortalBookingService::class);
            $current=$bridge->payload($grant)['personal'];
            foreach (DB::table('portal_booking_authorizations')->where('customer_id',$attempt->customer_id)
                ->where('portal_account_id',$grant->portal_account_id)->where('patient_id',$patient->id)->whereNotNull('completed_at')->get() as $evidence) {
                $previous=$bridge->payload($evidence)['personal'];
                $keys=['identification_country','identification_type','identification_number'];
                if (array_intersect_key($previous,array_flip($keys))===array_intersect_key($current,array_flip($keys))) return $patient;
            }
        }
        $email = mb_strtolower(trim($data['email']));
        if (!hash_equals(mb_strtolower(trim((string) $patient->email)), $email)) return null;
        // Historical emails are not trusted merely because they exist in patients.email.
        foreach (DB::table('patient_registration_attempts')->where('customer_id', $attempt->customer_id)->where('patient_id', $patient->id)
            ->where('channel', 'public_booking')->where('status', 'completed')->whereNotNull('contact_verified_at')->get() as $receipt) {
            $payload = app(PublicPatientRegistration::class)->payload($receipt);
            if (hash_equals(mb_strtolower(trim($payload['email'] ?? '')), $email)) return $patient;
        }
        return null;
    }

    private function create(array $fields, $document, int $branch, ?User $actor, string $channel, string $operation): Patient
    {
        $patient = Patient::create($fields + ['customer_id' => app('customer')->id, 'clinic_branch_id' => $branch,
            'rut' => $document->definition->namespace === 'CL:RUT' ? $document->number->normalized : null, 'status' => 1]);
        $factory = app(IdentifierRepositoryFactory::class);
        $repository = $actor ? $factory->forNewPatient($patient) : $factory->make();
        $repository->registerPatientIdentifier($patient, $actor, $document, $channel, $operation);
        $patient->medicalRecord()->create(['customer_id' => $patient->customer_id]);
        PatientAuditLogger::record($patient, 'created', 'Datos del paciente', [], ['origen' => $channel, 'operación' => $operation], $actor === null);
        PatientAuditLogger::record($patient, 'created', 'Ficha clínica', [], ['ficha' => 'Creada con registro central'], $actor === null);
        return $patient;
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['identification_number' => 'Existe una reserva documental o una coincidencia que requiere revisión autorizada. No se creó otro paciente.']);
    }
}
