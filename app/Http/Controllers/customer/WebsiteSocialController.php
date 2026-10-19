<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSocialSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteSocialController extends Controller
{
    public function edit(): View
    {
        $settings = WebsiteSocialSetting::firstOrNew(
            ['customer_id' => app('customer')->id]
        );
        $networks = WebsiteSocialSetting::networks();

        return view('customer.website-socials.edit', compact('settings', 'networks'));
    }

    public function update(Request $request): RedirectResponse
    {
        $networks = array_keys(WebsiteSocialSetting::networks());
        $data = $request->validate([
            'social_links' => ['nullable', 'array', 'max:12'],
            'social_links.*.network' => ['required', 'string', 'distinct', 'in:' . implode(',', $networks)],
            'social_links.*.url' => ['required', 'url:http,https', 'max:500'],
            'show_in_topbar' => ['required', 'boolean'],
        ]);
        $data['social_links'] = array_values($data['social_links'] ?? []);

        WebsiteSocialSetting::updateOrCreate(
            ['customer_id' => app('customer')->id],
            $data
        );

        return redirect()->route('customers.website-socials.edit', ['customer' => request()->route('customer')])
            ->with('success', 'Redes sociales actualizadas.');
    }
}
