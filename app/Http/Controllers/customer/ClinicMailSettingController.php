<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\WebsiteTopbar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicMailSettingController extends Controller
{
    public function edit(): View
    {
        $topbar = WebsiteTopbar::query()->firstOrNew(['customer_id' => (int) app('customer')->id]);

        return view('customer.mail-settings.edit', [
            'replyEmail' => $topbar->reply_email,
            'whatsappNumber' => $topbar->reply_whatsapp,
            'delivery' => $topbar->telemedicine_delivery ?: 'email',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customerId = (int) app('customer')->id;
        $validated = $request->validate([
            'reply_to_email' => ['nullable', 'email', 'max:160'],
            'reply_whatsapp' => ['nullable', 'string', 'max:40'],
            'telemedicine_delivery' => ['required', 'in:email,whatsapp,both'],
        ]);

        WebsiteTopbar::updateOrCreate(
            ['customer_id' => $customerId],
            [
                'reply_email' => $validated['reply_to_email'] ?? null,
                'reply_whatsapp' => $validated['reply_whatsapp'] ?? null,
                'telemedicine_delivery' => $validated['telemedicine_delivery'],
            ]
        );

        return redirect()->route('customers.mail-settings.edit', ['customer' => $request->route('customer')])
            ->with('success', 'Configuracion de notificaciones actualizada.');
    }
}
