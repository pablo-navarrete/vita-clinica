<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\RegularMedication;
use Illuminate\Http\Request;

class RegularMedicationController extends Controller
{
    public function store(Request $request)
    {

 
        $regularMedications = [];

        // Recuperar existentes
        if ($request->has('existing_regular_medications')) {

            $existing = RegularMedication::whereIn(
                'id',
                $request->existing_regular_medications
            )->get();

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
        RegularMedication::where('customer_id', $request->customer_id)
            ->where('medical_record_id', $request->medical_record_id)
            ->delete();

        // Guardar todas
        foreach ($regularMedications as $regular) {
            RegularMedication::create([
                'customer_id'       => $request->customer_id,
                'medical_record_id' => $request->medical_record_id,
                'name'              => $regular['name'],
            ]);
        }

        return redirect()->back()->with(
            'success',
            'Medicamentos habituales actualizados correctamente.'
        );
    }
}
