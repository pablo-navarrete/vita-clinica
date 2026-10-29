<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ !empty($previewMode) ? 'Vista previa - ' : '' }}{{ $post->title }} | {{ $blogSetting->title ?: 'Blog' }} | {{ app('customer')->name }}</title>
    <meta name="description" content="{{ $post->excerpt ?: \Illuminate\Support\Str::limit($post->content, 155) }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    @include('customer.web.theme-colors')
    <style>
        body{font-family:Poppins,sans-serif;background:#f8fafc;color:#1f2937}.blog-article{max-width:900px;margin:3rem auto 4rem;padding:0 1rem}.blog-back{display:inline-flex;align-items:center;gap:.5rem;margin-bottom:1.5rem;color:{{ themeColor(700) }};font-weight:600;text-decoration:none}.blog-article__header{margin-bottom:2rem}.blog-article time{font-size:.9rem;color:#64748b}.blog-article h1{margin:.6rem 0;font-size:clamp(2rem,5vw,3rem);line-height:1.2;font-weight:700;color:#172033}.blog-article__excerpt{font-size:1.15rem;color:#64748b;line-height:1.7}.blog-article__image{width:100%;max-height:520px;object-fit:cover;border-radius:1rem;margin:1rem 0 2rem}.blog-article__content{white-space:normal;overflow-wrap:anywhere;font-size:1rem;line-height:1.9;color:#374151}.blog-article__content p{margin:0 0 1.2rem}.blog-article__content table{width:100%;border-collapse:collapse;margin:1rem 0}.blog-article__content th,.blog-article__content td{border:1px solid #d1d5db;padding:.5rem;text-align:left}.blog-article__content img{max-width:100%;height:auto}
    </style>
    <style>
        body { margin: 0; overflow-x: hidden; }
        .blog-hero { position: relative; display: flex; box-sizing: border-box; width: 100vw; min-height: 320px; align-items: center; justify-content: center; margin: 0 calc(50% - 50vw) 3rem; padding: 2rem 1rem; }
        .blog-hero h1 { color: #fff !important; text-align: center; text-shadow: 0 2px 10px rgba(0,0,0,.5); }
        @if($blogSetting->banner_path)
            .blog-hero { background-image: linear-gradient(120deg, rgba(23,36,72,.76), rgba(57,32,130,.58)), url('{{ asset('storage/' . $blogSetting->banner_path) }}') !important; background-position: center; background-size: cover; }
        @endif
        @media (max-width: 767.98px) { .blog-hero { min-height: 220px; margin-bottom: 1.5rem; padding: 1.5rem .75rem; } }
    </style>
</head>
<body>
    @include('customer.web.menu')
    <header class="blog-hero"><h1>{{ $blogSetting->title ?: 'Blog' }}</h1></header>
    <main class="blog-article">
        @if(!empty($previewMode))
            <div style="margin-bottom:1.25rem;padding:.85rem 1rem;border:1px solid {{ themeColor(200) }};border-radius:.75rem;background:{{ themeColor(50) }};color:#1f2937">
                <strong>Vista previa</strong> &middot; Este art&iacute;culo se muestra como en la web, aunque todav&iacute;a no est&eacute; publicado.
            </div>
            <a class="blog-back" href="{{ route('customers.website-blog.index', ['customer' => request()->route('customer')]) }}"><i class="fa-solid fa-arrow-left"></i> Volver a art&iacute;culos</a>
        @else
            <a class="blog-back" href="{{ route('customer.website-blog.public.index', array_merge(['customer' => request()->route('customer')], request()->boolean('public_view') ? ['public_view' => 1] : [])) }}"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        @endif
        <article>
            <header class="blog-article__header">
                @php($articleDate = $post->published_at ?? $post->created_at)
                <time datetime="{{ $articleDate->toDateString() }}">{{ $articleDate->locale('es')->translatedFormat('d F Y') }}</time>
                <h1>{{ $post->title }}</h1>
                @if($post->excerpt)<p class="blog-article__excerpt">{{ $post->excerpt }}</p>@endif
            </header>
            @if($post->image_path)<img class="blog-article__image" src="{{ asset('storage/' . $post->image_path) }}" alt="{{ $post->title }}">@endif
            <div class="blog-article__content">{!! $contentHtml !!}</div>
        </article>
    </main>
    @include('customer.web.footer')
</body>
</html>
