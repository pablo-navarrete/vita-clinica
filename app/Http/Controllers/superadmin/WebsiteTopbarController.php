<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteTopbarController extends Controller
{
    public function edit()
    {
        return view('superadmin.website.topbar', ['topbar' => (object) VitaWebsiteSetting::topbar()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'show_social_links' => ['required', 'boolean'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $data['show_social_links'] = $request->boolean('show_social_links');
        DB::transaction(function () use ($data) {
            VitaWebsiteSetting::firstOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $setting = VitaWebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $content = $setting->content;
            $content['topbar_settings'] = $data;
            $setting->update(['content' => $content]);
        });
        return redirect()->route('superadmin.website.section', ['section' => 'topbar'])->with('success', 'Barra superior actualizada.');
    }
}
