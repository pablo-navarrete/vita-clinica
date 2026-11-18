<?php

namespace App\Services\Identification;

use App\Models\Patient;
use App\Models\User;
use App\Services\Identification\Storage\IdentifierRepositoryFactory;
use App\Services\Identification\Storage\IdentifierSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PatientIdentification
{

    public function subject(Patient $patient): IdentifierSubject
    {
        return new IdentifierSubject('patient', (int) $patient->id, (int) $patient->customer_id);
    }

    public function parse(Request $request, ?Patient $patient): ?DocumentIdentifier
    {
        if ($patient && ! $request->boolean('identification_enabled')) {
            if ($request->exists('rut') && (string) $request->input('rut') !== (string) $patient->rut) {
                throw ValidationException::withMessages(['identification_number' => 'Activa la actualización de identificación para cambiar el documento.']);
            }
            return null;
        }
        $data = $request->validate(['identification_country' => ['sometimes', 'required', 'string', 'size:2'],
            'identification_type' => ['sometimes', 'required', 'string'], 'identification_number' => ['sometimes', 'required', 'string', 'max:128'],
            'identification_holder' => ['sometimes', 'in:natural_person']]);
        try {
            return app(IdentificationService::class)->identify($data['identification_country'] ?? 'CL',
                $data['identification_type'] ?? 'national_id', $data['identification_number'] ?? $request->input('rut'), 'natural_person');
        } catch (InvalidIdentification $error) {
            throw ValidationException::withMessages(['identification_number' => $error->getMessage()]);
        }
    }

    /** Caller holds the customer lock, including for duplicate checks. */
    public function assertUnique(DocumentIdentifier $doc, User $actor, ?Patient $patient): void
    {
        $result = app(\App\Services\Patients\PatientIdentityResolver::class)->resolve((int) app('customer')->id, $doc, $patient?->id);
        if ($result['state'] !== 'new') $this->conflict();
    }

    public function save(Patient $patient, User $actor, ?DocumentIdentifier $doc, Request $request, bool $creating): void
    {
        if (! $doc) return;
        if (DB::transactionLevel() === 0) throw new \LogicException('Patient identity requires the parent transaction.');
        $factory = app(IdentifierRepositoryFactory::class);
        $repo = $creating ? $factory->forNewPatient($patient) : $factory->make();
        if (! $creating) {
            $primary = $repo->primary($this->subject($patient), $actor);
            if (! $request->exists('identification_expected_primary') || (string) $request->input('identification_expected_primary') !== ($primary['reference'] ?? '')) {
                throw ValidationException::withMessages(['identification_number' => 'La identificación cambió. Recarga antes de guardar.']);
            }
            $references = $repo->findExact($this->subject($patient), $actor, $doc->definition->country, $doc->definition->type->value, $doc->original);
        } else $references = [];
        try {
            if ($references) $repo->setPrimary($this->subject($patient), $actor, $references[0]);
            else $repo->create($this->subject($patient), $actor, $doc->definition->country, $doc->definition->type->value, $doc->original, 'natural_person', true);
        } catch (\InvalidArgumentException) { $this->conflict(); }
        \App\Support\PatientAuditLogger::record($patient, 'updated', 'Identificación documental', [], [
            'país emisor' => $doc->definition->country, 'tipo' => $doc->definition->type->value, 'identidad verificada' => false]);
    }

    public function form(Patient $patient, User $actor): array
    {
        $primary = app(IdentifierRepositoryFactory::class)->make()->primary($this->subject($patient), $actor);
        return ['identification_enabled' => false, 'identification_expected_primary' => $primary['reference'] ?? '',
            'identification_country' => $primary['country'] ?? '', 'identification_type' => $primary['type'] ?? 'national_id',
            'identification_holder' => 'natural_person', 'identification_number' => $primary['number'] ?? '',
            'identification_display' => $primary['display'] ?? '', 'identification_legacy' => $primary ? '' : (string) $patient->rut,
            'identity_verified' => false];
    }

    public function snapshot(Patient $patient, User $actor, bool $requireActive = true): array
    {
        $doc = app(IdentifierRepositoryFactory::class)->forClinicalPatientRead()->primarySnapshot($this->subject($patient), $actor);
        $missing = ! $doc && DB::table('patient_identifiers')->where('customer_id', $patient->customer_id)->where('patient_id', $patient->id)->exists();
        abort_if($missing && $requireActive, 409, 'El paciente necesita un documento principal activo.');
        if ($missing) $doc = ['reference' => null, 'country' => null, 'type' => 'unavailable', 'namespace' => 'unavailable', 'normalized' => null, 'display' => null, 'normalization_version' => null, 'validation_status' => 'missing_primary', 'identity_verified' => false];
        return ['id' => $patient->id, 'name' => $patient->name, 'document' => $doc ?? [
            'reference' => null, 'country' => null, 'type' => 'legacy_rut', 'namespace' => 'legacy:patients.rut',
            'normalized' => null, 'display' => $patient->rut, 'normalization_version' => null, 'validation_status' => 'legacy_unverified', 'identity_verified' => false]];
    }

    public function masked(Patient $patient): string
    {
        if (! auth()->user()) return 'Identificación reservada';
        try { return app(IdentifierRepositoryFactory::class)->make()->patientMasked($patient, auth()->user()); }
        catch (\Illuminate\Auth\Access\AuthorizationException) { return 'Identificación reservada'; }
    }

    /** Full number only in the patient module, with the existing full-identity authorization. */
    public function patientTableNumber(Patient $patient): string
    {
        try {
            $primary = app(IdentifierRepositoryFactory::class)->make()->primary($this->subject($patient), auth()->user());
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return $this->masked($patient);
        }
        if ($primary) return $primary['display'];
        if (! filled($patient->rut)) return 'Sin documento';
        try { return app(ChileanRutRule::class)->validate($patient->rut)->display; }
        catch (InvalidIdentification) { return (string) $patient->rut; }
    }

    /** Uses clinical-record authorization, without granting access through agenda/chat permissions. */
    public function clinicalNumber(Patient $patient): string
    {
        $identity = $this->snapshot($patient, auth()->user(), false);
        $document = $identity['document'];
        $number = $document['display'] ?? null;
        if (! filled($number)) return 'Sin documento';
        if ($document['type'] === 'legacy_rut') {
            try { return app(ChileanRutRule::class)->validate($number)->display; }
            catch (InvalidIdentification) {}
        }
        return (string) $number;
    }

    /** Preserve partial name searches; exact document matches use an explicit issuing context. */
    public function filter($query, string $term, ?string $country = null, ?string $type = null): void
    {
        $ids = [];
        if ($country && $type && $term !== '') {
            try {
                $doc = app(IdentificationService::class)->identify($country, $type, $term, 'natural_person');
                $ids = app(IdentifierRepositoryFactory::class)->make()->patientMatches(auth()->user(), (int) app('customer')->id, $country, $type, $term);
                $legacy = Patient::where('customer_id', app('customer')->id)->whereNotExists(fn ($q) => $q
                    ->selectRaw('1')->from('patient_identifiers')
                    ->whereColumn('patient_identifiers.patient_id', 'patients.id')
                    ->whereColumn('patient_identifiers.customer_id', 'patients.customer_id'));
                if ($doc->definition->namespace === 'CL:RUT') $legacy->whereRaw("SHA2(UPPER(REPLACE(REPLACE(REPLACE(rut,'.',''),'-',''),' ','')),256) = ?", [hash('sha256', $doc->number->normalized)]);
                else $legacy->whereRaw('SHA2(CAST(rut AS BINARY),256) = ?', [hash('sha256', $doc->original)]);
                $ids = array_unique(array_merge($ids, $legacy->pluck('id')->all()));
            }
            catch (InvalidIdentification) {}
            $query->whereIn('id', $ids);
            return;
        }
        $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhereIn('id', $ids));
    }

    public function filterRequest($query, Request $request, string $term): void
    {
        $request->validate(['document_country' => ['nullable', 'string', 'size:2'], 'document_type' => ['nullable', 'string'],
            'document_number' => ['nullable', 'string', 'max:128']]);
        $this->filter($query, (string) $request->input('document_number', $term), $request->input('document_country'), $request->input('document_type'));
    }

    /** DataTables owns the only search input; an explicit scope selects foreign documents. */
    public function filterDataTable($query, Request $request): void
    {
        $term = (string) $request->input('search.value', '');
        if ($term === '') return;
        if ($request->filled('document_country') && $request->filled('document_type')) {
            $this->filter($query, $term, $request->input('document_country'), $request->input('document_type'));
            return;
        }
        // Validation governs new identities, not whether an existing historical row can be found.
        $query->where(function ($match) use ($term) {
            $match->where('name', 'like', '%'.$term.'%');
            $match->orWhere(function ($document) use ($term) {
                $this->filter($document, $term, 'CL', 'national_id');
            });
            $legacyNumber = strtoupper(str_replace(['.', '-', ' '], '', $term));
            if (preg_match('/\A[0-9]{1,9}K?\z/D', $legacyNumber)) {
                $match->orWhere(function ($legacy) use ($legacyNumber) {
                    $legacy->whereNotExists(fn ($identifiers) => $identifiers->selectRaw('1')->from('patient_identifiers')
                        ->whereColumn('patient_identifiers.patient_id', 'patients.id')
                        ->whereColumn('patient_identifiers.customer_id', 'patients.customer_id'))
                        ->whereRaw("SHA2(UPPER(REPLACE(REPLACE(REPLACE(rut,'.',''),'-',''),' ','')),256) = ?", [hash('sha256', $legacyNumber)]);
                });
            }
        });
    }

    public function decorate(Patient $patient): Patient
    {
        return $patient->makeHidden('rut')->setAttribute('identification_label', $this->masked($patient));
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['identification_number' => 'Existe una coincidencia documental o histórica en esta clínica. Requiere revisión autorizada; no se fusionaron pacientes.']);
    }
}
