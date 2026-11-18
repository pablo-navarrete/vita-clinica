<?php

namespace App\Services\Identification;

use App\Models\Customer;
use App\Models\User;
use App\Services\Identification\Storage\IdentifierRepositoryFactory;
use App\Services\Identification\Storage\IdentifierSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IdentificationForms
{
    public function subject(Customer|User $owner): IdentifierSubject
    {
        return new IdentifierSubject($owner instanceof Customer ? 'customer' : 'user', (int) $owner->id, $owner instanceof Customer ? (int) $owner->id : ($owner->customer_id === null ? null : (int) $owner->customer_id));
    }

    public function form(Customer|User $owner, User $actor): array
    {
        $primary = app(IdentifierRepositoryFactory::class)->make()->primary($this->subject($owner), $actor);

        return ['identification_enabled' => false, 'identification_expected_primary' => $primary['reference'] ?? '',
            'identification_country' => $primary['country'] ?? '', 'identification_type' => $primary['type'] ?? ($owner instanceof Customer ? 'tax_id' : 'national_id'),
            'identification_holder' => $primary['holder'] ?? ($owner instanceof Customer ? '' : 'natural_person'),
            'identification_number' => $primary['number'] ?? '', 'identification_display' => $primary['display'] ?? '',
            'identification_legacy' => $primary === null ? (string) $owner->rut : '', 'identity_verified' => false];
    }

    /** Null means preserve legacy verbatim, never infer its country or migrate it. */
    public function parse(Request $request, bool $customer, ?string $legacy, bool $creating, string $prefix = ''): ?DocumentIdentifier
    {
        if (! $creating && ! $request->boolean($prefix.'identification_enabled')) {
            // Old clients may still send rut. They may preserve it, not bypass the new writer.
            if ($request->exists($prefix.'rut') && (string) $request->input($prefix.'rut') !== (string) $legacy) {
                throw ValidationException::withMessages([$prefix.'identification_number' => 'Activa la actualización de identificación para cambiar el documento.']);
            }

            return null;
        }
        $country = $request->input($prefix.'identification_country', 'CL');
        $type = $request->input($prefix.'identification_type', $customer ? 'tax_id' : 'national_id');
        $holder = $customer ? $request->input($prefix.'identification_holder', 'legal_entity') : 'natural_person';
        $number = $request->input($prefix.'identification_number', $request->input($prefix.'rut'));
        if (! is_string($country) || ! is_string($type) || ! is_string($holder)) {
            throw ValidationException::withMessages([$prefix.'identification_number' => 'Documento no válido.']);
        }
        if (($customer && $type !== 'tax_id') || (! $customer && ! in_array($type, ['national_id', 'passport'], true))) {
            throw ValidationException::withMessages([$prefix.'identification_type' => 'Selecciona un tipo de documento correspondiente al titular.']);
        }
        if (! $customer && $request->input($prefix.'identification_holder', 'natural_person') !== 'natural_person') {
            throw ValidationException::withMessages([$prefix.'identification_holder' => 'Un usuario debe ser una persona natural.']);
        }
        try {
            return app(IdentificationService::class)->identify($country, $type, $number, $holder);
        } catch (InvalidIdentification $error) {
            throw ValidationException::withMessages([$prefix.'identification_number' => $error->getMessage()]);
        }
    }

    public function legacyValue(?DocumentIdentifier $document, ?string $existing = null): ?string
    {
        return $document?->definition->namespace === 'CL:RUT' ? $document->number->display : $existing;
    }

    public function assertCustomerSchema(?DocumentIdentifier $document, ?string $existing): void
    {
        if (! $document || $this->legacyValue($document, $existing) !== null) {
            return;
        }
        $column = DB::selectOne("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='rut'");
        if (! $column || $column->IS_NULLABLE !== 'YES') {
            throw ValidationException::withMessages(['identification_number' => 'Los documentos extranjeros de clientes requieren aplicar la migración compatible de la etapa 3. No se guardará un RUT ficticio.']);
        }
    }

    public function save(Customer|User $owner, User $actor, ?DocumentIdentifier $document, Request $request, string $prefix = '', bool $creating = false): void
    {
        if (! $document) {
            return;
        }
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Identification changes require the parent transaction.');
        }
        $subject = $this->subject($owner);
        // Same root lock as the repository; parent writes must be in the caller's transaction.
        DB::table($subject->customerId === null ? 'users' : 'customers')->where('id', $subject->customerId ?? $subject->ownerId)->lockForUpdate()->first();
        $repo = app(IdentifierRepositoryFactory::class)->make();
        $primary = $repo->primary($subject, $actor);
        if (! $creating && (! $request->exists($prefix.'identification_expected_primary') || (string) $request->input($prefix.'identification_expected_primary') !== ($primary['reference'] ?? ''))) {
            throw ValidationException::withMessages([$prefix.'identification_number' => 'La identificación cambió. Recarga el formulario antes de guardar.']);
        }
        try {
            $refs = $repo->findExact($subject, $actor, $document->definition->country, $document->definition->type->value, $document->original, $document->definition->holderKind->value);
            if ($refs) {
                $repo->setPrimary($subject, $actor, $refs[0]);
            } else {
                $repo->create($subject, $actor, $document->definition->country, $document->definition->type->value, $document->original, $document->definition->holderKind->value, true);
            }
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages([$prefix.'identification_number' => $error->getMessage()]);
        }
    }

}
