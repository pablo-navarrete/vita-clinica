<?php

namespace App\Services\Identification\Storage;

use App\Models\User;
use App\Services\Identification\IdentificationService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Not connected to any current writer. All operations require an actor and explicit subject. */
final class IdentifierRepository
{
    /** Trusted integration worker only; never used as an HTTP presentation. */
    public function documentForPortalLinking(\App\Models\Patient $patient): ?array
    {
        $subject=new IdentifierSubject('patient',(int)$patient->id,(int)$patient->customer_id);
        $row=$this->owned($subject)->where('is_primary',true)->whereNull('retired_at')->where('record_state','validated')->first();
        if (!$row) return null;
        $payload=$this->payload($row,$subject);
        return ['reference'=>$row->record_uuid,'document'=>$this->identification->identify($row->issuing_country_code,$row->document_type,$payload['original'],'natural_person')];
    }
    /** Patient search is tenant scoped; callers still intersect with their permitted patient query. */
    public function patientMatches(User $actor, int $customer, string $country, string $type, string $number, bool $claims = false): array
    {
        $this->authorizePatientPresentation($actor, $customer);
        $document = $this->identification->identify($country, $type, $number, 'natural_person');
        $subject = new IdentifierSubject('patient', 1, $customer);
        $hashes = $this->crypto->hashes($subject->lookupContext($document));
        $column = $claims ? 'claim_hash' : 'lookup_hash';
        $query = $this->scoped($subject)->where('record_state', 'validated')->where(function ($q) use ($hashes, $column) {
            foreach ($hashes as $version => $hash) $q->orWhere(fn ($q) => $q->where('lookup_key_version', $version)->where($column, $hash));
        });
        if (! $claims) $query->whereNull('retired_at');
        return $query->pluck('patient_id')->map(fn ($id) => (int) $id)->unique()->all();
    }

    /** Only masked projections escape this method; no general-view permission grants plaintext access. */
    public function patientMasked(\App\Models\Patient $patient, User $actor): string
    {
        $this->authorizePatientPresentation($actor, (int) $patient->customer_id);
        $subject = new IdentifierSubject('patient', (int) $patient->id, (int) $patient->customer_id);
        $row = $this->owned($subject)->where('is_primary', true)->whereNull('retired_at')->where('record_state', 'validated')->first();
        $identity = ['rut' => $patient->rut];
        if ($row) {
            $payload = $this->payload($row, $subject);
            $identity = ['document' => ['display' => $payload['display'], 'country' => $row->issuing_country_code, 'type' => $row->document_type, 'namespace' => $row->identifier_namespace]];
        }
        return app(\App\Services\Identification\DocumentIdentityPresenter::class)->label($identity, true);
    }

    private function authorizePatientPresentation(User $actor, int $customer): void
    {
        $branch = (int) session('active_branch_id');
        $actor = $actor->fresh();
        $allowed = $actor && (int) $actor->status === 1 && (int) $actor->customer_id === $customer
            && (int) app('customer')->id === $customer && (int) session('active_branch_customer_id') === $customer
            && \App\Support\ClinicBranchContext::availableBranches($actor, $customer)->contains('id', $branch);
        $permission = false;
        if ($allowed) foreach (['patients.view', 'patients.create', 'patients.update', 'appointments.view', 'chat.view', 'dashboard.view', 'medical_records.view', 'medical_records.manage', 'schedules.manage'] as $name) {
            $permission = $permission || \App\Support\ClinicBranchContext::hasPermissionInBranch($actor, $customer, $branch, $name);
        }
        if (! $allowed || ! $permission) throw new \Illuminate\Auth\Access\AuthorizationException('No tienes autorización para buscar pacientes.');
    }
    public function __construct(private ConnectionInterface $db, private IdentifierCrypto $crypto,
        private IdentifierAuthorizer $authorization, private IdentificationService $identification = new IdentificationService) {}

    public function create(IdentifierSubject $subject, User $actor, string $country, string $type,
        mixed $number, string $holderKind = 'natural_person', bool $primary = false): string
    {
        $document = $this->identification->identify($country, $type, $number, $holderKind);
        if ($subject->kind !== 'customer' && $holderKind !== 'natural_person') {
            throw new InvalidArgumentException('Usuarios y pacientes requieren persona natural.');
        }

        return $this->write($subject, $actor, function () use ($subject, $actor, $document, $primary) {
            return $this->insertDocument($subject, $actor, $document, $primary, 'manual');
        });
    }

    /** Narrow registration-only entry; does not grant public read or update access. */
    public function registerPatientIdentifier(\App\Models\Patient $patient, ?User $actor,
        \App\Services\Identification\DocumentIdentifier $document, string $channel, ?string $operation = null): string
    {
        $subject = new IdentifierSubject('patient', (int) $patient->id, (int) $patient->customer_id);
        if (!$patient->wasRecentlyCreated || $this->db->transactionLevel() < 1) throw new \LogicException('Registration transaction required.');
        if ($actor) return $this->write($subject, $actor, fn () => $this->insertDocument($subject, $actor, $document, true, $channel));
        $attempt = $this->db->table('patient_registration_attempts')->where('operation_id', $operation)
            ->where('customer_id', $patient->customer_id)->where('clinic_branch_id', $patient->clinic_branch_id)
            ->where('channel', 'public_booking')->where('status', 'processing')->whereNotNull('contact_verified_at')->first();
        $hashes = $this->crypto->hashes($subject->lookupContext($document));
        if (!$attempt || (int) app('customer')->id !== (int) $patient->customer_id
            || !isset($hashes[$attempt->lookup_key_version]) || !hash_equals($attempt->document_hash, $hashes[$attempt->lookup_key_version])) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Invalid public registration context.');
        }
        return $this->insertDocument($subject, null, $document, true, 'public_booking');
    }

    private function insertDocument(IdentifierSubject $subject, ?User $actor,
        \App\Services\Identification\DocumentIdentifier $document, bool $primary, string $source): string
    {
            $this->assertWriterVersion($subject);
            $hashes = $this->crypto->hashes($subject->lookupContext($document));
            if ($this->matching($subject, $hashes, true)->exists()) {
                throw new InvalidArgumentException('El documento ya está registrado, incluso si está retirado.');
            }
            $uuid = (string) Str::uuid();
            $row = ['record_uuid' => $uuid, 'customer_id' => $subject->customerId, 'scope_key' => $subject->scopeKey(),
                'issuing_country_code' => $document->definition->country, 'document_type' => $document->definition->type->value,
                'identifier_namespace' => $document->definition->namespace, 'holder_kind' => $document->definition->holderKind->value,
                'normalization_version' => $document->number->ruleVersion, 'validation_status' => $document->number->level->value,
                'record_state' => 'validated', 'source_kind' => $source, 'is_primary' => false, 'revision' => 1,
                'encryption_key_version' => $this->crypto->encryptionVersion, 'lookup_key_version' => $this->crypto->lookupVersion,
                'lookup_hash' => $hashes[$this->crypto->lookupVersion], 'claim_hash' => $hashes[$this->crypto->lookupVersion],
                'created_by' => $actor?->id, 'last_changed_by' => $actor?->id, 'created_at' => now(), 'updated_at' => now()];
            if ($subject->kind !== 'customer') {
                $row[$subject->ownerColumn()] = $subject->ownerId;
            }
            $payload = ['original' => $document->original, 'normalized' => $document->number->normalized, 'display' => $document->number->display,
                'holder_identity_verified' => false, 'audit' => [$this->event('created', $actor)]];
            $row['encrypted_payload'] = $this->crypto->encrypt($payload, $this->binding((object) $row, $subject));
            $this->db->table($subject->table())->insert($row);
            if ($subject->kind==='patient') app(\App\Services\MiSalud\PortalPatientLinkService::class)->request('patient',$subject->ownerId);
            if ($primary) {
                $this->selectPrimary($subject, $actor, $uuid);
            }

            return $uuid;
    }

    /** Returns references only. Explicit read() is required to decrypt a document. */
    public function findExact(IdentifierSubject $subject, User $actor, string $country, string $type,
        mixed $number, string $holderKind = 'natural_person'): array
    {
        $this->authorize($subject, $actor, false);
        $document = $this->identification->identify($country, $type, $number, $holderKind);
        $rows = $this->matching($subject, $this->crypto->hashes($subject->lookupContext($document)), false)
            ->whereNull('retired_at')->where('record_state', 'validated')->get();
        $found = [];
        foreach ($rows as $row) {
            $payload = $this->payload($row, $subject);
            if ($row->identifier_namespace === $document->definition->namespace
                && $row->issuing_country_code === $document->definition->country
                && $row->holder_kind === $holderKind && hash_equals($payload['normalized'], $document->number->normalized)) {
                $found[] = $row->record_uuid;
            }
        }

        return $found;
    }

    public function read(IdentifierSubject $subject, User $actor, string $reference): array
    {
        $this->authorize($subject, $actor, false);
        $row = $this->owned($subject)->where('record_uuid', $reference)->first();
        if (! $row) {
            throw new InvalidArgumentException('No existe el identificador en este contexto.');
        }

        return $this->payload($row, $subject);
    }

    /** Explicit authorized form projection. Never expose ciphertext, hashes or audit payloads. */
    public function primary(IdentifierSubject $subject, User $actor): ?array
    {
        $this->authorize($subject, $actor, false);
        $row = $this->owned($subject)->where('is_primary', true)->whereNull('retired_at')->where('record_state', 'validated')->first();
        if (! $row) {
            return null;
        }
        $payload = $this->payload($row, $subject);

        return ['reference' => $row->record_uuid, 'country' => $row->issuing_country_code, 'type' => $row->document_type,
            'holder' => $row->holder_kind, 'number' => $payload['original'], 'display' => $payload['display'], 'identity_verified' => false];
    }

    /** Authorized immutable-document projection. Never returns lookup hashes or encryption metadata. */
    public function primarySnapshot(IdentifierSubject $subject, User $actor): ?array
    {
        $this->authorize($subject, $actor, false);
        $row = $this->owned($subject)->where('is_primary', true)->whereNull('retired_at')->where('record_state', 'validated')->first();
        if (! $row) {
            return null;
        }
        $payload = $this->payload($row, $subject);

        return ['reference' => $row->record_uuid, 'country' => $row->issuing_country_code,
            'type' => $row->document_type, 'namespace' => $row->identifier_namespace,
            'normalized' => $payload['normalized'], 'display' => $payload['display'],
            'normalization_version' => $row->normalization_version, 'validation_status' => $row->validation_status,
            'identity_verified' => false];
    }

    public function setPrimary(IdentifierSubject $subject, User $actor, string $reference): void
    {
        $this->write($subject, $actor, fn () => $this->selectPrimary($subject, $actor, $reference));
    }

    public function retire(IdentifierSubject $subject, User $actor, string $reference, string $reason): void
    {
        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('Indica un motivo de retiro de hasta 1000 caracteres.');
        }
        $this->write($subject, $actor, function () use ($subject, $actor, $reference, $reason) {
            $row = $this->owned($subject)->where('record_uuid', $reference)->lockForUpdate()->first();
            if (! $row || $row->retired_at !== null) {
                throw new InvalidArgumentException('El identificador no está activo en este contexto.');
            }
            $payload = $this->payload($row, $subject);
            $payload['audit'][] = $this->event('retired', $actor) + ['reason' => $reason];
            $this->update($row, $subject, $actor, $payload, ['is_primary' => false, 'retired_at' => now()->utc(), 'retired_by' => $actor->id]);
            // claim_hash is retained: retirement never makes a patient's identity available to another patient.
        });
    }

    /** Offline maintenance primitive. No command calls it. Stop old deployments before switching keys. */
    public function rotateScope(IdentifierSubject $subject, User $actor): int
    {
        return $this->write($subject, $actor, function () use ($subject, $actor) {
            $query = $subject->kind === 'patient' ? $this->scoped($subject) : $this->owned($subject);
            $count = 0;
            foreach ($query->orderBy('id')->lockForUpdate()->get() as $row) {
                $ownerSubject = new IdentifierSubject($subject->kind, (int) $row->{$subject->ownerColumn()}, $subject->customerId);
                $this->authorize($ownerSubject, $actor, true);
                $payload = $this->payload($row, $ownerSubject);
                $changes = [];
                if ($row->record_state === 'validated') {
                    $context = ['kind' => $subject->kind, 'scope' => $subject->scopeKey(), 'owner' => $subject->kind === 'patient' ? null : $subject->ownerId,
                        'country' => $row->issuing_country_code, 'namespace' => $row->identifier_namespace, 'holder' => $row->holder_kind, 'normalized' => $payload['normalized']];
                    $hash = $this->crypto->hashes($context)[$this->crypto->lookupVersion];
                    $changes = ['lookup_key_version' => $this->crypto->lookupVersion, 'lookup_hash' => $hash, 'claim_hash' => $hash];
                }
                $payload['audit'][] = $this->event('keys_rotated', $actor) + ['previous_encryption_version' => $row->encryption_key_version, 'previous_lookup_version' => $row->lookup_key_version];
                $this->update($row, $ownerSubject, $actor, $payload, $changes);
                $count++;
            }

            return $count;
        });
    }

    private function selectPrimary(IdentifierSubject $subject, ?User $actor, string $reference): void
    {
        $target = $this->owned($subject)->where('record_uuid', $reference)->lockForUpdate()->first();
        if (! $target || $target->retired_at !== null || $target->record_state !== 'validated') {
            throw new InvalidArgumentException('Solo un documento activo y validado puede ser principal.');
        }
        foreach ($this->owned($subject)->where('is_primary', true)->where('record_uuid', '<>', $reference)->lockForUpdate()->get() as $row) {
            $payload = $this->payload($row, $subject);
            $payload['audit'][] = $this->event('primary_removed', $actor);
            $this->update($row, $subject, $actor, $payload, ['is_primary' => false]);
        }
        if (! $target->is_primary) {
            $payload = $this->payload($target, $subject);
            $payload['audit'][] = $this->event('primary_selected', $actor);
            $this->update($target, $subject, $actor, $payload, ['is_primary' => true]);
        }
    }

    private function write(IdentifierSubject $subject, User $actor, callable $operation): mixed
    {
        if ($this->db->getDriverName() !== 'mysql') {
            throw new RuntimeException('El almacenamiento requiere MySQL y sus restricciones revisadas.');
        }
        try {
            return $this->db->transaction(function () use ($subject, $actor, $operation) {
                $root = $subject->customerId === null ? 'users' : 'customers';
                $id = $subject->customerId ?? $subject->ownerId;
                if (! $this->db->table($root)->where('id', $id)->lockForUpdate()->first()) {
                    throw new InvalidArgumentException('No existe el contexto de identificación.');
                }
                $this->authorize($subject, $actor, true);

                return $operation();
            }, 3);
        } catch (QueryException) {
            throw new RuntimeException('No se pudo guardar el identificador de forma segura. Revisa duplicados o restricciones.');
        }
    }

    private function authorize(IdentifierSubject $subject, User $actor, bool $write): void
    {
        $owner = $this->db->table($subject->ownersTable())->where('id', $subject->ownerId)->first();
        if (! $owner || ($subject->kind !== 'customer' && ($owner->customer_id === null ? null : (int) $owner->customer_id) !== $subject->customerId)
            || (! empty($owner->deleted_at))) {
            throw new InvalidArgumentException('El titular no corresponde al contexto.');
        }
        $this->authorization->authorize($actor, $subject, $owner, $write);
    }

    private function assertWriterVersion(IdentifierSubject $subject): void
    {
        $rows = $subject->kind === 'patient' ? $this->scoped($subject) : $this->owned($subject);
        if ($rows->where('record_state', 'validated')->where('lookup_key_version', '<>', $this->crypto->lookupVersion)->exists()) {
            throw new RuntimeException('El contexto requiere completar la rotación de claves antes de nuevas escrituras.');
        }
    }

    private function scoped(IdentifierSubject $subject)
    {
        return $this->db->table($subject->table())->where('scope_key', $subject->scopeKey());
    }

    private function owned(IdentifierSubject $subject)
    {
        return $this->scoped($subject)->where($subject->ownerColumn(), $subject->ownerId);
    }

    private function matching(IdentifierSubject $subject, array $hashes, bool $claims)
    {
        $query = $claims && $subject->kind === 'patient' ? $this->scoped($subject) : $this->owned($subject);
        $column = $claims ? 'claim_hash' : 'lookup_hash';

        return $query->where(function ($q) use ($hashes, $column) {
            foreach ($hashes as $version => $hash) {
                $q->orWhere(fn ($q) => $q->where('lookup_key_version', $version)->where($column, $hash));
            }
        });
    }

    private function binding(object $row, IdentifierSubject $subject): array
    {
        return ['record_uuid' => $row->record_uuid, 'kind' => $subject->kind, 'owner' => $subject->ownerId, 'scope' => $subject->scopeKey(),
            'country' => $row->issuing_country_code, 'type' => $row->document_type, 'namespace' => $row->identifier_namespace,
            'holder' => $row->holder_kind, 'normalization_version' => $row->normalization_version, 'record_state' => $row->record_state, 'validation_status' => $row->validation_status,
            'revision' => (int) $row->revision, 'primary' => (bool) $row->is_primary,
            'retired_at' => isset($row->retired_at) ? ($row->retired_at instanceof \DateTimeInterface ? $row->retired_at->format('Y-m-d H:i:s') : (string) $row->retired_at) : null,
            'retired_by' => isset($row->retired_by) ? (int) $row->retired_by : null,
            'lookup_key_version' => $row->lookup_key_version, 'lookup_hash' => $row->lookup_hash, 'claim_hash' => $row->claim_hash];
    }

    private function payload(object $row, IdentifierSubject $subject): array
    {
        if ((json_decode($row->encrypted_payload, true)['key_version'] ?? null) !== $row->encryption_key_version) {
            throw new RuntimeException('Metadatos de cifrado no válidos.');
        }

        return $this->crypto->decrypt($row->encrypted_payload, $this->binding($row, $subject));
    }

    private function update(object $row, IdentifierSubject $subject, ?User $actor, array $payload, array $changes): void
    {
        $next = (object) array_merge((array) $row, $changes, ['revision' => $row->revision + 1]);
        if ($subject->kind==='patient') app(\App\Services\MiSalud\PortalPatientLinkService::class)->request('patient',$subject->ownerId);
        $affected = $this->owned($subject)->where('id', $row->id)->where('revision', $row->revision)->update($changes + [
            'encrypted_payload' => $this->crypto->encrypt($payload, $this->binding($next, $subject)),
            'encryption_key_version' => $this->crypto->encryptionVersion, 'last_changed_by' => $actor?->id,
            'revision' => $row->revision + 1, 'updated_at' => now()]);
        if ($affected !== 1) {
            throw new RuntimeException('El identificador cambió durante la operación.');
        }
    }

    private function event(string $action, ?User $actor): array
    {
        return ['action' => $action, 'actor_id' => $actor?->id, 'at' => now()->utc()->toIso8601String()];
    }
}
