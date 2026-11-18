@php($topbar = \App\Models\VitaWebsiteSetting::topbar($site))
@if($topbar['enabled'])
    <style>
        .vita-public-topbar{background:#eff6ff;color:#1e40af;font-size:12px}
        .vita-public-topbar .topbar-row{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:10px 0;min-height:38px}
        .vita-public-topbar .topbar-contact,.vita-public-topbar .topbar-socials{display:flex;align-items:center;flex-wrap:wrap;gap:16px;min-width:0}
        .vita-public-topbar .topbar-contact a{overflow-wrap:anywhere}
        .vita-public-topbar .topbar-contact span[aria-hidden]{margin-right:6px}
        .vita-public-topbar .topbar-divider{color:#93c5fd}
        .vita-public-topbar a:hover{text-decoration:underline}
        .vita-public-topbar .topbar-socials a{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;font-size:16px;transition:background .18s}
        .vita-public-topbar .topbar-socials a:hover{background:#dbeafe;text-decoration:none}
        @media(max-width:640px){.vita-public-topbar .topbar-row{flex-wrap:wrap;gap:8px}.vita-public-topbar .topbar-contact{gap:10px}.vita-public-topbar .topbar-socials{gap:12px}}
    </style>
    <div class="vita-public-topbar">
        <div class="wrap topbar-row">
            <div class="topbar-contact">
                @if($topbar['phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $topbar['phone']) }}"><span aria-hidden="true">☎</span>{{ $topbar['phone'] }}</a>@endif
                @if($topbar['phone'] && $topbar['email'])<span class="topbar-divider" aria-hidden="true">|</span>@endif
                @if($topbar['email'])<a href="mailto:{{ $topbar['email'] }}"><span aria-hidden="true">✉</span>{{ $topbar['email'] }}</a>@endif
            </div>
            @if($topbar['show_social_links'])
                <nav class="topbar-socials" aria-label="Redes sociales">
                    @foreach(\App\Models\VitaWebsiteSetting::socialLinks($site) as $social)
                        @php($network = \App\Models\WebsiteSocialSetting::networks()[$social['network']] ?? null)
                        @if($network)<a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $network['label'] }}" title="{{ $network['label'] }}"><i class="{{ $network['icon'] }}" aria-hidden="true"></i></a>@endif
                    @endforeach
                </nav>
            @endif
        </div>
    </div>
@endif
