@php
    $footer = \App\Models\VitaWebsiteSetting::footer($site);
    $footerName = $footer['title'] ?: $site['brand'];
    $footerLogo = $logosByLocation['footer'] ?? null;
@endphp
@if($footer['enabled'])
    <style>
        .vita-site-footer{margin-top:80px;color:#d1d5db;background:linear-gradient(135deg,#111827,#1f2937);border-top:4px solid #2563eb}
        .vita-site-footer .footer-grid{display:grid;grid-template-columns:5fr 3fr 4fr;gap:48px;padding:64px 0}
        .vita-site-footer .footer-brand{display:inline-flex;align-items:center;gap:14px;margin-bottom:22px}
        .vita-site-footer .footer-brand img{display:block;max-width:180px;max-height:64px;object-fit:contain}
        .vita-site-footer .footer-brand h2{margin:0;color:#fff;font-size:30px}
        .vita-site-footer .footer-heading{margin:0 0 23px;color:#fff;font-size:14px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}
        .vita-site-footer .footer-heading::after{display:block;width:36px;height:2px;margin-top:10px;background:#2563eb;content:''}
        .vita-site-footer .footer-description{max-width:512px;color:#aeb8c5;font-size:14px;line-height:1.8;white-space:pre-line;overflow-wrap:anywhere;margin:0 0 24px}
        .vita-site-footer .footer-contact{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;color:#cbd5e1;font-size:14px;overflow-wrap:anywhere}
        .vita-site-footer .footer-symbol{flex-shrink:0;color:#93c5fd}
        .vita-site-footer .footer-link{display:flex;align-items:center;gap:9px;margin-bottom:13px;color:#cbd5e1;font-size:14px;transition:color .18s,transform .18s;overflow-wrap:anywhere}
        .vita-site-footer a:hover{color:#fff}.vita-site-footer a.footer-link:hover{transform:translateX(3px)}
        .vita-site-footer .footer-socials{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
        .vita-site-footer .footer-social{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border:1px solid #ffffff33;border-radius:50%;background:#ffffff0f;color:white;font-size:18px;transition:background .18s}
        .vita-site-footer .footer-social:hover{background:#2563eb;border-color:#2563eb}
        .vita-site-footer .footer-bottom{border-top:1px solid #ffffff1a;background:#111827;color:#9ca3af;font-size:12px}
        .vita-site-footer .footer-bottom .wrap{display:flex;justify-content:space-between;gap:20px;padding:22px 0}
        @media(max-width:900px){.vita-site-footer .footer-grid{grid-template-columns:1fr 1fr;gap:36px}.vita-site-footer .footer-grid>section:first-child{grid-column:1/-1}}
        @media(max-width:640px){.vita-site-footer{margin-top:48px}.vita-site-footer .footer-grid{grid-template-columns:1fr;padding:42px 0}.vita-site-footer .footer-grid>section:first-child{grid-column:auto}.vita-site-footer .footer-bottom .wrap{flex-direction:column;gap:10px}}
        @media(prefers-reduced-motion:reduce){.vita-site-footer .footer-link,.vita-site-footer .footer-social{transition:none}}
    </style>
    <footer class="vita-site-footer">
        <div class="wrap footer-grid">
            <section>
                <a href="{{ route('vita.website') }}" class="footer-brand" aria-label="{{ $footerName }}">
                    @if(in_array($footer['brand_display'], ['logo', 'both'], true) && $footerLogo)
                        <img src="{{ asset('storage/' . $footerLogo->url) }}" alt="Logo de {{ $footerName }}">
                    @endif
                    @if(in_array($footer['brand_display'], ['title', 'both'], true))<h2>{{ $footerName }}</h2>@endif
                </a>
                @if($footer['description'])<p class="footer-description">{{ $footer['description'] }}</p>@endif
                @if($footer['address'] || $footer['phone'] || $footer['email'])
                    <h3 class="footer-heading">Contacto</h3>
                    @if($footer['address'])<div class="footer-contact"><span class="footer-symbol" aria-hidden="true">⌖</span><span>{{ $footer['address'] }}</span></div>@endif
                    @if($footer['phone'])<div class="footer-contact"><span class="footer-symbol" aria-hidden="true">☎</span><a href="tel:{{ preg_replace('/[^0-9+]/', '', $footer['phone']) }}">{{ $footer['phone'] }}</a></div>@endif
                    @if($footer['email'])<div class="footer-contact"><span class="footer-symbol" aria-hidden="true">✉</span><a href="mailto:{{ $footer['email'] }}">{{ $footer['email'] }}</a></div>@endif
                @endif
                @if($footer['show_social_links'])
                    <nav class="footer-socials" aria-label="Redes sociales">
                        @foreach(\App\Models\VitaWebsiteSetting::socialLinks($site) as $social)
                            @php($network = \App\Models\WebsiteSocialSetting::networks()[$social['network']] ?? null)
                            @if($network)<a class="footer-social" href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $network['label'] }}" title="{{ $network['label'] }}"><i class="{{ $network['icon'] }}" aria-hidden="true"></i></a>@endif
                        @endforeach
                    </nav>
                @endif
            </section>
            <section><h3 class="footer-heading">Enlaces</h3><nav aria-label="Enlaces del sitio">
                @foreach($publicMenus as $menu)
                    <a class="footer-link" href="{{ str_starts_with($menu['url'], '#') ? route('vita.website') . $menu['url'] : $menu['url'] }}"><span class="footer-symbol" aria-hidden="true">›</span><span>{{ $menu['name'] }}</span></a>
                @endforeach
            </nav></section>
            <section><h3 class="footer-heading">Funcionalidades</h3>
                @foreach($site['features'] as $feature)<div class="footer-link"><span class="footer-symbol" aria-hidden="true">✓</span><span>{{ $feature['title'] }}</span></div>@endforeach
            </section>
        </div>
        <div class="footer-bottom"><div class="wrap"><span>© {{ now()->year }} {{ $footerName }}. Todos los derechos reservados.</span><span>Desarrollado por <a href="{{ route('vita.website') }}">VITA</a></span></div></div>
    </footer>
@endif
