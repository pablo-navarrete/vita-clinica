@if ($websiteFooter?->enabled ?? true)
    @php($socialLinks = $websiteSocialSettings?->social_links ?? [])
    @php($socialNetworks = \App\Models\WebsiteSocialSetting::networks())
    @php($footerName = $websiteFooter?->title ?: app('customer')->name)

    <style>
        .clinic-site-footer { margin-top: 5rem; color: #d1d5db; background: linear-gradient(135deg, #111827 0%, #1f2937 100%); border-top: 4px solid {{ themeColor(600) }}; }
        .clinic-site-footer h2, .clinic-site-footer h3 { color: #fff; }
        .clinic-site-footer .footer-heading { margin-bottom: 1.4rem; font-size: .88rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .clinic-site-footer .footer-heading::after { display: block; width: 2.25rem; height: 2px; margin-top: .65rem; background: {{ themeColor(600) }}; content: ''; }
        .clinic-site-footer .footer-description { max-width: 32rem; color: #aeb8c5; line-height: 1.8; }
        .clinic-site-footer .footer-contact { display: flex; align-items: flex-start; gap: .75rem; margin-bottom: .85rem; color: #cbd5e1; }
        .clinic-site-footer .footer-contact i { width: 1.1rem; margin-top: .2rem; color: {{ themeColor(400) }}; }
        .clinic-site-footer .footer-link { display: flex; align-items: center; gap: .55rem; margin-bottom: .8rem; color: #cbd5e1 !important; text-decoration: none; transition: color .18s ease, transform .18s ease; }
        .clinic-site-footer .footer-link i { color: {{ themeColor(400) }}; font-size: .75rem; }
        .clinic-site-footer .footer-link:hover { color: #fff !important; transform: translateX(3px); }
        .clinic-site-footer .footer-specialty { display: flex; align-items: center; gap: .55rem; margin-bottom: .8rem; color: #cbd5e1; }
        .clinic-site-footer .footer-specialty i { color: {{ themeColor(400) }}; font-size: .8rem; }
        .clinic-site-footer .footer-social { display: inline-flex; width: 2.65rem; height: 2.65rem; align-items: center; justify-content: center; margin: .25rem .45rem .25rem 0; border: 1px solid rgba(255,255,255,.2); border-radius: 50%; color: #fff !important; background: rgba(255,255,255,.06); text-decoration: none; transition: background .18s ease, border-color .18s ease, transform .18s ease; }
        .clinic-site-footer .footer-social:hover { border-color: {{ themeColor(600) }}; background: {{ themeColor(600) }}; color: #fff !important; transform: translateY(-2px); }
        .clinic-site-footer-bottom { color: #9ca3af; background: #111827; border-top: 1px solid rgba(255,255,255,.1); }
        .clinic-site-footer-bottom a { color: #d1d5db !important; text-decoration: none; }
        .clinic-site-footer-bottom a:hover { color: #fff !important; }
        @media (max-width: 767.98px) { .clinic-site-footer { margin-top: 3rem; } }
    </style>

    <footer id="contacto" class="clinic-site-footer container-fluid py-5">
        <div class="container py-lg-4">
            <div class="row">
                <section class="col-lg-5 col-md-12 mb-5 mb-lg-0">
                    <a href="{{ url('/') }}" class="d-inline-block mb-3 text-decoration-none">
                        <h2 class="mb-0 font-weight-bold">{{ $footerName }}</h2>
                    </a>
                    @if ($websiteFooter?->description)
                        <p class="footer-description mb-4">{{ $websiteFooter->description }}</p>
                    @endif

                    @if ($websiteFooter?->address || $websiteFooter?->phone || $websiteFooter?->email)
                        <h3 class="footer-heading">Contacto</h3>
                        @if ($websiteFooter?->address)
                            <div class="footer-contact"><i class="fa fa-map-marker-alt"></i><span>{{ $websiteFooter->address }}</span></div>
                        @endif
                        @if ($websiteFooter?->phone)
                            <div class="footer-contact"><i class="fa fa-phone-alt"></i><a class="text-reset" href="tel:{{ preg_replace('/[^0-9+]/', '', $websiteFooter->phone) }}">{{ $websiteFooter->phone }}</a></div>
                        @endif
                        @if ($websiteFooter?->email)
                            <div class="footer-contact"><i class="fa fa-envelope"></i><a class="text-reset" href="mailto:{{ $websiteFooter->email }}">{{ $websiteFooter->email }}</a></div>
                        @endif
                    @endif

                    @if (($websiteFooter?->show_social_links ?? true) && count($socialLinks))
                        <div class="mt-4" aria-label="Redes sociales">
                            @foreach ($socialLinks as $social)
                                @php($network = $socialNetworks[$social['network'] ?? ''] ?? null)
                                @if ($network)
                                    <a class="footer-social" href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $network['label'] }}" title="{{ $network['label'] }}"><i class="{{ $network['icon'] }}"></i></a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="col-lg-3 col-sm-6 mb-5 mb-lg-0">
                    <h3 class="footer-heading">Enlaces</h3>
                    <nav aria-label="Enlaces del sitio">
                        @foreach ($publicMenus as $menu)
                            <a class="footer-link" href="{{ $menu->url }}"><i class="fa fa-angle-right"></i><span>{{ $menu->name }}</span></a>
                        @endforeach
                    </nav>
                </section>

                <section class="col-lg-4 col-sm-6">
                    <h3 class="footer-heading">Servicios</h3>
                    @forelse ($publicSpecialties as $specialty)
                        <div class="footer-specialty"><i class="fa-solid fa-circle-check"></i><span>{{ $specialty->name }}</span></div>
                    @empty
                        <p class="footer-description">Consulta directamente con la clínica para conocer sus servicios.</p>
                    @endforelse
                </section>
            </div>
        </div>
    </footer>

    <div class="clinic-site-footer-bottom container-fluid py-3">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-left mb-2 mb-md-0">
                    <small>&copy; {{ date('Y') }} <a href="{{ url('/') }}">{{ $footerName }}</a>. Todos los derechos reservados.</small>
                </div>
                <div class="col-md-6 text-center text-md-right">
                    <small>Desarrollado por <a href="https://www.vita.cl">VITA</a></small>
                </div>
            </div>
        </div>
    </div>
@endif
