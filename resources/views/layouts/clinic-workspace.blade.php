{{-- Shared clinic shell. Callers supply their own authorized navigation and content. --}}
<div class="vita-workspace-shell" @if($clinicNavigation ?? false) x-data="{ open: false }" style="background-color: {{ themeColor(50) }}" @endif>
    {{ $sidebar ?? '' }}
    <div class="vita-workspace-column">
        @isset($navigation)<div class="vita-workspace-navigation">{{ $navigation }}</div>@endisset
        <div @class(['vita-workspace-body', 'vita-workspace-scroll' => $scrollContent ?? true])>
            {{ $slot }}
        </div>
    </div>
</div>
