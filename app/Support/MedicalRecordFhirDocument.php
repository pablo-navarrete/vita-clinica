<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MedicalRecordFhirDocument
{
    public static function make(Patient $patient, Customer $customer): array
    {
        $record = $patient->medicalRecord;
        $entries = [];
        $register = static function (array $resource) use (&$entries): string {
            $id = (string) Str::uuid();
            $resource['id'] = $id;
            $fullUrl = 'urn:uuid:' . $id;
            $entries[] = ['fullUrl' => $fullUrl, 'resource' => $resource];

            return $fullUrl;
        };

        $patientRef = $register(array_filter([
            'resourceType' => 'Patient',
            'identifier' => [[
                'use' => 'official',
                'system' => 'https://vita-clinica.cl/fhir/NamingSystem/rut',
                'value' => $patient->rut,
            ]],
            'active' => (bool) $patient->status,
            'name' => [['text' => $patient->name]],
            'telecom' => array_values(array_filter([
                $patient->phone ? ['system' => 'phone', 'value' => $patient->phone] : null,
                $patient->email ? ['system' => 'email', 'value' => $patient->email] : null,
            ])),
            'gender' => self::fhirGender($patient->gender),
            'birthDate' => $patient->birth_date?->format('Y-m-d'),
            'address' => $patient->address ? [['text' => $patient->address, 'country' => 'CL']] : null,
        ], static fn ($value) => $value !== null));

        $clinicRef = $register([
            'resourceType' => 'Organization',
            'active' => (bool) $customer->status,
            'name' => $customer->legal_name ?: $customer->name,
            'telecom' => array_values(array_filter([
                $customer->phone ? ['system' => 'phone', 'value' => $customer->phone] : null,
                $customer->email ? ['system' => 'email', 'value' => $customer->email] : null,
            ])),
            'address' => $customer->address ? [['text' => $customer->address, 'country' => 'CL']] : null,
        ]);

        $sections = [];
        $addSection = static function (string $title, string $loinc, string $display, array $resources, array $narrative = []) use (&$sections): void {
            $section = [
                'title' => $title,
                'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => $loinc, 'display' => $display]]],
                'text' => [
                    'status' => 'generated',
                    'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . implode('', array_map(
                        static fn ($line) => '<p>' . e($line) . '</p>',
                        $narrative ?: ['Sin registros.']
                    )) . '</div>',
                ],
            ];
            if ($resources) {
                $section['entry'] = array_map(static fn ($reference) => ['reference' => $reference], $resources);
            }
            $sections[] = $section;
        };

        $addSection('Sede de ingreso del paciente', '34117-2', 'History and physical note', [], [
            'Sede de ingreso del paciente: ' . ($patient->clinicBranch?->name ?? 'Sin sede registrada'),
        ]);

        $allergyRefs = [];
        foreach ($record?->allergies ?? [] as $allergy) {
            $allergyRefs[] = $register(array_filter([
                'resourceType' => 'AllergyIntolerance',
                'clinicalStatus' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical', 'code' => 'active']]],
                'verificationStatus' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-verification', 'code' => 'confirmed']]],
                'code' => ['text' => $allergy->allergen],
                'patient' => ['reference' => $patientRef],
                'recordedDate' => $allergy->created_at?->toIso8601String(),
                'reaction' => $allergy->reaction ? [['manifestation' => [['text' => $allergy->reaction]]]] : null,
            ], static fn ($value) => $value !== null));
        }
        $addSection('Alergias e intolerancias', '48765-2', 'Allergies and adverse reactions', $allergyRefs,
            ($record?->allergies ?? collect())->map(fn ($item) => trim($item->allergen . ($item->reaction ? ' — ' . $item->reaction : '')))->all());

        $conditionRefs = [];
        foreach ($record?->chronicDiseases ?? [] as $disease) {
            $conditionRefs[] = $register([
                'resourceType' => 'Condition',
                'clinicalStatus' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical', 'code' => 'active']]],
                'verificationStatus' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status', 'code' => 'confirmed']]],
                'code' => ['text' => $disease->name],
                'subject' => ['reference' => $patientRef],
                'recordedDate' => $disease->created_at?->toIso8601String(),
            ]);
        }
        $addSection('Enfermedades crónicas', '11450-4', 'Problem list - Reported', $conditionRefs,
            ($record?->chronicDiseases ?? collect())->pluck('name')->all());

        $medicationRefs = [];
        foreach ($record?->regularMedications ?? [] as $medication) {
            $medicationRefs[] = $register(array_filter([
                'resourceType' => 'MedicationStatement',
                'status' => 'unknown',
                'medicationCodeableConcept' => ['text' => $medication->name],
                'subject' => ['reference' => $patientRef],
                'dateAsserted' => $medication->created_at?->toIso8601String(),
            ], static fn ($value) => $value !== null));
        }
        $addSection('Medicamentos habituales', '10160-0', 'History of Medication use Narrative', $medicationRefs,
            ($record?->regularMedications ?? collect())->pluck('name')->all());

        $surgeryRefs = [];
        foreach ($record?->previousSurgeries ?? [] as $surgery) {
            $surgeryRefs[] = $register(array_filter([
                'resourceType' => 'Procedure',
                'status' => 'completed',
                'code' => ['text' => $surgery->name],
                'subject' => ['reference' => $patientRef],
            ], static fn ($value) => $value !== null));
        }
        $addSection('Cirugías previas registradas', '29554-3', 'Procedure narrative', $surgeryRefs,
            ($record?->previousSurgeries ?? collect())->pluck('name')->all());

        $locations = [];
        $practitioners = [];
        $encounterRefs = [];
        $clinicalImpressionRefs = [];
        $encounterNarrative = [];
        foreach ($patient->encounters as $encounter) {
            $professionalRef = null;
            if ($encounter->professional) {
                $professionalId = (int) $encounter->professional->id;
                if (!isset($practitioners[$professionalId])) {
                    $practitioners[$professionalId] = $register(array_filter([
                        'resourceType' => 'Practitioner',
                        'active' => (bool) $encounter->professional->status,
                        'name' => [['text' => $encounter->professional->name]],
                        'telecom' => $encounter->professional->email ? [['system' => 'email', 'value' => $encounter->professional->email]] : null,
                    ], static fn ($value) => $value !== null));
                }
                $professionalRef = $practitioners[$professionalId];
            }

            $period = array_filter([
                'start' => $encounter->started_at?->toIso8601String(),
                'end' => $encounter->ended_at?->toIso8601String(),
            ], static fn ($value) => $value !== null);
            $status = match ((int) $encounter->status) {
                1 => 'in-progress', 2 => 'finished', 3 => 'cancelled', default => 'unknown',
            };
            $encounterResource = [
                'resourceType' => 'Encounter',
                'status' => $status,
                'class' => ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => 'AMB', 'display' => 'ambulatory'],
                'subject' => ['reference' => $patientRef],
            ];
            if ($period) {
                $encounterResource['period'] = $period;
            }
            if ($professionalRef) {
                $encounterResource['participant'] = [['individual' => ['reference' => $professionalRef]]];
            }
            if ($encounter->chief_complaint) {
                $encounterResource['reasonCode'] = [['text' => $encounter->chief_complaint]];
            }
            $branch = $encounter->clinicBranch ?? $encounter->appointment?->clinicBranch;
            if ($branch) {
                if (!isset($locations[$branch->id])) {
                    $locations[$branch->id] = $register(array_filter([
                        'resourceType' => 'Location',
                        'name' => $branch->name,
                        'address' => $branch->address ? ['text' => $branch->address, 'country' => 'CL'] : null,
                        'managingOrganization' => ['reference' => $clinicRef],
                    ], static fn ($value) => $value !== null));
                }
                $encounterResource['location'] = [['location' => ['reference' => $locations[$branch->id]]]];
            }
            $encounterNarrative[] = 'Atención #' . $encounter->id . ' — Sede de atención: ' . ($branch?->name ?? 'Sin sede registrada');
            $encounterRef = $register($encounterResource);
            $encounterRefs[] = $encounterRef;

            $notes = $encounter->treatment_plan ? [['text' => $encounter->treatment_plan]] : [];
            foreach ($encounter->observations as $observation) {
                $notes[] = array_filter([
                    'authorString' => $observation->author?->name ?? 'Usuario eliminado',
                    'time' => $observation->created_at?->toIso8601String(),
                    'text' => $observation->observation
                        . ($observation->clinicBranch ? "\nSucursal: " . $observation->clinicBranch->name : ''),
                ], static fn ($value) => $value !== null);
            }

            $clinicalImpression = array_filter([
                'resourceType' => 'ClinicalImpression',
                'status' => 'completed',
                'subject' => ['reference' => $patientRef],
                'encounter' => ['reference' => $encounterRef],
                'date' => $encounter->started_at?->toIso8601String(),
                'assessor' => $professionalRef ? ['reference' => $professionalRef] : null,
                'summary' => $encounter->clinical_assessment,
                'note' => $notes ?: null,
            ], static fn ($value) => $value !== null);
            if ($encounter->clinical_assessment || $notes) {
                $clinicalImpressionRefs[] = $register($clinicalImpression);
            }

            foreach ($encounter->observations as $observation) {
                $encounterNarrative[] = 'Observación adicional de la atención #' . $encounter->id . ': '
                    . ($observation->created_at?->timezone('America/Santiago')->format('d/m/Y H:i') ?? 'Fecha no registrada')
                    . ' — ' . ($observation->author?->name ?? 'Usuario eliminado')
                    . ($observation->clinicBranch ? ' — Sucursal: ' . $observation->clinicBranch->name : '')
                    . ' — ' . $observation->observation;
            }

            $date = $encounter->started_at?->format('d/m/Y H:i') ?? 'Fecha no registrada';
            $encounterNarrative[] = trim($date . ' — ' . ($encounter->professional?->name ?? 'Profesional no registrado')
                . ($encounter->chief_complaint ? ' — Motivo: ' . $encounter->chief_complaint : '')
                . ($encounter->clinical_assessment ? ' — Evaluación: ' . $encounter->clinical_assessment : '')
                . ($encounter->treatment_plan ? ' — Plan: ' . $encounter->treatment_plan : ''));
        }
        $addSection('Atenciones clínicas', '46240-8', 'History of encounters', $encounterRefs, $encounterNarrative);
        $addSection('Evaluaciones clínicas y planes', '51848-0', 'Evaluation note', $clinicalImpressionRefs);

        $addSection('Documentos registrados por sede', '34117-2', 'History and physical note', [],
            $patient->documents->map(fn ($document) => $document->original_name
                . ' — Sede: ' . ($document->clinicBranch?->name ?? 'Sin sede registrada')
                . ' — ' . ($document->created_at?->timezone('America/Santiago')->format('d/m/Y H:i') ?? 'Fecha no registrada')
                . ' — ' . ($document->uploader?->name ?? 'Usuario eliminado'))->all());
        $addSection('Movimientos de la ficha por sede', '34117-2', 'History and physical note', [],
            $patient->auditLogs->map(fn ($log) => $log->section . ' — ' . $log->action
                . ' — Sede: ' . ($log->clinicBranch?->name ?? 'Sin sede registrada')
                . ' — ' . ($log->created_at?->timezone('America/Santiago')->format('d/m/Y H:i') ?? 'Fecha no registrada')
                . ' — ' . ($log->user?->name ?? 'Usuario eliminado'))->all());

        if ($patient->insurance) {
            $payerRef = $register([
                'resourceType' => 'Organization',
                'active' => true,
                'name' => $patient->insurance->name,
                'identifier' => [['system' => 'https://vita-clinica.cl/fhir/NamingSystem/insurance', 'value' => (string) $patient->insurance->id]],
            ]);
            $coverageRef = $register([
                'resourceType' => 'Coverage',
                'status' => 'active',
                'beneficiary' => ['reference' => $patientRef],
                'payor' => [['reference' => $payerRef]],
                'type' => ['text' => $patient->insurance->name],
            ]);
            $addSection('Previsión', '48768-6', 'Payment sources', [$coverageRef], [$patient->insurance->name]);
        } else {
            $addSection('Previsión', '48768-6', 'Payment sources', [], ['Sin previsión registrada.']);
        }

        $composition = [
            'resourceType' => 'Composition',
            'status' => 'final',
            'type' => ['coding' => [['system' => 'http://loinc.org', 'code' => '34117-2', 'display' => 'History and physical note']]],
            'subject' => ['reference' => $patientRef],
            'date' => Carbon::now()->toIso8601String(),
            'author' => [['reference' => $clinicRef]],
            'title' => 'Ficha clínica de ' . $patient->name,
            'custodian' => ['reference' => $clinicRef],
            'section' => $sections,
        ];
        $compositionId = (string) Str::uuid();
        array_unshift($entries, ['fullUrl' => 'urn:uuid:' . $compositionId, 'resource' => ['id' => $compositionId, ...$composition]]);

        return [
            'resourceType' => 'Bundle',
            'id' => (string) Str::uuid(),
            'meta' => ['profile' => ['http://hl7.org/fhir/StructureDefinition/Bundle']],
            'identifier' => ['system' => 'https://vita-clinica.cl/fhir/NamingSystem/clinical-document', 'value' => (string) Str::uuid()],
            'type' => 'document',
            'timestamp' => Carbon::now()->toIso8601String(),
            'entry' => $entries,
        ];
    }

    private static function fhirGender(?string $gender): string
    {
        return match (mb_strtolower(trim((string) $gender))) {
            'male', 'masculino', 'hombre', 'm' => 'male',
            'female', 'femenino', 'mujer', 'f' => 'female',
            'other', 'otro', 'no binario', 'no-binario' => 'other',
            default => 'unknown',
        };
    }
}
