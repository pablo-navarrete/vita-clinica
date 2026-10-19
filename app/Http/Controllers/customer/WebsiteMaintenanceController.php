<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\WebsiteMaintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebsiteMaintenanceController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        WebsiteMaintenance::updateOrCreate(
            ['customer_id' => app('customer')->id],
            $data
        );

        return back()->with('success', $data['enabled']
            ? 'El modo mantenimiento está activado.'
            : 'El modo mantenimiento está desactivado.');
    }
}
