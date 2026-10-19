<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\WebsiteFooter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteFooterController extends Controller
{
    public function edit(): View
    {
        $footer = WebsiteFooter::firstOrNew(
            ['customer_id' => app('customer')->id],
            ['enabled' => true, 'show_social_links' => true, 'title' => app('customer')->name]
        );

        return view('customer.website-footer.edit', compact('footer'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'show_social_links' => ['required', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1200'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        WebsiteFooter::updateOrCreate(
            ['customer_id' => app('customer')->id],
            $data
        );

        return redirect()->route('customers.website-footer.edit', ['customer' => request()->route('customer')])
            ->with('success', $data['enabled'] ? 'Footer actualizado.' : 'Footer ocultado del sitio público.');
    }
}
