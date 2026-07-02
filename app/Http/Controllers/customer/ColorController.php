<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;

use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{

    public function update(Request $request)
    {
        $request->validate([
            'theme' => 'required|string'
        ]);

        $customer = app('customer');

        $customer->update([
            'color' => $request->theme
        ]);

        return back(); 
    }

}
