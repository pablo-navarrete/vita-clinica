<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\Color;
use App\Support\ClinicActivityLogger;
use Illuminate\Http\Request;

class ColorController extends Controller
{

    public function update(Request $request)
    {
        $request->validate([
            'theme' => 'required|string'
        ]);

        $customer = app('customer');
        $previousColor = $customer->color;

        $customer->update([
            'color' => $request->theme
        ]);

        if ($previousColor !== $request->theme) {
            ClinicActivityLogger::recordManual(
                (int) $customer->id,
                'Configuración del sistema',
                'updated',
                'tema',
                'Color institucional',
                'Cambió el color institucional de «' . $previousColor . '» a «' . $request->theme . '».',
                ['color']
            );
        }

        return back(); 
    }

}
