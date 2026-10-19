<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\WebsiteFooter;
use App\Models\WebsiteSocialSetting;
use App\Models\WebsiteTopbar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteTopbarController extends Controller
{
    public function edit(): View
    {
        $customerId = (int) app('customer')->id;
        $footer = WebsiteFooter::query()->where('customer_id', $customerId)->first();
        $socialSettings = WebsiteSocialSetting::query()->where('customer_id', $customerId)->first();
        $topbar = WebsiteTopbar::firstOrNew(
            ['customer_id' => $customerId],
            [
                'enabled' => true,
                'show_social_links' => (bool) ($socialSettings?->show_in_topbar ?? false),
                'phone' => $footer?->phone,
                'email' => $footer?->email,
            ]
        );

        return view('customer.website-topbar.edit', compact('topbar'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'show_social_links' => ['required', 'boolean'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        WebsiteTopbar::updateOrCreate(
            ['customer_id' => app('customer')->id],
            $data
        );

        return redirect()->route('customers.website-topbar.edit', ['customer' => request()->route('customer')])
            ->with('success', 'Barra superior actualizada.');
    }
}
