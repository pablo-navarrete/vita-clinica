<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteFooterController extends Controller
{
    public function edit()
    {
        $site = VitaWebsiteSetting::content();
        return view('superadmin.website.footer', ['footer' => (object) VitaWebsiteSetting::footer($site), 'brand' => $site['brand']]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'show_social_links' => ['required', 'boolean'],
            'brand_display' => ['required', 'in:both,logo,title'],
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1200'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $data['show_social_links'] = $request->boolean('show_social_links');
        DB::transaction(function () use ($data) {
            VitaWebsiteSetting::firstOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $setting = VitaWebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $content = $setting->content;
            $content['footer_settings'] = $data;
            $setting->update(['content' => $content]);
        });
        return redirect()->route('superadmin.website.section', ['section' => 'footer'])
            ->with('success', $data['enabled'] ? 'Footer actualizado.' : 'Footer ocultado del sitio público.');
    }
}
