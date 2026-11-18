<button class="portal-menu-backdrop" type="button" data-portal-menu-close aria-label="Cerrar menú" tabindex="-1" hidden></button>
<aside id="portal-sidebar" class="portal-sidebar" data-preference-key="mi-salud-sidebar-compact:{{ $account->id }}" aria-label="Navegación de Mi Salud">
    <a class="portal-sidebar-brand" href="{{ route('mi-salud.panel') }}">@include('mi-salud.brand')<span class="portal-sidebar-caption vita-workspace-title">Mi Salud</span></a>
    <div class="portal-sidebar-toolbar"><button type="button" class="portal-sidebar-collapse" data-portal-menu-toggle aria-controls="portal-sidebar" aria-expanded="true" aria-label="Compactar menú"><x-menu-icon name="chevron-left" /></button></div>
    <nav aria-label="Menú personal">
        <div class="portal-menu-section"><h2>Mi Salud</h2></div>
        <a class="portal-menu-link" href="{{ route('mi-salud.panel') }}" @if($page==='panel') aria-current="page" @endif aria-label="Mi espacio" title="Mi espacio"><x-menu-icon name="chart-line" /><span>Mi espacio</span></a>
        <a class="portal-menu-link" href="{{ route('mi-salud.attentions.index') }}" @if($page==='attentions') aria-current="page" @endif aria-label="Mis atenciones" title="Mis atenciones"><x-menu-icon name="file-medical" /><span>Mis atenciones</span></a>
        <a class="portal-menu-link" href="{{ route('mi-salud.booking.index') }}" @if($page==='booking') aria-current="page" @endif aria-label="Agendar atención" title="Agendar atención"><x-menu-icon name="calendar-plus" /><span>Agendar atención</span></a>
        <div class="portal-menu-section"><h2>Mi cuenta</h2></div>
        <a class="portal-menu-link" href="{{ route('mi-salud.profile') }}" @if($page==='profile') aria-current="page" @endif aria-label="Mi perfil" title="Mi perfil"><x-menu-icon name="user" /><span>Mi perfil</span></a>
    </nav>
</aside>
