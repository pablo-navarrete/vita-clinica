<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\PreviousSurgerie;
use App\Models\MedicalRecord;
use App\Support\PatientAuditLogger;
use Illuminate\Http\Request;

class PreviousSurgerieController extends Controller
{
     public function store(Request $request)
    {
        $customerId = app('customer')->id;
        $medicalRecord = MedicalRecord::where('customer_id', $customerId)
            ->findOrFail($request->input('medical_record_id'));
        $before = $medicalRecord->previousSurgeries()->pluck('name')->all();
 
        $previousSurgeries = [];

        // Recuperar existentes
        if ($request->has('existing_previous_surgeries')) {

            $existing = $medicalRecord->previousSurgeries()->whereIn('id', $request->existing_previous_surgeries)->get();

            foreach ($existing as $regular) {
                $previousSurgeries[] = [
                    'name' => $regular->name,
                ];
            }
        }

        // Agregar nuevas
        $newPreviousSurgeries = $request->input('new_previous_surgeries', []);

        foreach ($newPreviousSurgeries as $name) {
            $previousSurgeries[] = [
                'name' => $name,
            ];
        }

        // Eliminar anteriores
        $medicalRecord->previousSurgeries()->delete();

        // Guardar todas
        foreach ($previousSurgeries as $regular) {
            PreviousSurgerie::create([
                'customer_id'       => $customerId,
                'medical_record_id' => $medicalRecord->id,
                'name'              => $regular['name'],
            ]);
        }

        $after = $medicalRecord->previousSurgeries()->pluck('name')->all();
        PatientAuditLogger::recordMedicalRecord($medicalRecord, 'Cirugías previas', $before, $after);

        return redirect()->back()->with(
            'success',
            'Sirugías previas actualizadas correctamente.'
        );
    }
}
