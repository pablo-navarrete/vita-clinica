<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\VitaWebsiteSetting;
use App\Models\WebsiteSocialSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteSocialController extends Controller
{
    public function edit()
    {
        return view('superadmin.website.socials', [
            'settings' => (object) ['social_links' => VitaWebsiteSetting::socialLinks()],
            'networks' => WebsiteSocialSetting::networks(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'social_links' => ['nullable', 'array', 'max:12'],
            'social_links.*' => ['array:network,url'],
            'social_links.*.network' => ['required', 'string', 'distinct', 'in:' . implode(',', array_keys(WebsiteSocialSetting::networks()))],
            'social_links.*.url' => ['required', 'url:http,https', 'max:500'],
        ]);
        DB::transaction(function () use ($data) {
            VitaWebsiteSetting::firstOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $setting = VitaWebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $content = $setting->content;
            $content['social_links'] = array_values($data['social_links'] ?? []);
            $setting->update(['content' => $content]);
        });
        return redirect()->route('superadmin.website.section', ['section' => 'socials'])->with('success', 'Redes sociales actualizadas.');
    }
}
