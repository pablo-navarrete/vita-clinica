    @php
        $publicPreview = request()->boolean('public_view');
        $publicBlogSetting = \App\Models\WebsiteBlogSetting::where('customer_id', app('customer')->id)->first();
        $publicBlogEnabled = (bool) ($publicBlogSetting?->enabled ?? false);
        $publicBlogTitle = filled($publicBlogSetting?->title) ? $publicBlogSetting->title : 'Blog';
    @endphp
    <style>
        .website-public-topbar .website-topbar-contact {
            min-height: 1.5rem;
            gap: .35rem .65rem;
            color: #334155;
        }
        .website-public-topbar .website-topbar-contact small {
            display: inline-flex;
            align-items: center;
            color: #334155;
            font-size: .8rem;
            font-weight: 500;
            line-height: 1.4;
        }
        .website-public-topbar .website-topbar-contact i {
            color: {{ themeColor(700) }};
        }
        .website-public-topbar .website-topbar-divider {
            padding: 0 .25rem;
            color: #94a3b8;
        }
        .website-public-topbar .website-topbar-social a {
            display: inline-flex;
            width: 1.8rem;
            height: 1.8rem;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            text-decoration: none !important;
            transition: color .18s ease, background-color .18s ease;
        }
        .website-public-topbar .website-topbar-social a:hover,
        .website-public-topbar .website-topbar-social a:focus-visible {
            text-decoration: none !important;
        }
        .website-public-topbar .website-topbar-social a:hover {
            color: #fff !important;
            background-color: {{ themeColor(700) }};
        }
        .public-admin-entry {
            display: inline-flex;
            width: 1.9rem;
            height: 1.9rem;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            color: {{ themeColor(700) }} !important;
            background: transparent;
            font-size: .9rem;
            text-decoration: none !important;
            transition: color .18s ease, background-color .18s ease;
        }
        .public-admin-entry:hover,
        .public-admin-entry:focus-visible {
            color: #fff !important;
            background: {{ themeColor(700) }};
            text-decoration: none !important;
        }
        .website-access-divider {
            display: inline-block;
            height: 1.25rem;
            margin: 0 .45rem 0 .6rem;
            border-left: 1px solid rgba(51, 65, 85, .28);
            vertical-align: middle;
        }
        .public-admin-entry:focus-visible {
            outline: 2px solid {{ themeColor(500) }};
            outline-offset: 2px;
        }
        @media (max-width: 767.98px) {
            .website-public-topbar .row { row-gap: .35rem; }
            .website-public-topbar .website-topbar-social { text-align: right !important; }
        }
    </style>
    @if (auth()->check() && !$publicPreview)
        @php
            $userRoles = auth()->user()->roles();
            $hasAdminAccess = $userRoles->where('roles.customer_id', app('customer')->id)->where('roles.name', 'Administrador')->exists()
                || auth()->user()->roles()->whereNull('roles.customer_id')->where('roles.name', 'Super Administrador')->exists();
            $canManageWebsite = !auth()->user()->roles()->where('roles.customer_id', app('customer')->id)->where('roles.name', 'Doctor')->exists() || $hasAdminAccess;
        @endphp
        @php $websiteMaintenance = \App\Models\WebsiteMaintenance::firstOrNew(['customer_id' => app('customer')->id]); @endphp
        <style>
            .public-admin-link {
                display: inline-flex;
                align-items: center;
                border-radius: .375rem;
                padding: .35rem .55rem !important;
                border: 0;
                background: transparent;
                cursor: pointer;
                text-decoration: none !important;
                transition: background-color .18s ease, color .18s ease;
            }
            .public-admin-link:hover,
            .public-admin-link:focus-visible {
                background-color: rgba(255, 255, 255, .2);
                color: #fff !important;
                text-decoration: none !important;
            }
            .public-admin-link:focus-visible {
                outline: 2px solid rgba(255, 255, 255, .8);
                outline-offset: 2px;
            }
        </style>
        <div class="container-fluid public-admin-bar" style="background-color: {{ themeColor(800) }}">
            <div class="d-flex flex-wrap align-items-center px-2 px-lg-5 py-2" style="gap: .4rem .75rem; direction:ltr;">
                <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.dashboard.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-gauge-high mr-1"></i> Panel</a>
                @if ($canManageWebsite)
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.menus.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-bars mr-1"></i> Men&uacute;</a>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.banners.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-images mr-1"></i> Banners</a>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.website-blog.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-newspaper mr-1"></i> Blog</a>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.logo.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-image mr-1"></i> Logo</a>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.website-topbar.edit', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-bars-staggered mr-1"></i> Barra superior</a>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.website-footer.edit', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-shoe-prints mr-1"></i> Footer</a>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.website-socials.edit', ['customer' => request()->route('customer')]) }}"><i class="fa-brands fa-instagram mr-1"></i> Redes sociales</a>
                @endif
                @if ($canManageWebsite)
                    <form method="POST" action="{{ route('customers.website-maintenance.toggle', ['customer' => request()->route('customer')]) }}" class="d-inline m-0">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $websiteMaintenance->enabled ? 0 : 1 }}">
                        <input type="hidden" name="message" value="{{ $websiteMaintenance->message }}">
                        <button type="submit" class="public-admin-link small font-weight-bold text-white"
                            style="appearance:none;-webkit-appearance:none;line-height:inherit;vertical-align:baseline;color:inherit"
                            title="{{ $websiteMaintenance->enabled ? 'Desactivar mantenimiento' : 'Activar mantenimiento' }}">
                            <i class="fa-solid {{ $websiteMaintenance->enabled ? 'fa-circle-check' : 'fa-screwdriver-wrench' }} mr-1"></i>{{ $websiteMaintenance->enabled ? 'Desactivar mantenimiento' : 'Activar mantenimiento' }}
                        </button>
                    </form>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ request()->fullUrlWithQuery(['public_view' => 1]) }}" target="_blank" rel="noopener noreferrer">
                        <i class="fa-regular fa-eye mr-1"></i> Ver como visitante
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if (($websiteTopbar?->enabled ?? true) || !$publicPreview)
    <!-- Topbar Start -->
    <div class="container-fluid bg-light website-public-topbar" style="background-color: {{ themeColor(100) }} !important;">
        <div class="row py-2 px-lg-5">
            @if ($websiteTopbar?->enabled ?? true)
            <div class="col-lg-6 text-left mb-2 mb-lg-0">
                <div class="d-inline-flex flex-wrap align-items-center website-topbar-contact">
                    @if ($websiteTopbar?->phone)<small><i class="fa fa-phone-alt mr-2"></i>{{ $websiteTopbar->phone }}</small>@endif
                    @if ($websiteTopbar?->phone && $websiteTopbar?->email)<small class="website-topbar-divider">|</small>@endif
                    @if ($websiteTopbar?->email)<small><i class="fa fa-envelope mr-2"></i>{{ $websiteTopbar->email }}</small>@endif
                </div>
            </div>
            @endif
            @php $topbarSocialLinks = ($websiteTopbar?->enabled ?? true) && $websiteTopbar?->show_social_links ? ($websiteSocialSettings?->social_links ?? []) : []; @endphp
            <div class="col-lg-6 {{ ($websiteTopbar?->enabled ?? true) ? '' : 'offset-lg-6' }} text-lg-right website-topbar-social">
                <div class="website-public-access d-inline-flex align-items-center">
                    @if (count($topbarSocialLinks))
                        @php $topbarSocialNetworks = \App\Models\WebsiteSocialSetting::networks(); @endphp
                        <div class="d-inline-flex align-items-center" aria-label="Redes sociales">
                        @foreach ($topbarSocialLinks as $social)
                            @php $network = $topbarSocialNetworks[$social['network'] ?? ''] ?? null; @endphp
                            @if ($network && !empty($social['url']))
                                <a class="mx-2" href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                                    aria-label="{{ $network['label'] }}" title="{{ $network['label'] }}"
                                    style="color: {{ themeColor(700) }}"><i class="{{ $network['icon'] }}"></i></a>
                            @endif
                        @endforeach
                        </div>
                    @endif
                    @if (!$publicPreview)
                        @guest
                        <span class="website-access-divider" aria-hidden="true"></span>
                            <a class="public-admin-entry" href="{{ route('login') }}" aria-label="Acceso administración" title="Acceso administración"><i class="fa-solid fa-lock" aria-hidden="true"></i></a>
                        @endguest
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- Topbar End -->
    @endif

    <!-- Navbar Start -->
    <div class="container-fluid p-0">
        <nav class="navbar navbar-expand-lg bg-white navbar-light py-3 py-lg-0 px-lg-5">
           <a href="{{ $publicPreview ? url('/') . '?public_view=1' : url('/') }}"
   class="navbar-brand ml-lg-3 public-clinic-brand"
   style="display:flex; align-items:center; gap:10px;">

    @if(isset(app('logos_by_location')['header']))
        <img class="public-clinic-logo" src="{{ asset('storage/' . app('logos_by_location')['header']->url) }}"
             style="height:80px; width:auto; object-fit:contain;">
    @endif

    <h1 class="m-0 public-clinic-name"
        style="color: {{ themeColor(800) }} !important; margin:0;">
        {{ app()->bound('customer') ? app('customer')->name : config('app.name') }}
    </h1>

</a>
            @if ($publicMenus->isNotEmpty())
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Abrir menú de navegación">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-between px-lg-3" id="navbarCollapse">
                <div class="navbar-nav m-auto py-0" style="direction:ltr;">
                    @if ($hasConfiguredMenus)
                        @foreach ($publicMenus as $menu)
                            @php $menuPath = parse_url($menu->url, PHP_URL_PATH) ?: ''; @endphp
                            @php $currentPath = '/' . trim(request()->path(), '/'); @endphp
                            @php $isActiveMenu = (isset($page) && (int) $menu->website_page_id === (int) $page->id) || ($menuPath !== '' && '/' . trim($menuPath, '/') === $currentPath); @endphp
                            @php $menuHref = $menu->url; @endphp
                            @if ($publicPreview && str_starts_with($menuHref, '/'))
                                @php $menuHref = url($menuHref) . (str_contains($menuHref, '?') ? '&' : '?') . 'public_view=1'; @endphp
                            @endif
                            <a href="{{ $menuHref }}" class="nav-item nav-link {{ $isActiveMenu ? 'active' : '' }}"
                                @if($isActiveMenu) aria-current="page" @endif>{{ $menu->name }}</a>
                        @endforeach
                    @else
                    <a href="{{ url('/') }}" class="nav-item nav-link active" style="color: {{ themeColor(600) }} !important;">
                        INICIO
                    </a>
                    <a href="#quienes-somos" class="nav-item nav-link">QUIENES SOMOS</a>
                    <a href="#servicios" class="nav-item nav-link">SERVICIOS</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">ESPECIALIDADES</a>
                        <div class="dropdown-menu rounded-0 m-0">
                            <a href="appointment.html" class="dropdown-item">TRAUMATOLOGÍA</a>
                            <a href="opening.html" class="dropdown-item">MEDICO GENERAL</a>
                            <a href="team.html" class="dropdown-item">PSICOLOGÍA</a>
                            <a href="testimonial.html" class="dropdown-item">Testimonial</a>
                        </div>
                    </div>
                    <a href="#contacto" class="nav-item nav-link">CONTACTO</a>
                    @endif
                    @if ($publicBlogEnabled)
                        @php $blogHref = route('customer.website-blog.public.index', ['customer' => request()->route('customer')]); @endphp
                        @if ($publicPreview) @php $blogHref .= '?public_view=1'; @endphp @endif
                        <a href="{{ $blogHref }}" class="nav-item nav-link {{ request()->is('blog*') ? 'active' : '' }}" @if(request()->is('blog*')) aria-current="page" @endif>{{ $publicBlogTitle }}</a>
                    @endif
                </div>
                @if ($websiteNavigationButton?->enabled)
                    @php
                        $buttonHref = match ($websiteNavigationButton->destination) {
                            'contact' => url('/') . '#contacto',
                            'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', (string) $websiteNavigationButton->whatsapp_phone),
                            'page' => $publicMenus->firstWhere('id', $websiteNavigationButton->menu_id)?->url ?? '#',
                            'booking' => $websiteNavigationButton->public_booking_enabled ? '#publicBookingModal' : '#',
                            'custom' => $websiteNavigationButton->custom_url ?: '#',
                            default => '#',
                        };
                        if ($publicPreview && str_starts_with($buttonHref, '/') && !str_starts_with($buttonHref, '//')) {
                            [$buttonPath, $buttonFragment] = array_pad(explode('#', $buttonHref, 2), 2, null);
                            $buttonHref = url($buttonPath) . (str_contains($buttonPath, '?') ? '&' : '?') . 'public_view=1'
                                . ($buttonFragment ? '#' . $buttonFragment : '');
                        }
                    @endphp
                    <a href="{{ $buttonHref }}" class="btn public-navigation-cta"
                        @if($websiteNavigationButton->destination === 'booking' && $websiteNavigationButton->public_booking_enabled) data-target="#publicBookingModal" data-toggle="modal" @endif
                        style="background-color: {{ themeColor(600) }} !important; color:white;">
                        {{ $websiteNavigationButton->label }}
                    </a>
                @endif
            </div>
            @endif
            @if (!$publicPreview)
                @auth
                    <a href="{{ route('customers.dashboard.index', ['customer' => request()->route('customer')]) }}" class="public-admin-entry d-none ml-auto ml-lg-2 my-2 my-lg-0">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                        <span>Ir al panel</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="public-admin-entry d-none ml-auto ml-lg-2 my-2 my-lg-0">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span>Acceso administración</span>
                    </a>
                @endauth
            @endif
        </nav>
    </div>
    <!-- Navbar End -->
