<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Allergie;
use Illuminate\Http\Request;

class AllergieController extends Controller
{
   
    

    public function store(Request $request)
{
    $allergies = [];

    // Recuperar alergias existentes
    if ($request->has('existing_allergies')) {
        $existing = Allergie::whereIn(
            'id',
            $request->existing_allergies
        )->get();

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
    Allergie::where('customer_id', $request->customer_id)
        ->where('medical_record_id', $request->medical_record_id)
        ->delete();


    foreach ($allergies as $allergy) {
        Allergie::create([
            'customer_id'       => $request->customer_id,
            'medical_record_id' => $request->medical_record_id,
            'allergen'          => $allergy['allergen'],
            'reaction'          => $allergy['reaction'],
        ]);
    }


    return redirect()->back()->with('success', 'Alergias actualizadas correctamente.');
}

   
}
