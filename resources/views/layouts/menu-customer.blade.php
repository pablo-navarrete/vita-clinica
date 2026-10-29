@if(auth()->check() && !request()->ajax() && !request()->expectsJson() && !request()->boolean('_vita_frame') && !request()->boolean('_vita_content') && request()->header('Sec-Fetch-Dest') !== 'iframe' && !request()->routeIs('telemedicine.patient.*', 'customers.telemedicine.room'))
    @include('layouts.vita-shell')
@else
    @include('layouts.menu-customer-content')
@endif
