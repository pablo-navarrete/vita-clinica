<section id="vita-banner-carousel" class="vita-banner-carousel" aria-label="Presentación de VITA" aria-roledescription="carrusel">
    <style>
        .vita-banner-carousel .vita-banner-copy { width:100%; max-width:1100px; }
        .vita-banner-text { max-width:900px; }
        .vita-banner-text[data-align="left"] { text-align:left; margin-right:auto; }
        .vita-banner-text[data-align="center"] { text-align:center; margin-left:auto; margin-right:auto; }
        .vita-banner-text[data-align="right"] { text-align:right; margin-left:auto; }
        .vita-banner-text[data-align="left"] p { margin-left:0; }
        .vita-banner-text[data-align="right"] p { margin-right:0; }
        .vita-banner-button[data-align="left"] { text-align:left; }
        .vita-banner-button[data-align="center"] { text-align:center; }
        .vita-banner-button[data-align="right"] { text-align:right; }
        .vita-banner-carousel{position:relative;background:#172b40;color:#fff}.vita-banner-slide{position:relative;min-height:clamp(420px,72vh,740px);display:grid;place-items:center;padding:70px 75px;text-align:center;isolation:isolate}.vita-banner-slide[hidden]{display:none!important}.vita-banner-slide>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}.vita-banner-slide::before{content:'';position:absolute;inset:0;background:var(--banner-overlay,linear-gradient(100deg,#0f172ab0,#0f172a60));z-index:-1}.vita-banner-copy{max-width:900px;overflow-wrap:anywhere}.vita-banner-copy h1{font-size:clamp(34px,4.5vw,64px);color:#fff}.vita-banner-copy .eyebrow{color:#fff;letter-spacing:.17em}.vita-banner-copy p{max-width:680px;margin:0 auto 24px;font-size:17px;line-height:1.8;white-space:pre-line}.vita-banner-nav{position:absolute;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:40px;height:48px;border:1px solid #ffffff60;border-radius:9px;background:#0f172a40;color:#fff;cursor:pointer;font-size:26px}.vita-banner-nav:hover{background:#0f172a80}.vita-banner-prev{left:18px}.vita-banner-next{right:18px}.vita-banner-controls{position:absolute;left:0;right:0;bottom:18px;display:flex;justify-content:center;align-items:center;gap:9px}.vita-banner-dot{width:9px;height:9px;border:0;border-radius:50%;background:#ffffff60;cursor:pointer;padding:0}.vita-banner-dot[aria-current="true"]{background:#fff;width:22px;border-radius:8px}.vita-banner-pause{background:#0f172a60;color:#fff;border:1px solid #ffffff60;border-radius:6px;padding:5px 9px;font-size:11px;cursor:pointer;margin-left:8px}.vita-banner-carousel button:focus-visible{outline:2px solid #fff;outline-offset:4px}@media(max-width:640px){.vita-banner-slide{min-height:58svh;padding:60px 45px}.vita-banner-copy h1{font-size:32px}.vita-banner-copy p{font-size:14px}.vita-banner-nav{width:28px;height:38px}.vita-banner-prev{left:8px}.vita-banner-next{right:8px}}
    </style>
    @foreach($activeBanners as $banner)
        <div class="vita-banner-slide" @if(!($banner['dark_overlay'] ?? true)) style="--banner-overlay:transparent" @endif data-vita-slide role="group" aria-roledescription="diapositiva" aria-label="{{ $loop->iteration }} de {{ $activeBanners->count() }}" @if(!$loop->first) hidden @endif>
            <img src="{{ asset('storage/' . $banner['image_path']) }}" alt="{{ $banner['image_alt'] ?: $banner['title'] }}" @if(!$loop->first) loading="lazy" @endif>
            @unless($banner['image_only'] ?? false)
            <div class="vita-banner-copy">
                <div class="vita-banner-text" data-align="{{ $banner['text_alignment'] ?? 'center' }}">
                    @if($banner['subtitle'])<span class="eyebrow">{{ $banner['subtitle'] }}</span>@endif
                    <h1>{{ $banner['title'] }}</h1>
                    @if($banner['description'])<p>{{ $banner['description'] }}</p>@endif
                </div>
                @if($banner['button_url'] && $banner['button_text'])
                    <div class="vita-banner-button" data-align="{{ $banner['button_alignment'] ?? 'center' }}"><a class="button" href="{{ $banner['button_url'] }}">{{ $banner['button_text'] }}</a></div>
                @endif
            </div>
            @endunless
        </div>
    @endforeach
    @if($activeBanners->count() > 1)
        <button type="button" class="vita-banner-nav vita-banner-prev" data-banner-step="-1" aria-label="Banner anterior">‹</button><button type="button" class="vita-banner-nav vita-banner-next" data-banner-step="1" aria-label="Banner siguiente">›</button>
        <div class="vita-banner-controls">@foreach($activeBanners as $banner)<button type="button" class="vita-banner-dot" data-banner-index="{{ $loop->index }}" aria-label="Ver banner {{ $loop->iteration }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>@endforeach<button type="button" class="vita-banner-pause" aria-pressed="false">Pausar</button></div>
    @endif
</section>
<script>
(() => {
    const root = document.getElementById('vita-banner-carousel');
    const slides = [...root.querySelectorAll('[data-vita-slide]')];
    if (slides.length < 2) return;
    const dots = [...root.querySelectorAll('[data-banner-index]')];
    const pauseButton = root.querySelector('.vita-banner-pause');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0, paused = motion.matches, hovered = false, focused = false;
    const show = value => {
        index = (value + slides.length) % slides.length;
        slides.forEach((slide, number) => { slide.hidden = number !== index; });
        dots.forEach((dot, number) => dot.setAttribute('aria-current', String(number === index)));
    };
    const paintPause = () => { pauseButton.textContent = paused ? 'Reproducir' : 'Pausar'; pauseButton.setAttribute('aria-pressed', String(paused)); };
    root.querySelectorAll('[data-banner-step]').forEach(button => button.addEventListener('click', () => show(index + Number(button.dataset.bannerStep))));
    dots.forEach(dot => dot.addEventListener('click', () => show(Number(dot.dataset.bannerIndex))));
    pauseButton.addEventListener('click', () => { paused = !paused; paintPause(); });
    root.addEventListener('mouseenter', () => { hovered = true; });
    root.addEventListener('mouseleave', () => { hovered = false; });
    root.addEventListener('focusin', () => { focused = true; });
    root.addEventListener('focusout', event => { focused = root.contains(event.relatedTarget); });
    motion.addEventListener('change', event => { if (event.matches) { paused = true; paintPause(); } });
    paintPause();
    setInterval(() => { if (!paused && !hovered && !focused && !document.hidden) show(index + 1); }, 6000);
})();
</script>
