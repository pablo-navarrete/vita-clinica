<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Allergie;
use App\Models\MedicalRecord;
use App\Support\PatientAuditLogger;
use Illuminate\Http\Request;

class AllergieController extends Controller
{
   
    

    public function store(Request $request)
{
    $customerId = app('customer')->id;
    $medicalRecord = MedicalRecord::where('customer_id', $customerId)
        ->findOrFail($request->input('medical_record_id'));
    $before = $medicalRecord->allergies()->get(['allergen', 'reaction'])->toArray();
    $allergies = [];

    // Recuperar alergias existentes
    if ($request->has('existing_allergies')) {
        $existing = $medicalRecord->allergies()->whereIn('id', $request->existing_allergies)->get();

        foreach ($existing as $allergy) {
            $allergies[] = [
                'allergen' => $allergy->allergen,
                'reaction' => $allergy->reaction,
            ];
        }
    }

    // Agregar nuevas alergias
    $newAllergens = $request->input('new_allergens', []);
    $newReactions = $request->input('new_reactions', []);

    foreach ($newAllergens as $index => $allergen) {
        $allergies[] = [
            'allergen' => $allergen,
            'reaction' => $newReactions[$index] ?? null,
        ];
    }


    // Reemplazar todo
    $medicalRecord->allergies()->delete();


    foreach ($allergies as $allergy) {
        Allergie::create([
            'customer_id'       => $customerId,
            'medical_record_id' => $medicalRecord->id,
            'allergen'          => $allergy['allergen'],
            'reaction'          => $allergy['reaction'],
        ]);
    }

    $after = $medicalRecord->allergies()->get(['allergen', 'reaction'])->toArray();
    PatientAuditLogger::recordMedicalRecord($medicalRecord, 'Alergias', $before, $after);


    return redirect()->back()->with('success', 'Alergias actualizadas correctamente.');
}

   
}
