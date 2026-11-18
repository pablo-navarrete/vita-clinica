<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($publicPage) ? $publicPage['name'] . ' · ' : '' }}{{ $site['brand'] }} · {{ $site['tagline'] }}</title>
    <meta name="description" content="{{ $site['hero_description'] }}">
    @if(!empty($preview))<meta name="robots" content="noindex,nofollow">@endif
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:90px}body{margin:0;font-family:Figtree,sans-serif;color:#172b40;background:#fff}a{color:inherit;text-decoration:none}button,input{font:inherit}.wrap{width:min(1160px,calc(100% - 48px));margin:auto}.site-header{border-bottom:1px solid #edf1f5;background:#fff}.header-row{display:flex;align-items:center;justify-content:space-between;gap:20px;min-height:84px}.brand{display:flex;gap:12px;align-items:center;font-weight:700;font-size:24px;letter-spacing:-.04em}.brand img{max-width:120px;height:44px;object-fit:contain}.brand-mark{color:#1d4ed8;letter-spacing:.16em}.nav{display:flex;gap:28px;align-items:center;font-size:16px;color:#516274}.nav a:hover{color:#1d4ed8}.button{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:14px 23px;border:1px solid #1d4ed8;border-radius:10px;background:#1d4ed8;color:white;font-size:14px;font-weight:600;transition:background .15s}.button:hover{background:#1e40af;color:white}.button.secondary{background:white;border-color:#dce5ef;color:#334155}.eyebrow{display:block;font-size:11px;font-weight:700;letter-spacing:.17em;color:#1d4ed8;margin-bottom:20px;text-transform:uppercase}.hero{background:linear-gradient(140deg,#f8fbff,#eff6ff 65%,#f1f8f8);overflow:hidden}.hero-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:70px;align-items:center;padding:100px 0 110px}h1{font-size:clamp(38px,4.8vw,66px);line-height:1.08;letter-spacing:-.05em;margin:0 0 25px;text-wrap:balance;overflow-wrap:anywhere}.hero p{color:#5b6d7e;font-size:17px;line-height:1.8;max-width:510px}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:30px}.workspace{position:relative;border:1px solid #e0e8f1;border-radius:18px;background:#fff;box-shadow:0 24px 70px #244b7720;transform:rotate(-2deg)}.workspace-bar{display:flex;gap:6px;align-items:center;padding:17px;border-bottom:1px solid #edf1f5;font-size:11px;color:#6d8094}.dot{width:7px;height:7px;border-radius:50%;background:#cbd5e1}.workspace-bar strong{margin-left:auto;font-size:11px;color:#1d4ed8;letter-spacing:.1em}.workspace-body{padding:25px}.workspace-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:23px}.workspace-head strong{font-size:18px}.pill{background:#eff6ff;color:#1d4ed8;font-size:10px;padding:6px 9px;border-radius:20px}.appointment{display:flex;gap:14px;align-items:center;padding:17px 0;border-top:1px solid #eff3f6}.appointment time{font-size:12px;color:#7b8b9b;width:38px}.appointment div{border-left:3px solid #93c5fd;padding:3px 0 3px 12px}.appointment strong{display:block;font-size:13px;font-weight:600}.appointment small{display:block;font-size:11px;color:#8190a0;margin-top:5px}.workspace-foot{padding:14px 25px;background:#f8fafc;border-radius:0 0 18px 18px;font-size:11px;color:#718298}.section{padding:88px 0}.section-heading{max-width:660px;margin-bottom:38px}h2{font-size:clamp(28px,3.2vw,42px);letter-spacing:-.04em;line-height:1.15;margin:0 0 18px;text-wrap:balance}.section-heading p,.contact p{font-size:16px;line-height:1.8;color:#66788a;white-space:pre-line}.features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.feature{padding:28px;border:1px solid #e5ecf3;border-radius:14px}.feature-number{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-size:12px;margin-bottom:24px}.feature h3{font-size:18px;letter-spacing:-.02em;margin:0 0 12px;overflow-wrap:anywhere}.feature p{color:#6b7b8c;font-size:14px;line-height:1.8;margin:0;white-space:pre-line}.faq-section{background:#f8fafc}.faq-grid{display:grid;grid-template-columns:1fr 1.4fr;gap:80px}details{border-bottom:1px solid #dde5ee;padding:20px 0}summary{font-weight:600;font-size:15px;cursor:pointer;line-height:1.6}details p{font-size:14px;color:#66788a;line-height:1.8;white-space:pre-line}.contact{display:flex;justify-content:space-between;gap:40px;align-items:center;padding:45px;border:1px solid #e2eaf3;border-radius:18px;background:#f4f8ff}.contact-copy{max-width:620px}.contact p{margin-bottom:0}.contact-links{display:flex;flex-direction:column;align-items:flex-start;gap:14px;overflow-wrap:anywhere;min-width:0}.contact-links a:not(.button){font-size:14px;color:#1d4ed8}.site-footer{border-top:1px solid #e5ecf3;padding:26px 0;color:#7b8998;font-size:12px}.footer-row{display:flex;justify-content:space-between;gap:20px}.preview{padding:12px 24px;text-align:center;background:#fef3c7;color:#92400e;font-size:13px}.preview a{text-decoration:underline}.menu-toggle{display:none}a:focus-visible,summary:focus-visible{outline:2px solid #2563eb;outline-offset:5px}
        @media(max-width:900px){.hero-grid{gap:35px;padding:70px 0}.features{grid-template-columns:repeat(2,minmax(0,1fr))}.faq-grid{gap:35px}.contact{flex-direction:column;align-items:flex-start}.nav{gap:15px}.nav a{font-size:14px}}
        @media(max-width:640px){.wrap{width:calc(100% - 36px)}.header-row{min-height:76px;flex-wrap:wrap;padding:14px 0;gap:12px}.brand{font-size:21px}.brand img{height:32px;max-width:85px}.nav{gap:14px;flex-wrap:wrap}.nav a[href="#funcionalidades"],.nav a[href="#preguntas"]{display:none}.nav .button{padding:10px 12px}.hero-grid{grid-template-columns:1fr;gap:45px;padding:55px 0}.hero p{font-size:15px}.workspace{max-width:400px;transform:none;width:100%;margin:auto}.section{padding:55px 0}.features,.faq-grid{grid-template-columns:1fr}.feature{padding:24px}.contact{padding:26px}.footer-row{flex-direction:column;gap:8px}.hero-grid h1{font-size:40px}}
        @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.button{transition:none}}
        .site-topbar{display:flex;justify-content:center;gap:20px;flex-wrap:wrap;padding:10px 20px;background:#eff6ff;color:#1e40af;font-size:12px}.site-topbar a{text-decoration:underline}.hero-image{width:100%;max-height:480px;object-fit:contain;border-radius:18px}.social-links{display:flex;gap:16px;flex-wrap:wrap}.social-links a:hover{text-decoration:underline}.article-link{display:inline-block;margin-top:20px;font-size:13px;color:#1d4ed8}.site-post{max-width:850px}.site-post h1{font-size:clamp(32px,4vw,50px)}.post-intro{color:#64748b;font-size:18px;line-height:1.8}.post-content{white-space:pre-wrap;overflow-wrap:anywhere;font-size:16px;line-height:1.9;margin-top:30px}
        @media(max-width:640px){.nav{width:100%}.nav a[href="#funcionalidades"],.nav a[href="#preguntas"]{display:inline}.nav a{font-size:14px}.footer-row{flex-wrap:wrap}}
    </style>
<link rel="stylesheet" href="{{ asset('css/vita-patient-access.css').'?v='.filemtime(public_path('css/vita-patient-access.css')) }}">
@include('components.phone-assets')
@include('components.favicon')
</head>
<body>
    @if(!empty($preview))<div class="preview">Vista previa · <a href="{{ route('superadmin.website.edit') }}">Volver al editor</a>@if(!$site['published']) · La web pública está desactivada.@endif</div>@endif
    @include('vita.topbar')
    <header class="site-header"><div class="wrap header-row">
        <a href="{{ route('vita.directory.index') }}" class="brand" aria-label="{{ $site['brand'] }}">
            @if(in_array($site['header_brand_display'], ['logo', 'both'], true) && isset($logosByLocation['header']))
                <img src="{{ asset('storage/' . $logosByLocation['header']->url) }}" alt="Logo {{ $site['brand'] }}">
            @endif
            @if(in_array($site['header_brand_display'], ['title', 'both'], true) || !isset($logosByLocation['header']))
                <span class="brand-mark">{{ $site['brand'] }}</span>
            @endif
        </a>
        @php
            $publicMenus = collect(\App\Models\VitaWebsiteSetting::menus($site))->filter(fn ($menu) => $menu['status']);
            $navigationButton = $site['navigation_button'];
            $buttonHref = match ($navigationButton['destination']) {
                'contact' => route('vita.website') . '#contacto',
                'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $navigationButton['whatsapp_phone'] ?? ''),
                'page' => $publicMenus->firstWhere('id', $navigationButton['menu_id'])['url'] ?? null,
                'custom' => $navigationButton['custom_url'],
                default => null,
            };
        @endphp
        <nav class="nav" aria-label="Navegación principal">
            <a href="{{ route('vita.directory.index') }}">Inicio</a>
            @include('vita.specialty-menu')
            <a href="{{ route('vita.website') }}">Trabaja con nosotros</a>
            @foreach($publicMenus->reject(fn($menu) => in_array($menu['url'], ['/', '/vita'], true)) as $menu)
                <a href="{{ str_starts_with($menu['url'], '#') ? route('vita.website') . $menu['url'] : $menu['url'] }}" @if(isset($publicPage) && $publicPage['id'] === $menu['id']) aria-current="page" @endif>{{ $menu['name'] }}</a>
            @endforeach
            @if($navigationButton['enabled'] && $buttonHref)<a class="button" href="{{ str_starts_with($buttonHref, '#') ? route('vita.website') . $buttonHref : $buttonHref }}">{{ $navigationButton['label'] }}</a>@endif
            <a class="button secondary" href="{{ route('login') }}">Acceso clínicas</a>
            @include('vita.patient-access')
        </nav>
    </div></header>
    <main>
        @if(isset($publicPage))
            <article class="wrap section site-post"><h1>{{ $publicPage['name'] }}</h1>
                @foreach($publicPage['sections'] as $section)
                    <section style="margin-top:36px">
                        @if(filled($section['title']))<h2>{{ $section['title'] }}</h2>@endif
                        @if(filled($section['body']))<div class="post-content">{{ $section['body'] }}</div>@endif
                        @if($section['image_path'])<img src="{{ asset('storage/' . $section['image_path']) }}" alt="{{ $section['image_alt'] }}" loading="lazy" style="display:block;max-width:100%;height:auto;margin-top:24px;border-radius:14px">@endif
                    </section>
                @endforeach
            </article>
        @elseif(isset($post))
            <article class="wrap section site-post"><a href="{{ route('vita.website') }}#blog" class="eyebrow">Volver al blog</a><h1>{{ $post['title'] }}</h1><p class="post-intro">{{ $post['excerpt'] }}</p><div class="post-content">{{ $post['content'] }}</div></article>
        @else
        @php($activeBanners = collect(\App\Models\VitaWebsiteSetting::banners($site))->filter(fn ($banner) => $banner['status'])->values())
        @if($activeBanners->isNotEmpty())
            @include('vita.banners')
        @else
        <section class="hero"><div class="wrap hero-grid"><div>
            <span class="eyebrow">{{ $site['hero_label'] }}</span><h1>{{ $site['hero_title'] }}</h1><p>{{ $site['hero_description'] }}</p>
            <div class="actions"><a class="button" href="#contacto">{{ $site['cta_label'] }} <span aria-hidden="true">↗</span></a><a class="button secondary" href="#funcionalidades">Conoce las herramientas</a></div>
        </div>@if($site['banner_path'] && $site['banners'] === null)<img class="hero-image" src="{{ asset('storage/' . $site['banner_path']) }}" alt="{{ $site['hero_title'] }}">@else<div class="workspace" aria-label="Ilustración de una agenda de trabajo, con datos de ejemplo">
            <div class="workspace-bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span><strong>{{ $site['brand'] }}</strong></div>
            <div class="workspace-body"><div class="workspace-head"><strong>Tu jornada, en orden.</strong><span class="pill">Agenda</span></div>
                <div class="appointment"><time>09:00</time><div><strong>Consulta de seguimiento</strong><small>Atención presencial</small></div></div>
                <div class="appointment"><time>10:00</time><div><strong>Todo listo para la próxima atención</strong><small>Ficha clínica · Antecedentes · Documentos</small></div></div>
                <div class="appointment"><time>11:30</time><div><strong>Cerca, incluso a distancia</strong><small>Consulta por telemedicina</small></div></div>
            </div><div class="workspace-foot">Vista ilustrativa · Sin datos de pacientes</div>
        </div>@endif</div></section>
        @endif
        <section class="section"><div class="wrap"><div class="section-heading"><h2>Conoce a los centros que utilizan VITA</h2><p>Encuentra clínicas y centros de atención y visita sus sitios web.</p></div><a class="button" href="{{ route('vita.directory.index') }}">Explorar clínicas y centros</a></div></section>
        <section id="funcionalidades" class="section"><div class="wrap"><div class="section-heading"><span class="eyebrow">{{ $site['tagline'] }}</span><h2>{{ $site['about_title'] }}</h2><p>{{ $site['about_description'] }}</p></div>
            <div class="features">@foreach($site['features'] as $feature)<article class="feature"><span class="feature-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $feature['title'] }}</h3><p>{{ $feature['description'] }}</p></article>@endforeach</div>
        </div></section>
        @if($site['faqs'])<section id="preguntas" class="section faq-section"><div class="wrap faq-grid"><div><span class="eyebrow">ESTAMOS PARA ACOMPAÑARTE</span><h2>Resolvamos tus dudas.</h2></div><div>@foreach($site['faqs'] as $faq)<details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach</div></div></section>@endif
        <section id="contacto" class="section"><div class="wrap"><div class="contact"><div class="contact-copy"><span class="eyebrow">CONVERSEMOS</span><h2>{{ $site['contact_title'] }}</h2><p>{{ $site['contact_description'] }}</p></div><div class="contact-links">
            @if($site['contact_email'])<a class="button" href="mailto:{{ $site['contact_email'] }}">Escríbenos</a><a href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }}</a>@endif
            @if($site['contact_phone'])<a href="tel:{{ preg_replace('/[^+0-9]/', '', $site['contact_phone']) }}">{{ $site['contact_phone'] }}</a>@endif
            @if(!$site['contact_email'] && !$site['contact_phone'])<span>Pronto compartiremos nuestros canales de contacto.</span>@endif
        </div></div></div></section>
        @endif
    </main>
    @include('vita.footer')
</body>
</html>
