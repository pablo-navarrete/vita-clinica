<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\ChronicDiseases;
use Illuminate\Http\Request;

class ChronicDiseasesController extends Controller
{
    
public function store(Request $request)
{
    $chronicDiseases = [];

    // Recuperar existentes
    if ($request->has('existing_chronic_diseases')) {

        $existing = ChronicDiseases::whereIn(
            'id',
            $request->existing_chronic_diseases
        )->get();

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
    ChronicDiseases::where('customer_id', $request->customer_id)
        ->where('medical_record_id', $request->medical_record_id)
        ->delete();

    // Guardar todas
    foreach ($chronicDiseases as $disease) {
        ChronicDiseases::create([
            'customer_id'       => $request->customer_id,
            'medical_record_id' => $request->medical_record_id,
            'name'              => $disease['name'],
        ]);
    }

    return redirect()->back()->with(
        'success',
        'Enfermedades crónicas actualizadas correctamente.'
    );
}

}
