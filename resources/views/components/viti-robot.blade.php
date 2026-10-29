@php($vitiId = 'viti-' . \Illuminate\Support\Str::uuid())
<svg class="viti-robot-art" viewBox="0 0 180 210" role="img" aria-label="Robot Viti">
    <defs>
        <linearGradient id="{{ $vitiId }}-ceramic" x1=".18" y1="0" x2=".82" y2="1"><stop stop-color="#fff"/><stop offset=".42" stop-color="#f7f9ff"/><stop offset=".78" stop-color="#dfe8fb"/><stop offset="1" stop-color="#a9bbdf"/></linearGradient>
        <linearGradient id="{{ $vitiId }}-metal" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#d9fbff"/><stop offset=".32" stop-color="#38eaff"/><stop offset=".7" stop-color="#08aee9"/><stop offset="1" stop-color="#385bd1"/></linearGradient>
        <linearGradient id="{{ $vitiId }}-lilac" x1="0" y1="0" x2=".8" y2="1"><stop stop-color="#f3d8ff"/><stop offset=".4" stop-color="#c17cff"/><stop offset="1" stop-color="#6546cb"/></linearGradient>
        <linearGradient id="{{ $vitiId }}-glass" x1=".15" y1="0" x2=".8" y2="1"><stop stop-color="#283c60"/><stop offset=".3" stop-color="#101d38"/><stop offset="1" stop-color="#050a1c"/></linearGradient>
        <radialGradient id="{{ $vitiId }}-eye" cx=".32" cy=".2" r=".85"><stop stop-color="#d7ffff"/><stop offset=".24" stop-color="#75f8ff"/><stop offset=".68" stop-color="#13d6ff"/><stop offset="1" stop-color="#2693f5"/></radialGradient>
        <filter id="{{ $vitiId }}-shadow" x="-30%" y="-30%" width="160%" height="175%"><feGaussianBlur in="SourceAlpha" stdDeviation="2.5" result="b"/><feOffset dy="3" result="o"/><feComponentTransfer><feFuncA type="linear" slope=".23"/></feComponentTransfer><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>
        <filter id="{{ $vitiId }}-glow"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
    </defs>

    <!-- Aletas y auriculares -->
    <g class="viti-head-accessories">
    <path d="M39 60 27 35q-3-8 4-12l8 3 9 34m84 0 9-34 8-3q7 4 4 12l-12 25" fill="url(#{{ $vitiId }}-lilac)" stroke="#d8e2fa" stroke-width="4" stroke-linejoin="round"/>
    <g class="viti-ears" filter="url(#{{ $vitiId }}-shadow)">
        <ellipse cx="30" cy="91" rx="10" ry="15" fill="url(#{{ $vitiId }}-metal)" stroke="#e4faff" stroke-width="3"/>
        <ellipse cx="150" cy="91" rx="10" ry="15" fill="url(#{{ $vitiId }}-metal)" stroke="#e4faff" stroke-width="3"/>
        <path d="M27 83q-3 7 0 13m126-13q3 7 0 13" fill="none" stroke="#fff" stroke-opacity=".9" stroke-width="2.5" stroke-linecap="round"/>
    </g>
    </g>

    <!-- Brazos cortos, redondeados y articulados -->
    <g class="viti-arm viti-left-arm viti-wave-arm">
        <path d="M53 143 40 152 32 137" fill="none" stroke="#b7c7e5" stroke-width="17" stroke-linecap="round"/>
        <path d="M53 143 40 152 32 137" fill="none" stroke="url(#{{ $vitiId }}-ceramic)" stroke-width="11" stroke-linecap="round"/>
        <circle cx="31" cy="135" r="9" fill="url(#{{ $vitiId }}-ceramic)" stroke="#b7c7e5" stroke-width="2"/>
        <path d="M27 132q4-3 7-1" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M25 138q6 3 12 0" fill="none" stroke="url(#{{ $vitiId }}-metal)" stroke-width="2.5" stroke-linecap="round"/>
    </g>
    <g class="viti-arm viti-rest-arm">
        <path d="m127 143 14 9 7 16" fill="none" stroke="#b7c7e5" stroke-width="17" stroke-linecap="round"/>
        <path d="m127 143 14 9 7 16" fill="none" stroke="url(#{{ $vitiId }}-ceramic)" stroke-width="11" stroke-linecap="round"/>
        <circle cx="149" cy="169" r="9" fill="url(#{{ $vitiId }}-ceramic)" stroke="#b7c7e5" stroke-width="2"/>
    </g>

    <!-- Torso compacto -->
    <g class="viti-torso">
    <path d="M58 137q32-19 64 0l8 36q4 20-40 22-44-2-40-22z" fill="url(#{{ $vitiId }}-ceramic)" stroke="#b7c7e5" stroke-width="3" filter="url(#{{ $vitiId }}-shadow)"/>
    <path d="M67 143q22-11 46 0" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
    <path d="M90 153c-8-11-21-3-15 6l15 16 15-16c6-9-7-17-15-6z" fill="url(#{{ $vitiId }}-lilac)" stroke="#f8eaff" stroke-width="1.5"/>
    <path d="M87 158h6v13h-6zm-5 4h16v5H82z" fill="#fff"/>
    <path d="M73 197q17 5 34 0l8 7q-5 5-25 5t-25-5z" fill="#14213d"/>
    <ellipse cx="90" cy="205" rx="15" ry="4" fill="url(#{{ $vitiId }}-metal)" filter="url(#{{ $vitiId }}-glow)"/>
    </g>

    <!-- Cabeza grande tipo casco -->
    <g class="viti-head">
    <rect x="29" y="27" width="122" height="119" rx="49" fill="url(#{{ $vitiId }}-ceramic)" stroke="#b6c7e7" stroke-width="4" filter="url(#{{ $vitiId }}-shadow)"/>
    <path d="M48 60q5-22 29-27" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round"/>
    <path d="M59 38q31-19 62 0-6 13-31 13T59 38z" fill="url(#{{ $vitiId }}-metal)" stroke="#c5fbff" stroke-width="2" filter="url(#{{ $vitiId }}-shadow)"/>
    <path d="M72 37q18-8 36 0" fill="none" stroke="#fff" stroke-opacity=".9" stroke-width="2.5" stroke-linecap="round"/>

    <!-- Visor de vidrio curvo -->
    <rect x="40" y="62" width="100" height="69" rx="31" fill="url(#{{ $vitiId }}-glass)" stroke="#e3edff" stroke-width="5" filter="url(#{{ $vitiId }}-shadow)"/>
    <path d="M49 82q4-13 17-15h22q-17 4-27 15-5 5-10 5z" fill="#fff" opacity=".14"/>
    <path d="M47 81q2-9 9-12" fill="none" stroke="#fff" stroke-opacity=".75" stroke-width="2.5" stroke-linecap="round"/>

    <!-- Ojos brillantes y sonrisa -->
    <g class="viti-eye viti-eye-left"><ellipse cx="67" cy="96" rx="8" ry="11" fill="url(#{{ $vitiId }}-eye)" filter="url(#{{ $vitiId }}-glow)"/><ellipse cx="65" cy="92" rx="2.5" ry="3" fill="#fff"/><ellipse cx="68" cy="96" rx="2" ry="5" fill="#c9ffff" opacity=".7"/></g>
    <g class="viti-eye viti-eye-right"><ellipse cx="113" cy="96" rx="8" ry="11" fill="url(#{{ $vitiId }}-eye)" filter="url(#{{ $vitiId }}-glow)"/><ellipse cx="111" cy="92" rx="2.5" ry="3" fill="#fff"/><ellipse cx="114" cy="96" rx="2" ry="5" fill="#c9ffff" opacity=".7"/></g>
    <path class="viti-mouth" d="M82 108q8 8 16 0" fill="none" stroke="#55efff" stroke-width="4" stroke-linecap="round" filter="url(#{{ $vitiId }}-glow)"/>
    <ellipse class="viti-mouth-open" cx="90" cy="107" rx="5" ry="7" fill="#49eaff" filter="url(#{{ $vitiId }}-glow)"/>
    <ellipse cx="54" cy="109" rx="5" ry="2" fill="#fa83cf" opacity=".7"/><ellipse cx="126" cy="109" rx="5" ry="2" fill="#fa83cf" opacity=".7"/>
    </g>
</svg>
