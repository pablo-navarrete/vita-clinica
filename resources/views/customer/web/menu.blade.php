    @php($publicPreview = request()->boolean('public_view'))
    @if (auth()->check() && !$publicPreview)
        @php($canManageWebsite = !auth()->user()->roles()->where('roles.name', 'Doctor')->exists())
        @php($websiteMaintenance = \App\Models\WebsiteMaintenance::firstOrNew(['customer_id' => app('customer')->id]))
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
        <div class="container-fluid d-none d-lg-block public-admin-bar" style="background-color: {{ themeColor(800) }}">
            <div class="d-flex flex-wrap align-items-center px-lg-5 py-2" style="gap: 1.25rem; direction:ltr;">
                <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.dashboard.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-gauge-high mr-1"></i> Panel</a>
                @if ($canManageWebsite)
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.menus.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-bars mr-1"></i> Men&uacute;</a>
                    <a class="public-admin-link small font-weight-bold text-white" href="{{ route('customers.banners.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-images mr-1"></i> Banners</a>
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

    @if ($websiteTopbar?->enabled ?? true)
    <!-- Topbar Start -->
    <div class="container-fluid bg-light d-none d-lg-block" style="background-color: {{ themeColor(100) }} !important;">
        <div class="row py-2 px-lg-5">
            <div class="col-lg-6 text-left mb-2 mb-lg-0">
                <div class="d-inline-flex align-items-center">
                    @if ($websiteTopbar?->phone)<small><i class="fa fa-phone-alt mr-2"></i>{{ $websiteTopbar->phone }}</small>@endif
                    @if ($websiteTopbar?->phone && $websiteTopbar?->email)<small class="px-3">|</small>@endif
                    @if ($websiteTopbar?->email)<small><i class="fa fa-envelope mr-2"></i>{{ $websiteTopbar->email }}</small>@endif
                </div>
            </div>
            @php($topbarSocialLinks = $websiteTopbar?->show_social_links ? ($websiteSocialSettings?->social_links ?? []) : [])
            @if (count($topbarSocialLinks))
                @php($topbarSocialNetworks = \App\Models\WebsiteSocialSetting::networks())
                <div class="col-lg-6 text-lg-right">
                    <div class="d-inline-flex align-items-center" aria-label="Redes sociales">
                        @foreach ($topbarSocialLinks as $social)
                            @php($network = $topbarSocialNetworks[$social['network'] ?? ''] ?? null)
                            @if ($network && !empty($social['url']))
                                <a class="mx-2" href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                                    aria-label="{{ $network['label'] }}" title="{{ $network['label'] }}"
                                    style="color: {{ themeColor(700) }}"><i class="{{ $network['icon'] }}"></i></a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    <!-- Topbar End -->
    @endif


    <!-- Navbar Start -->
    <div class="container-fluid p-0">
        <nav class="navbar navbar-expand-lg bg-white navbar-light py-3 py-lg-0 px-lg-5">
           <a href="{{ $publicPreview ? url('/') . '?public_view=1' : url('/') }}"
   class="navbar-brand ml-lg-3"
   style="display:flex; align-items:center; gap:10px;">

    @if(isset(app('logos_by_location')['header']))
        <img src="{{ asset('storage/' . app('logos_by_location')['header']->url) }}"
             style="height:80px; width:auto; object-fit:contain;">
    @endif

    <h1 class="m-0"
        style="color: {{ themeColor(800) }} !important; margin:0;">
        {{ app()->bound('customer') ? app('customer')->name : config('app.name') }}
    </h1>

</a>
            @if ($publicMenus->isNotEmpty())
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-between px-lg-3" id="navbarCollapse">
                <div class="navbar-nav m-auto py-0" style="direction:ltr;">
                    @if ($hasConfiguredMenus)
                        @foreach ($publicMenus as $menu)
                            @php($menuPath = parse_url($menu->url, PHP_URL_PATH) ?: '')
                            @php($currentPath = '/' . trim(request()->path(), '/'))
                            @php($isActiveMenu = (isset($page) && (int) $menu->website_page_id === (int) $page->id) || ($menuPath !== '' && '/' . trim($menuPath, '/') === $currentPath))
                            @php($menuHref = $menu->url)
                            @if ($publicPreview && str_starts_with($menuHref, '/'))
                                @php($menuHref = url($menuHref) . (str_contains($menuHref, '?') ? '&' : '?') . 'public_view=1')
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
                </div>
                <a href="" class="btn d-none d-lg-block" style="background-color: {{ themeColor(600) }} !important; color:white;" >WHATSAPP</a>
            </div>
            @endif
        </nav>
    </div>
    <!-- Navbar End -->
