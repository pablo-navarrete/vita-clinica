@php($menuSpecialties = $specialtyOptions ?? app(\App\Services\CustomerDirectoryService::class)->specialtyOptions())
<details class="vita-specialty-menu">
    <summary>Especialidades <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
    <div class="vita-specialty-dropdown">
        @if($menuSpecialties->isNotEmpty())
            <ul aria-label="Buscar clínicas por especialidad">
                @foreach($menuSpecialties as $option)
                    <li><a href="{{ route('vita.directory.index',['especialidad'=>$option['id']]) }}" @if(($selectedSpecialty ?? null) === $option['id']) aria-current="true" @endif>{{ $option['name'] }}</a></li>
                @endforeach
            </ul>
        @else
            <p>Aún no hay especialidades disponibles.</p>
        @endif
    </div>
</details>
@once
<style>
.vita-specialty-menu{position:relative}.vita-specialty-menu summary{display:flex;align-items:center;gap:8px;min-height:44px;cursor:pointer;list-style:none;font:inherit}.vita-specialty-menu summary::-webkit-details-marker{display:none}.vita-specialty-menu summary i{font-size:10px;transition:transform .15s}.vita-specialty-menu[open] summary{color:#1d4ed8}.vita-specialty-menu[open] summary i{transform:rotate(180deg)}.vita-specialty-dropdown{position:absolute;z-index:50;top:calc(100% + 8px);right:0;width:min(360px,calc(100vw - 40px));max-height:360px;overflow-y:auto;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 36px #172b401a;box-sizing:border-box}.vita-specialty-dropdown ul{list-style:none;margin:0;padding:0}.vita-specialty-dropdown li a{display:flex;align-items:center;min-height:44px;padding:10px 14px;box-sizing:border-box;border-radius:8px;white-space:normal;overflow-wrap:anywhere;font-size:14px;color:#172b40}.vita-specialty-dropdown li a:hover,.vita-specialty-dropdown li a[aria-current=true]{background:#eff6ff;color:#1e40af}.vita-specialty-dropdown p{margin:8px;padding:8px;color:#52677d;font-size:14px;line-height:1.6}.vita-specialty-menu summary:focus-visible,.vita-specialty-dropdown a:focus-visible{outline:3px solid #1d4ed8;outline-offset:2px}
</style>
<script>
document.addEventListener('click', event => document.querySelectorAll('.vita-specialty-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; }));
document.addEventListener('keydown', event => { if (event.key === 'Escape') document.querySelectorAll('.vita-specialty-menu[open]').forEach(menu => { menu.open = false; menu.querySelector('summary').focus(); }); });
</script>
@endonce
