<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\ChronicDiseases;
use App\Models\MedicalRecord;
use App\Support\PatientAuditLogger;
use Illuminate\Http\Request;

class ChronicDiseasesController extends Controller
{
    
public function store(Request $request)
{
    $customerId = app('customer')->id;
    $medicalRecord = MedicalRecord::where('customer_id', $customerId)
        ->findOrFail($request->input('medical_record_id'));
    $before = $medicalRecord->chronicDiseases()->pluck('name')->all();
    $chronicDiseases = [];

    // Recuperar existentes
    if ($request->has('existing_chronic_diseases')) {

        $existing = $medicalRecord->chronicDiseases()->whereIn('id', $request->existing_chronic_diseases)->get();

        foreach ($existing as $disease) {
            $chronicDiseases[] = [
                'name' => $disease->name,
            ];
        }
    }

    // Agregar nuevas
    $newChronicDiseases = $request->input('new_chronic_diseases', []);

    foreach ($newChronicDiseases as $name) {
        $chronicDiseases[] = [
            'name' => $name,
        ];
    }

    // Eliminar anteriores
    $medicalRecord->chronicDiseases()->delete();

    // Guardar todas
    foreach ($chronicDiseases as $disease) {
        ChronicDiseases::create([
            'customer_id'       => $customerId,
            'medical_record_id' => $medicalRecord->id,
            'name'              => $disease['name'],
        ]);
    }

    $after = $medicalRecord->chronicDiseases()->pluck('name')->all();
    PatientAuditLogger::recordMedicalRecord($medicalRecord, 'Enfermedades crónicas', $before, $after);

    return redirect()->back()->with(
        'success',
        'Enfermedades crónicas actualizadas correctamente.'
    );
}

}
