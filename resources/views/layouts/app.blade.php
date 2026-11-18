<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'VITA') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800&display=swap" rel="stylesheet">

        <!-- FontAwesome -->
        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

        <!-- DataTables CSS -->
        <link rel="stylesheet"
            href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

        <!-- DataTables Responsive CSS -->
        <link rel="stylesheet"
            href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root { --date-field-accent: #2563eb; --vita-field-focus: {{ themeColor(500) }}; --vita-field-ring: {{ themeColor(100) }}; }
            .flatpickr-calendar { width: 326px !important; border: 1px solid #e2e8f0 !important; border-radius: 16px !important; box-shadow: 0 18px 45px rgba(15, 23, 42, .18) !important; padding: 10px 12px !important; font-family: inherit !important; overflow: visible; }
            .flatpickr-months { padding: 4px 4px 8px; }
            .flatpickr-current-month { font-size: 1rem !important; font-weight: 700; }
            .flatpickr-current-month .flatpickr-monthDropdown-months, .flatpickr-current-month input.cur-year { font-weight: 700 !important; }
            .flatpickr-weekday { color: #64748b !important; font-weight: 600 !important; }
            .flatpickr-day { border-radius: 9px !important; color: #334155 !important; }
            .flatpickr-day:hover { background: #eff6ff !important; border-color: transparent !important; }
            .flatpickr-day.today { border-color: var(--date-field-accent) !important; }
            .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected:hover { background: var(--date-field-accent) !important; border-color: var(--date-field-accent) !important; color: #fff !important; }
            .flatpickr-day.disabled { color: #cbd5e1 !important; }
            .flatpickr-days, .dayContainer { width: 294px !important; min-width: 294px !important; max-width: 294px !important; }
            .flatpickr-weekdays, .flatpickr-weekdaycontainer { width: 294px !important; }
            .flatpickr-day.vita-holiday:not(.selected) { color: #dc2626 !important; background: #fff1f2; border-color: transparent; }
            .flatpickr-day.vita-holiday:not(.selected):hover { background: #ffe4e6 !important; }
            .flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: var(--date-field-accent) !important; }
            .date-picker-visible { min-height: 2.75rem; width: 100%; cursor: pointer; }
            input[type="date"],
            input[type="datetime-local"],
            input[type="month"] {
                min-height: 2.75rem;
                padding: .55rem .8rem;
                border: 1px solid #cbd5e1;
                border-radius: .65rem;
                background: #fff;
                color: #1f2937;
                accent-color: #2563eb;
                color-scheme: light;
                transition: border-color .18s ease, box-shadow .18s ease;
            }
            input[type="date"]:hover,
            input[type="datetime-local"]:hover,
            input[type="month"]:hover { border-color: #2563eb; }
            input[type="date"]:focus,
            input[type="datetime-local"]:focus,
            input[type="month"]:focus {
                border-color: #2563eb;
                outline: none;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, .16);
            }
            input[type="date"]::-webkit-calendar-picker-indicator,
            input[type="datetime-local"]::-webkit-calendar-picker-indicator,
            input[type="month"]::-webkit-calendar-picker-indicator { padding: .3rem; border-radius: .4rem; cursor: pointer; }

            .dataTables_length select {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                background-image: none !important;
            }

            /* ===== DATATABLE ===== */

            .dataTables_wrapper {
                padding-top: 10px;
            }

            /* BUSCADOR */
            .dataTables_filter {
                margin-bottom: 20px !important;
            }

            .dataTables_filter label {
                font-weight: 600;
                color: #374151;
            }

            .dataTables_filter input {
                margin-left: 10px !important;
                border: 1px solid #d1d5db !important;
                border-radius: 10px !important;
                padding: 8px 14px !important;
                outline: none !important;
                transition: all .2s ease;
            }

            .dataTables_filter input:focus {
                border-color: #3b82f6 !important;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, .2) !important;
            }

            /* SELECT */
            .dataTables_length {
                margin-bottom: 20px !important;
            }

            .dataTables_length select {
                border: 1px solid #d1d5db !important;
                border-radius: 8px !important;
                padding: 5px 10px !important;
            }

            /* TABLA */
            table.dataTable {
                border-collapse: collapse !important;
            }

            table.dataTable thead th {
                background-color: {{ themeColor(100) }} !important;
                color: #1f2937 !important;
                font-weight: 700;
                padding: 14px !important;
                border-bottom: 1px solid #e5e7eb !important;
            }

            table.dataTable tbody td {
                padding: 14px !important;
                border-bottom: 1px solid #f3f4f6;
            }

            table.dataTable tbody tr.odd > *,
            table.dataTable.display tbody tr.odd > .sorting_1 { background-color: #f9f9f9 !important; }
            table.dataTable tbody tr.even > *,
            table.dataTable.display tbody tr.even > .sorting_1 { background-color: #fff !important; }
            table.dataTable tbody tr.odd > td:first-child { background-color: #f9f9f9 !important; }
            table.dataTable tbody tr.even > td:first-child { background-color: #fff !important; }
            table.dataTable tbody tr:hover > *,
            table.dataTable.display tbody tr:hover > .sorting_1 { background-color: #f3f4f6 !important; }

            /* PAGINACIÓN */
            .dataTables_paginate {
                margin-top: 20px !important;
               
            }

            .paginate_button {
                border-radius: 8px !important;
                margin: 0 3px !important;
               
            }

            .paginate_button.current {
                background: #DBEAFE !important;
                
            }

            /* INFO */
            .dataTables_info {
                margin-top: 20px;
                color: #6b7280;
            }
              [x-cloak] {
        display: none !important;
    }
        </style>
        <link rel="stylesheet" href="{{ asset('css/vita-sidebar.css').'?v='.filemtime(public_path('css/vita-sidebar.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/vita-workspace-layout.css').'?v='.filemtime(public_path('css/vita-workspace-layout.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/vita-workspace-theme.css').'?v='.filemtime(public_path('css/vita-workspace-theme.css')) }}">
        @stack('styles')
    @include('components.phone-assets')
        <link rel="stylesheet" href="{{ asset('css/clinic-field-focus.css').'?v='.filemtime(public_path('css/clinic-field-focus.css')) }}">
@include('components.favicon')
</head>

    @php($vitaContentOnly = request()->boolean('_vita_content'))
    <body data-vita-content="{{ $vitaContentOnly ? 'true' : 'false' }}" class="vita-superadmin font-sans antialiased">

        <div x-data="{ sidebarOpen: false }" class="vita-workspace-shell flex h-screen min-h-0 overflow-hidden" style="background-color: {{ themeColor(50) }}">
            @unless($vitaContentOnly)
                @include('layouts.sidebar-superadmin')
            @endunless

            <div class="flex h-screen min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                @unless($vitaContentOnly)
                    @include('layouts.navigation')
                @endunless

                @if($vitaContentOnly)
                <main class="min-h-0 flex-1 overflow-y-auto">
                    <div class="flex min-h-full flex-col" style="display:flex;flex-direction:column;min-height:100%;">
                    @if (isset($header))
                        <header class="shadow" style="background-color: {{ themeColor(50) }}">
                            <div class="px-6 py-6">
                                {{ $header }}
                            </div>
                        </header>
                    @endif
                    <div class="flex-1" style="flex:1 0 auto;">{{ $slot }}</div>
                    @include('layouts.footer-customer')
                    </div>
                </main>
                @else
                    <div id="vita-module-slot" aria-label="Contenido del módulo" style="position:relative;flex:1;min-height:0;overflow:hidden">
                        <iframe id="vita-content-current" name="vita-content-initial" title="Contenido del módulo" data-initial-url="{{ request()->fullUrlWithQuery(['_vita_content' => 1]) }}" style="border:0;width:100%;height:100%;display:block"></iframe>
                        <div id="vita-module-loading" hidden role="status" aria-live="polite">
                            <span class="superadmin-module-spinner" aria-hidden="true"></span>
                            <span id="vita-module-loading-label" class="sr-only"></span>
                            <button id="vita-module-loading-dismiss" type="button" onclick="this.closest('#vita-module-loading').hidden = true">Volver al contenido</button>
                        </div>
                    </div>
                    <style>
                        #vita-module-loading{position:absolute;inset:0;z-index:20;display:flex;align-items:center;justify-content:center;gap:16px;pointer-events:auto;background:transparent}
                        #vita-module-loading[hidden]{display:none}
                        .superadmin-module-spinner{width:40px;height:40px;border:3px solid {{ themeColor(100) }};border-top-color:{{ themeColor(600) }};border-radius:50%;animation:superadmin-module-spin .7s linear infinite}
                        #vita-module-loading-dismiss{display:none;border:1px solid #cbd5e1;border-radius:8px;padding:8px 12px;background:white;font-size:13px}
                        #vita-module-loading[data-error="true"]{flex-direction:column;background:#ffffffeb}
                        #vita-module-loading[data-error="true"] .superadmin-module-spinner{display:none}
                        #vita-module-loading[data-error="true"] #vita-module-loading-label{position:static;width:auto;height:auto;clip:auto;white-space:normal;font-size:14px;color:#475569}
                        #vita-module-loading[data-error="true"] #vita-module-loading-dismiss{display:block}
                        @keyframes superadmin-module-spin{to{transform:rotate(360deg)}}
                        @media(prefers-reduced-motion:reduce){.superadmin-module-spinner{animation-duration:1.5s}}
                    </style>
                @endif
            </div>
        </div>

        <!-- JQUERY -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

        <!-- DATATABLES -->
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

        <!-- RESPONSIVE -->
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

        <!-- SWEET ALERT -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
        <script>
            const vitaChileHolidays = (year) => {
                const dates = {
                    [`${year}-01-01`]: 'Año Nuevo', [`${year}-05-01`]: 'Día del Trabajo',
                    [`${year}-05-21`]: 'Día de las Glorias Navales', [`${year}-06-29`]: 'San Pedro y San Pablo',
                    [`${year}-07-16`]: 'Día de la Virgen del Carmen', [`${year}-08-15`]: 'Asunción de la Virgen',
                    [`${year}-09-18`]: 'Independencia Nacional', [`${year}-09-19`]: 'Día de las Glorias del Ejército',
                    [`${year}-10-12`]: 'Encuentro de Dos Mundos', [`${year}-10-31`]: 'Día de las Iglesias Evangélicas y Protestantes',
                    [`${year}-11-01`]: 'Día de Todos los Santos', [`${year}-12-08`]: 'Inmaculada Concepción', [`${year}-12-25`]: 'Navidad',
                };
                const a = year % 19, b = Math.floor(year / 100), c = year % 100;
                const d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
                const g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30;
                const i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7;
                const m = Math.floor((a + 11 * h + 22 * l) / 451);
                const easter = new Date(year, Math.floor((h + l - 7 * m + 114) / 31) - 1, ((h + l - 7 * m + 114) % 31) + 1);
                [-2, -1].forEach((offset, index) => {
                    const date = new Date(easter); date.setDate(date.getDate() + offset);
                    dates[`${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`] = index === 0 ? 'Viernes Santo' : 'Sábado Santo';
                });
                return dates;
            };
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('input[type="date"]').forEach(input => {
                    if (input._flatpickr) return;
                    flatpickr(input, {
                        locale: 'es', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
                        altInputClass: `${input.className} date-picker-visible`, allowInput: true, disableMobile: true,
                        onDayCreate: (_dates, _dateString, _instance, dayElement) => {
                            const date = dayElement.dateObj;
                            const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                            const holiday = vitaChileHolidays(date.getFullYear())[key];
                            if (holiday) { dayElement.classList.add('vita-holiday'); dayElement.title = holiday; }
                        },
                    });
                });
            });
        </script>

        <!-- STACK -->
        @if($vitaContentOnly)
            @stack('scripts')
            @include('components.flash-toasts')
        @else
            @include('components.vita-assistant')
        @endif
        <script src="{{ asset('js/vita-navigation-bridge.js') }}?v={{ filemtime(public_path('js/vita-navigation-bridge.js')) }}"></script>
        @unless($vitaContentOnly)
            <script src="{{ asset('js/vita-superadmin-navigation.js') }}?v={{ filemtime(public_path('js/vita-superadmin-navigation.js')) }}"></script>
            <script src="{{ asset('js/vita-module-navigation.js') }}?v={{ filemtime(public_path('js/vita-module-navigation.js')) }}"></script>
        @endunless

    </body>
</html>
