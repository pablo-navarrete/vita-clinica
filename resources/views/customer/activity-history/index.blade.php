@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Historial de actividad</h2>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    <style>
        #activity-module-filter + .select2-container,
        #activity-user-filter + .select2-container { width: 100% !important; }
        #activity-module-filter + .select2-container,
        #activity-user-filter + .select2-container { margin-top: .25rem; }
        #activity-module-filter + .select2-container .select2-selection--single,
        #activity-user-filter + .select2-container .select2-selection--single {
            height: 38px; border: 1px solid #d1d5db; border-radius: .375rem;
        }
        #activity-module-filter + .select2-container .select2-selection__rendered,
        #activity-user-filter + .select2-container .select2-selection__rendered {
            line-height: 36px; padding-left: .75rem; color: #374151; font-size: .875rem;
        }
        #activity-module-filter + .select2-container .select2-selection__arrow,
        #activity-user-filter + .select2-container .select2-selection__arrow { height: 36px; }
    </style>
@endpush

@section('content')
    <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Filtrar movimientos</h3>
            <form method="GET" action="{{ route('customers.activity-history.index', ['customer' => request()->route('customer')]) }}" class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <label class="text-sm font-medium text-gray-700">Buscar
                    <input name="search" value="{{ request('search') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="Paciente, profesional, acción">
                </label>
                <label class="text-sm font-medium text-gray-700">Módulo
                    <select id="activity-module-filter" name="module" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        @foreach ($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-700">Usuario
                    <select id="activity-user-filter" name="user_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-700">Acción
                    <select name="action" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todas</option>
                        <option value="created" @selected(request('action') === 'created')>Creó</option>
                        <option value="updated" @selected(request('action') === 'updated')>Actualizó</option>
                        <option value="deleted" @selected(request('action') === 'deleted')>Eliminó</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-700">Desde
                    <input type="date" name="from" value="{{ request('from') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                </label>
                <label class="text-sm font-medium text-gray-700">Hasta
                    <input type="date" name="to" value="{{ request('to') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                </label>
                <div class="flex gap-2 md:col-span-3 xl:col-span-6">
                    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color: {{ themeColor(600) }}">Filtrar</button>
                    <a href="{{ route('customers.activity-history.index', ['customer' => request()->route('customer')]) }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4">
                <h3 class="font-semibold text-gray-800">Movimientos registrados</h3>
                <span class="text-sm text-gray-500">{{ $logs->total() }} registro(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Fecha y hora</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Usuario</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Módulo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Actividad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">{{ $log->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-800">{{ $log->actor_name ?: 'Sistema' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">{{ $log->module }}</td>
                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p>{{ $log->summary }}</p>
                                    @if (!empty($log->changes['fields']))
                                        <p class="mt-1 text-xs text-gray-500">Campos: {{ implode(', ', $log->changes['fields']) }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500">No hay movimientos para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t px-5 py-4">{{ $logs->links() }}</div>
        </section>

        <p class="text-xs text-gray-500">El historial registra acciones y campos modificados; no guarda contraseñas ni el contenido de la ficha clínica.</p>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            $('#activity-module-filter, #activity-user-filter').select2({
                width: '100%',
                minimumResultsForSearch: 0,
                language: {
                    noResults: () => 'No se encontraron resultados',
                    searching: () => 'Buscando…'
                }
            });
        });
    </script>
@endpush
