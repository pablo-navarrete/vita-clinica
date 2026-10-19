<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\RegularMedication;
use App\Models\MedicalRecord;
use App\Support\PatientAuditLogger;
use Illuminate\Http\Request;

class RegularMedicationController extends Controller
{
    public function store(Request $request)
    {
        $customerId = app('customer')->id;
        $medicalRecord = MedicalRecord::where('customer_id', $customerId)
            ->findOrFail($request->input('medical_record_id'));
        $before = $medicalRecord->regularMedications()->pluck('name')->all();
 
        $regularMedications = [];

        // Recuperar existentes
        if ($request->has('existing_regular_medications')) {

            $existing = $medicalRecord->regularMedications()->whereIn('id', $request->existing_regular_medications)->get();

            foreach ($existing as $regular) {
                $regularMedications[] = [
                    'name' => $regular->name,
                ];
            }
        }

        // Agregar nuevas
        $newRegularMedications = $request->input('new_regular_medications', []);

        foreach ($newRegularMedications as $name) {
            $regularMedications[] = [
                'name' => $name,
            ];
        }

        // Eliminar anteriores
        $medicalRecord->regularMedications()->delete();

        // Guardar todas
        foreach ($regularMedications as $regular) {
            RegularMedication::create([
                'customer_id'       => $customerId,
                'medical_record_id' => $medicalRecord->id,
                'name'              => $regular['name'],
            ]);
        }

        $after = $medicalRecord->regularMedications()->pluck('name')->all();
        PatientAuditLogger::recordMedicalRecord($medicalRecord, 'Medicamentos habituales', $before, $after);

        return redirect()->back()->with(
            'success',
            'Medicamentos habituales actualizados correctamente.'
        );
    }
}
