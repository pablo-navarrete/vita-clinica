@if ($publicBanners->isNotEmpty())
    <style>
        .banner-cta-button {
            transition: background-color .2s ease, border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        .banner-cta-button:hover,
        .banner-cta-button:focus-visible {
            background-color: {{ themeColor(800) }} !important;
            border-color: {{ themeColor(800) }} !important;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .28);
            color: #fff !important;
            transform: translateY(-3px) scale(1.03);
        }
        @media (max-width: 767.98px) {
            #clinic-banner-carousel .carousel-item { min-height: 58svh !important; }
            #clinic-banner-carousel .carousel-caption > div { width: 100%; max-width: calc(100vw - 2rem) !important; padding: .75rem !important; }
            #clinic-banner-carousel .carousel-caption h1 { font-size: clamp(1.8rem, 8vw, 3rem); line-height: 1.12; overflow-wrap: anywhere; }
            #clinic-banner-carousel .carousel-caption h6 { font-size: .75rem; letter-spacing: .12rem !important; }
            #clinic-banner-carousel .carousel-caption p { margin-right: 0 !important; margin-left: 0 !important; padding: 0 !important; font-size: .9rem; }
            #clinic-banner-carousel .banner-cta-button { padding: .75rem 1rem !important; }
        }
    </style>
    <section class="container-fluid p-0 mb-5">
        <div id="clinic-banner-carousel" class="carousel slide carousel-fade" data-ride="carousel" data-interval="6000">
            @if ($publicBanners->count() > 1)
                <ol class="carousel-indicators">
                    @foreach ($publicBanners as $banner)
                        <li data-target="#clinic-banner-carousel" data-slide-to="{{ $loop->index }}" class="{{ $loop->first ? 'active' : '' }}"></li>
                    @endforeach
                </ol>
            @endif
            <div class="carousel-inner">
                @foreach ($publicBanners as $banner)
                    <div class="carousel-item position-relative {{ $loop->first ? 'active' : '' }}" style="min-height: min(78vh, 760px);">
                        <img class="position-absolute w-100 h-100" src="{{ asset('storage/' . $banner->image_path) }}"
                            alt="{{ $banner->image_alt ?: $banner->title }}" style="object-fit: cover;">
                        <div class="position-absolute w-100 h-100" style="inset:0; background:rgba(0,0,0,.45);"></div>
                        <div class="carousel-caption d-flex flex-column align-items-center justify-content-center" style="inset:0;">
                            <div class="p-3" style="max-width: 900px;">
                                @if ($banner->subtitle)
                                    <h6 class="text-white text-uppercase mb-3" style="letter-spacing:3px;">{{ $banner->subtitle }}</h6>
                                @endif
                                <h1 class="display-3 text-capitalize text-white mb-3">{{ $banner->title }}</h1>
                                @if ($banner->description)
                                    <p class="mx-md-5 px-md-5">{{ $banner->description }}</p>
                                @endif
                                @if ($banner->button_text && $banner->button_url)
                                    <a class="banner-cta-button btn py-3 px-4 mt-3 text-white" href="{{ $banner->button_url }}"
                                        @if($banner->button_url === '#publicBookingModal' && $websiteNavigationButton?->public_booking_enabled) data-target="#publicBookingModal" data-toggle="modal" @endif
                                        style="background-color: {{ themeColor(600) }}; border-color: {{ themeColor(600) }};">
                                        {{ $banner->button_text }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($publicBanners->count() > 1)
                <a class="carousel-control-prev" href="#clinic-banner-carousel" role="button" data-slide="prev" aria-label="Banner anterior">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                </a>
                <a class="carousel-control-next" href="#clinic-banner-carousel" role="button" data-slide="next" aria-label="Siguiente banner">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                </a>
            @endif
        </div>
    </section>
@endif
