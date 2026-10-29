<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\ClinicBranch;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\PatientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientDocumentController extends Controller
{
    public function store(Request $request, $customer, $patientId, $encounterId)
    {
        $customerId = (int) app('customer')->id;
        $doctor = $request->user();
        abort_unless(
            $doctor
            && (int) $doctor->customer_id === $customerId
            && (int) $doctor->status === 1
            && $doctor->roles()->where('roles.customer_id', $customerId)->where('roles.name', 'Doctor')->exists(),
            403,
            'Solo un médico activo puede adjuntar documentos a una atención.'
        );

        $patient = Patient::query()->where('customer_id', $customerId)->findOrFail($patientId);
        $encounter = Encounter::query()
            ->where('customer_id', $customerId)
            ->where('patient_id', $patient->id)
            ->where('status', 1)
            ->with('appointment')
            ->findOrFail($encounterId);
        $responsibleProfessionalId = $encounter->professional_id ?? $encounter->appointment?->professional_id;
        abort_unless((int) $responsibleProfessionalId === (int) $doctor->id, 403, 'Solo el profesional responsable puede adjuntar documentos a esta atención.');

        $validated = $request->validate([
            'document' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'document.required' => 'Selecciona un documento para adjuntar.',
            'document.file' => 'El archivo seleccionado no es válido.',
            'document.max' => 'El documento no puede superar los 20 MB.',
            'document.mimes' => 'Formatos permitidos: PDF, JPG, PNG, DOC y DOCX.',
        ]);

        $file = $validated['document'];
        $sha256 = hash_file('sha256', $file->getRealPath());
        $existingDocument = PatientDocument::query()
            ->where('customer_id', $customerId)
            ->where('patient_id', $patient->id)
            ->where('sha256', $sha256)
            ->first();

        if ($existingDocument) {
            return back()->with('info', 'Este documento ya está guardado en la ficha clínica. No se creó una copia.');
        }

        $branchId = $encounter->clinic_branch_id ?? $encounter->appointment?->clinic_branch_id;
        if (!$branchId && (int) session('active_branch_customer_id') === $customerId) {
            $branchId = session('active_branch_id');
        }
        $branchId = $branchId
            ? ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->whereKey($branchId)->value('id')
            : null;

        $extension = strtolower($file->extension() ?: 'bin');
        $path = $file->storeAs(
            "medical-records/{$customerId}/{$patient->id}",
            Str::uuid() . '.' . $extension,
            'local'
        );

        try {
            PatientDocument::create([
                'customer_id' => $customerId,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'clinic_branch_id' => $branchId,
                'uploaded_by' => $doctor->id,
                'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'sha256' => $sha256,
                'description' => isset($validated['description']) ? trim($validated['description']) : null,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            if (PatientDocument::query()->where('patient_id', $patient->id)->where('sha256', $sha256)->exists()) {
                return back()->with('info', 'Este documento ya fue agregado a la ficha clínica. No se creó una copia.');
            }

            throw $exception;
        }

        return back()->with('success', 'Documento agregado a la ficha clínica y vinculado a la atención.');
    }

    public function download($customer, $patientId, $documentId)
    {
        $customerId = (int) app('customer')->id;
        $document = PatientDocument::query()
            ->where('customer_id', $customerId)
            ->where('patient_id', $patientId)
            ->findOrFail($documentId);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview($customer, $patientId, $documentId)
    {
        $customerId = (int) app('customer')->id;
        $document = PatientDocument::query()
            ->where('customer_id', $customerId)
            ->where('patient_id', $patientId)
            ->findOrFail($documentId);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        $extension = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
        $previewTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        if (!isset($previewTypes[$extension])) {
            return $this->download($customer, $patientId, $documentId);
        }

        $safeFilename = rawurlencode(basename($document->original_name));

        return response()->file(Storage::disk($document->disk)->path($document->path), [
            'Content-Type' => $previewTypes[$extension],
            'Content-Disposition' => "inline; filename*=UTF-8''{$safeFilename}",
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
