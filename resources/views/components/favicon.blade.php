@once
@php($favicon = app(\App\Services\FaviconService::class)->current())
<link rel="icon" type="{{ $favicon['type'] }}" href="{{ $favicon['url'] }}">
@endonce
