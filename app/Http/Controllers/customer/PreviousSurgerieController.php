<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\PreviousSurgerie;
use Illuminate\Http\Request;

class PreviousSurgerieController extends Controller
{
     public function store(Request $request)
    {

 
        $previousSurgeries = [];

        // Recuperar existentes
        if ($request->has('existing_previous_surgeries')) {

            $existing = PreviousSurgerie::whereIn(
                'id',
                $request->existing_previous_surgeries
            )->get();

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
        PreviousSurgerie::where('customer_id', $request->customer_id)
            ->where('medical_record_id', $request->medical_record_id)
            ->delete();

        // Guardar todas
        foreach ($previousSurgeries as $regular) {
            PreviousSurgerie::create([
                'customer_id'       => $request->customer_id,
                'medical_record_id' => $request->medical_record_id,
                'name'              => $regular['name'],
            ]);
        }

        return redirect()->back()->with(
            'success',
            'Sirugías previas actualizadas correctamente.'
        );
    }
}
