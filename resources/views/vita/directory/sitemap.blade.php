{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>{{ route('vita.directory.index') }}</loc></url>@foreach($profiles as $profile)@if($service->websiteUrl($profile->customer))<url><loc>{{ route('vita.directory.show',$profile->slug) }}</loc></url>@endif @endforeach</urlset>
