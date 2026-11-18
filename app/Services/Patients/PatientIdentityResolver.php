<?php

namespace App\Services\Patients;

use App\Models\Patient;
use App\Services\Identification\DocumentIdentifier;
use App\Services\Identification\Storage\{IdentifierCrypto, IdentifierSubject};
use Illuminate\Support\Facades\DB;

/** Internal resolution only. Public controllers must never serialize this result. */
final class PatientIdentityResolver
{
    public function crypto(): IdentifierCrypto { return new IdentifierCrypto(config('identification')); }

    public function hashes(int $customer, DocumentIdentifier $document): array
    {
        return $this->crypto()->hashes((new IdentifierSubject('patient', 1, $customer))->lookupContext($document));
    }

    public function resolve(int $customer, DocumentIdentifier $document, ?int $ignorePatient = null): array
    {
        $hashes = $this->hashes($customer, $document);
        $rows = DB::table('patient_identifiers')->where('customer_id', $customer)->when($ignorePatient, fn ($q) => $q->where('patient_id', '<>', $ignorePatient))->where(function ($query) use ($hashes) {
            foreach ($hashes as $version => $hash) $query->orWhere(fn ($q) => $q->where('lookup_key_version', $version)
                ->where(fn ($q) => $q->where('claim_hash', $hash)->orWhere('lookup_hash', $hash)));
        })->get();
        $legacy = Patient::where('customer_id', $customer)->when($ignorePatient, fn ($q) => $q->where('id', '<>', $ignorePatient));
        if ($document->definition->namespace === 'CL:RUT') {
            $numbers = [$document->number->normalized];
            // An incomplete historical body is a review candidate, never an automatic equivalence.
            $numbers[] = substr($document->number->normalized, 0, -1);
            $legacy->where(function ($q) use ($numbers) {
                foreach (array_unique($numbers) as $number) $q->orWhereRaw("SHA2(UPPER(REPLACE(REPLACE(REPLACE(rut,'.',''),'-',''),' ','')),256)=?", [hash('sha256', $number)]);
            });
        } else {
            $legacy->where(function ($q) use ($document) {
                foreach (array_unique([$document->original, $document->number->normalized, $document->number->display]) as $number)
                    $q->orWhereRaw('SHA2(CAST(rut AS BINARY),256)=?', [hash('sha256', $number)]);
            });
        }
        $legacyIds = $legacy->pluck('id')->map(fn ($id) => (int) $id)->all();
        $owners = $rows->pluck('patient_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (count(array_unique(array_merge($legacyIds, $owners))) > 1) return ['state' => 'review'];
        if ($rows->contains(fn ($row) => $row->record_state !== 'validated' || $row->retired_at !== null)) return ['state' => 'reserved'];
        if (count($owners) === 1) {
            $patient = Patient::where('customer_id', $customer)->find($owners[0]);
            return $patient && (int) $patient->status === 1 ? ['state' => 'existing', 'patient' => $patient] : ['state' => 'review'];
        }
        return $legacyIds ? ['state' => 'review'] : ['state' => 'new'];
    }
}
