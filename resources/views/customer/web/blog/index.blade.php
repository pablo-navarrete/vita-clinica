<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $blogSetting->title ?: 'Blog' }} | {{ app('customer')->name }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    @include('customer.web.theme-colors')
    <style>
        body{font-family:Figtree,sans-serif;background:#f8fafc;color:#1f2937}.blog-hero{padding:4rem 1rem;text-align:center;background:linear-gradient(135deg,{{ themeColor(800) }},{{ themeColor(600) }});color:#fff}.blog-hero h1{font-size:clamp(2rem,5vw,3.25rem);font-weight:700;margin:0}.blog-hero p{margin:.75rem 0 0;color:#eef2ff}.blog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:1.25rem;padding:3rem 0}.blog-card{overflow:hidden;background:#fff;border:1px solid #e5e7eb;border-radius:1rem;box-shadow:0 8px 24px #0f172a0a}.blog-card img{width:100%;height:210px;object-fit:cover}.blog-card__placeholder{height:210px;display:grid;place-items:center;background:{{ themeColor(50) }};color:{{ themeColor(600) }};font-size:2.5rem}.blog-card__body{padding:1.35rem}.blog-card time{font-size:.8rem;color:#64748b}.blog-card h2{margin:.55rem 0;font-size:1.2rem;font-weight:600}.blog-card h2 a{color:#172033;text-decoration:none}.blog-card p{color:#64748b;line-height:1.7}.blog-read{display:inline-flex;align-items:center;gap:.5rem;margin-top:.5rem;color:{{ themeColor(700) }};font-weight:600;text-decoration:none}.blog-empty{padding:4rem 1rem;text-align:center;color:#64748b}
    </style>
    <style>
        body { margin: 0; overflow-x: hidden; }
        .blog-hero { position: relative; display: flex; box-sizing: border-box; width: 100vw; min-height: 320px; align-items: center; justify-content: center; margin: 0 calc(50% - 50vw) 3rem; padding: 2rem 1rem; }
        .blog-hero h1 { color: #fff !important; text-align: center; text-shadow: 0 2px 10px rgba(0,0,0,.5); }
        .blog-hero p { color: #fff !important; text-shadow: 0 1px 6px rgba(0,0,0,.5); }
        @if($blogSetting->banner_path)
            .blog-hero { background-image: linear-gradient(120deg, rgba(23,36,72,.76), rgba(57,32,130,.58)), url('{{ asset('storage/' . $blogSetting->banner_path) }}') !important; background-position: center; background-size: cover; }
        @endif
        @media (max-width: 767.98px) { .blog-hero { min-height: 220px; margin-bottom: 1.5rem; padding: 1.5rem .75rem; } }
    </style>
    <style>
        .blog-search { display:flex; max-width:38rem; margin:0 auto 1rem; gap:.6rem; }
        .blog-search input { min-width:0; flex:1; border:1px solid #d1d5db; border-radius:.65rem; padding:.75rem 1rem; outline:none; }
        .blog-search input:focus { border-color:{{ themeColor(500) }}; box-shadow:0 0 0 3px {{ themeColor(100) }}; }
        .blog-search button,.blog-search__clear { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; border:0; border-radius:.65rem; padding:.7rem 1rem; font-weight:600; text-decoration:none; }
        .blog-search button { background:{{ themeColor(600) }}; color:#fff; transition:background-color .2s ease,box-shadow .2s ease,transform .2s ease; }
        .blog-search button:hover,.blog-search button:focus-visible { background:{{ themeColor(800) }}; box-shadow:0 8px 20px rgba(0,0,0,.22); color:#fff; transform:translateY(-2px); }
        .blog-search__clear { color:#475569; background:#e2e8f0; transition:background-color .2s ease,color .2s ease; }
        .blog-search__clear:hover,.blog-search__clear:focus-visible { color:#1f2937; background:#cbd5e1; }
        .blog-pagination { padding:0 0 2rem; }
        .blog-pagination nav { display:flex; justify-content:center; }
        @media(max-width:480px) { .blog-search button span { display:none; } .blog-search button,.blog-search__clear { padding:.7rem .85rem; } }
    </style>
@include('components.phone-assets')
@include('components.favicon')
</head>
<body>
    @include('customer.web.menu')
    <header class="blog-hero"><h1>{{ $blogSetting->title ?: 'Blog' }}</h1></header>
    <main class="container">
        <form class="blog-search" method="GET" action="{{ route('customer.website-blog.public.index', ['customer' => request()->route('customer')]) }}">
            @if(request()->boolean('public_view'))<input type="hidden" name="public_view" value="1">@endif
            <input type="search" name="search" value="{{ $search }}" placeholder="Buscar artículos..." aria-label="Buscar artículos">
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i><span>Buscar</span></button>
            @if($search !== '')<a class="blog-search__clear" href="{{ route('customer.website-blog.public.index', array_merge(['customer' => request()->route('customer')], request()->boolean('public_view') ? ['public_view' => 1] : [])) }}" aria-label="Limpiar búsqueda"><span>Limpiar</span></a>@endif
        </form>
        @if($posts->isEmpty() && $search !== '')
            <div class="blog-empty"><i class="fa-solid fa-magnifying-glass mb-3 text-4xl"></i><p>No se encontraron artículos con esa búsqueda.</p></div>
        @elseif($posts->isEmpty())
            <div class="blog-empty"><i class="fa-regular fa-newspaper mb-3 text-4xl"></i><p>Aún no hay artículos publicados.</p></div>
        @else
            <div class="blog-grid">
                @foreach($posts as $post)
                    <article class="blog-card">
                        @if($post->image_path)<img src="{{ asset('storage/' . $post->image_path) }}" alt="{{ $post->title }}" loading="lazy">@else<div class="blog-card__placeholder"><i class="fa-regular fa-newspaper"></i></div>@endif
                        <div class="blog-card__body">
                            <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->locale('es')->translatedFormat('d F Y') }}</time>
                            <h2><a href="{{ route('customer.website-blog.public.show', array_merge(['customer' => request()->route('customer'), 'slug' => $post->slug], request()->boolean('public_view') ? ['public_view' => 1] : [])) }}">{{ $post->title }}</a></h2>
                            <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit($post->content, 180) }}</p>
                            <a class="blog-read" href="{{ route('customer.website-blog.public.show', array_merge(['customer' => request()->route('customer'), 'slug' => $post->slug], request()->boolean('public_view') ? ['public_view' => 1] : [])) }}">Leer artículo <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($posts->hasPages())<div class="blog-pagination">{{ $posts->links() }}</div>@endif
        @endif
    </main>
    @include('customer.web.footer')
</body>
</html>
