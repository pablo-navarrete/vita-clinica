<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\Request;

class VitaWebsiteController extends Controller
{
    public function index()
    {
        $site = VitaWebsiteSetting::content();
        if (!$site['published']) return response()->view('vita.website-unavailable', [], 503);
        return view('vita.website', compact('site'));
    }

    public function edit()
    {
        return view('superadmin.website.edit', ['site' => VitaWebsiteSetting::content()]);
    }

    public function preview()
    {
        return view('vita.website', ['site' => VitaWebsiteSetting::content(), 'preview' => true]);
    }

    public function editSection(string $section)
    {
        if ($section === 'logo') return app(LogoController::class)->index();
        if ($section === 'banners') return app(WebsiteBannerController::class)->index();
        if ($section === 'menus') return app(WebsiteMenuController::class)->index();
        if ($section === 'footer') return app(WebsiteFooterController::class)->edit();
        if ($section === 'topbar') return app(WebsiteTopbarController::class)->edit();
        if ($section === 'socials') return app(WebsiteSocialController::class)->edit();
        $definition = config('vita_website_sections.' . $section);
        abort_unless($definition, 404);
        return view('superadmin.website.section', ['section' => $section, 'definition' => $definition, 'site' => VitaWebsiteSetting::content()]);
    }

    public function updateSection(Request $request, string $section)
    {
        if ($section === 'footer') return app(WebsiteFooterController::class)->update($request);
        if ($section === 'topbar') return app(WebsiteTopbarController::class)->update($request);
        if ($section === 'socials') return app(WebsiteSocialController::class)->update($request);
        $definition = config('vita_website_sections.' . $section);
        abort_unless($definition, 404);
        $safeUrl = function ($attribute, $value, $fail) {
            if (!$value) return;
            $local = preg_match('/\A(?:\/(?!\/)|#[a-zA-Z])[^\s\\\\]*\z/', $value);
            $external = filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https']);
            if (!$local && !$external) $fail('El destino debe ser una URL HTTP/HTTPS, una ruta local o un ancla válida.');
        };
        $rules = [];
        foreach ($definition['fields'] as $field => $label) {
            $rules[$field] = match (true) {
                in_array($field, ['published', 'topbar_enabled']) => ['required', 'boolean'],
                $field === 'contact_email' => ['nullable', 'email', 'max:254'],
                str_starts_with($field, 'social_') => ['nullable', 'url:http,https', 'max:500'],
                $field === 'topbar_url' => ['nullable', 'string', 'max:500', $safeUrl],
                $field === 'contact_phone' => ['nullable', 'string', 'max:40'],
                str_starts_with($field, 'topbar_') => ['nullable', 'string', 'max:200'],
                default => ['required', 'string', 'max:' . (str_ends_with($field, 'description') ? 1000 : 200)],
            };
        }
        foreach ($definition['collections'] ?? [] as $key => $collection) {
            $rules[$key] = [$key === 'features' ? 'required' : 'nullable', 'array', 'max:' . ($key === 'posts' ? 50 : 12)];
            if ($key === 'features') $rules[$key][] = 'min:1';
            $rules[$key . '.*'] = ['array:' . implode(',', array_keys($collection['fields']))];
            foreach ($collection['fields'] as $field => $label) {
                $rules[$key . '.*.' . $field] = match ($field) {
                    'url' => ['required', 'string', 'max:500', $safeUrl],
                    'slug' => ['required', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', 'distinct'],
                    'published' => ['required', 'boolean'],
                    'content' => ['required', 'string', 'max:20000'],
                    default => ['required', 'string', 'max:' . (in_array($field, ['description', 'answer', 'excerpt']) ? 1500 : 200)],
                };
            }
        }
        $asset = match ($section) { 'logo' => 'logo', 'banners' => 'banner', default => null };
        if ($asset) {
            $rules[$asset] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
            $rules['remove_asset'] = ['nullable', 'boolean'];
        }
        $data = $request->validate($rules);
        foreach (['published', 'topbar_enabled'] as $flag) if (array_key_exists($flag, $data)) $data[$flag] = $request->boolean($flag);
        foreach ($definition['collections'] ?? [] as $key => $collection) $data[$key] = array_values($data[$key] ?? []);
        foreach ($data['posts'] ?? [] as $index => $post) $data['posts'][$index]['published'] = (bool) $post['published'];
        unset($data['remove_asset']);
        if ($asset) {
            unset($data[$asset]);
            if ($request->boolean('remove_asset')) $data[$asset . '_path'] = null;
            if ($request->hasFile($asset)) $data[$asset . '_path'] = $request->file($asset)->store('vita/website', 'public');
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            VitaWebsiteSetting::firstOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $setting = VitaWebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $setting->update(['content' => array_replace($setting->content, $data)]);
        });
        return back()->with('success', $definition['title'] . ': cambios guardados correctamente.');
    }

    public function update(Request $request)
    {
        $rules = ['published' => 'required|boolean', 'contact_email' => 'nullable|email|max:254', 'contact_phone' => 'nullable|string|max:40',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', 'remove_logo' => 'nullable|boolean',
            'features' => 'required|array|min:1|max:12', 'features.*' => 'array:title,description', 'features.*.title' => 'required|string|max:100', 'features.*.description' => 'required|string|max:500',
            'faqs' => 'nullable|array|max:12', 'faqs.*' => 'array:question,answer', 'faqs.*.question' => 'required|string|max:180', 'faqs.*.answer' => 'required|string|max:1500'];
        foreach (['brand' => 60, 'tagline' => 100, 'hero_label' => 80, 'hero_title' => 140, 'hero_description' => 600, 'cta_label' => 50,
            'about_title' => 140, 'about_description' => 1000, 'contact_title' => 140, 'contact_description' => 600, 'footer' => 200] as $field => $max) {
            $rules[$field] = "required|string|max:{$max}";
        }
        $data = $request->validate($rules);
        unset($data['logo'], $data['remove_logo']);
        $data['published'] = $request->boolean('published');
        $data['features'] = array_values($data['features']);
        $data['faqs'] = array_values($data['faqs'] ?? []);
        $data['logo_path'] = VitaWebsiteSetting::content()['logo_path'];
        if ($request->boolean('remove_logo')) $data['logo_path'] = null;
        if ($request->hasFile('logo')) $data['logo_path'] = $request->file('logo')->store('vita/website', 'public');
        VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => array_replace(VitaWebsiteSetting::content(), $data)]);
        return back()->with('success', 'La web de VITA se actualizó correctamente.');
    }
}
