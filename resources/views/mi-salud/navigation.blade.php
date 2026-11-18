<header class="portal-topbar">
    <div class="portal-topbar-context"><button type="button" class="portal-mobile-menu" data-portal-menu-toggle aria-label="Abrir menú" aria-controls="portal-sidebar" aria-expanded="false">@include('mi-salud.icons.bars')</button><span class="portal-context-pill">Mi Salud</span><span class="portal-current-page">{{ $page==='profile'?'Mi perfil':'Mi espacio' }}</span></div>
    @php
        $avatarWords=preg_split('/\s+/u',trim($account->name),-1,PREG_SPLIT_NO_EMPTY);
        $avatarInitials=count($avatarWords)>1?mb_substr($avatarWords[0],0,1).mb_substr($avatarWords[1],0,1):mb_substr($avatarWords[0]??'',0,2);
    @endphp
    <div class="portal-topbar-actions">
        <button class="portal-viti-trash" type="button" data-viti-trash aria-label="Ocultar a Viti" title="Arrastra a Viti aquí para ocultarlo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/></svg>
        </button>
        <button class="portal-viti-toggle" type="button" data-viti-nav-toggle aria-label="Abrir asistente Viti" title="Hablar con Viti"><span aria-hidden="true">@include('components.viti-robot')</span></button>
        <a class="portal-website-link" href="{{ route('vita.directory.index') }}" aria-label="Ver web pública de VITA" title="Ver web pública de VITA">@include('mi-salud.icons.globe')</a>
        <details class="portal-account-menu"><summary aria-label="Abrir perfil" title="{{ $account->name }}"><span class="portal-account-avatar" aria-hidden="true">{{ mb_strtoupper($avatarInitials) }}</span></summary><div class="portal-account-dropdown"><div class="portal-account-heading"><strong>{{ $account->name }}</strong><p>{{ $account->email }}</p></div><a href="{{ route('mi-salud.profile') }}">@include('mi-salud.icons.user-regular')<span>Perfil</span></a><form method="POST" action="{{ route('mi-salud.logout') }}">@csrf<button type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M14 8l4 4-4 4M8 12h10"/></svg><span>Cerrar sesión</span></button></form></div></details>
    </div>
</header>
