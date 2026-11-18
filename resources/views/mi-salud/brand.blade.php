@php
    $portalBrand = request()->attributes->get('mi_salud_brand');
    if (!$portalBrand) {
        $settings = \App\Models\VitaWebsiteSetting::content();
        $logos = \App\Models\VitaWebsiteSetting::logosByLocation($settings);
        $logo = $logos['login'] ?? $logos['header'] ?? null;
        if (!$logo && \Illuminate\Support\Facades\Storage::disk('public')->exists('admin/logos/logo-vita-3.png')) {
            $logo = (object) ['url' => 'admin/logos/logo-vita-3.png'];
        }
        $portalBrand = ['name' => $settings['brand'], 'display' => $settings['header_brand_display'],
            'logo' => $logo];
        request()->attributes->set('mi_salud_brand', $portalBrand);
    }
@endphp
@if(in_array($portalBrand['display'], ['logo','both'], true) && $portalBrand['logo'])
    <img class="portal-brand-logo" src="{{ asset('storage/'.$portalBrand['logo']->url) }}" alt="Logo {{ $portalBrand['name'] }}">
@endif
@if(in_array($portalBrand['display'], ['title','both'], true) || !$portalBrand['logo'] || in_array($page??null, ['login','register'], true))
    <span class="portal-brand-title">{{ $portalBrand['name'] }}</span>
@endif
