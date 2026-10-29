@extends('layouts.menu-customer')

@section('header')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Registro de actividad</h2>
            <p class="mt-1 text-sm text-gray-500">Cambios registrados en la ficha de {{ $patient->name }}.</p>
        </div>
        <a href="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}" class="rounded-lg bg-gray-200 px-5 py-2 text-gray-700 hover:bg-gray-300">
            <i class="fa-solid fa-arrow-left mr-2"></i>Volver a la ficha
        </a>
    </div>
@endsection

@section('content')
    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-lg font-bold text-gray-800">{{ $patient->name }}</h1>
                        <p class="mt-1 text-sm text-gray-500">RUT: {{ $patient->rut }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background:{{ themeColor(100) }};color:{{ themeColor(700) }}">
                        {{ $auditLogs->total() }} cambios registrados
                    </span>
                </div>
            </section>

            @if ($auditLogs->isEmpty())
                <section class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                    <i class="fa-solid fa-clock-rotate-left mb-3 text-2xl text-gray-400"></i>
                    <p class="text-sm text-gray-500">No hay cambios registrados todavía.</p>
                </section>
            @else
                <div class="space-y-3">
                    @foreach ($auditLogs as $auditLog)
                        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                                <div>
                                    @if ($auditLog->action === 'legacy')
                                        <p class="font-semibold text-gray-800">Registro previo a la auditoría</p>
                                        <p class="mt-1 text-sm text-gray-600">El usuario que lo creó y los cambios anteriores no quedaron registrados.</p>
                                    @else
                                        <p class="font-semibold text-gray-800">
                                            {{ html_entity_decode($auditLog->user?->name ?? 'Usuario eliminado', ENT_QUOTES | ENT_HTML5, 'UTF-8') }}
                                            <span class="font-normal text-gray-600">
                                                @switch($auditLog->action)
                                                    @case('created') creó @break
                                                    @case('updated') modificó @break
                                                    @case('exported') exportó @break
                                                    @case('started') inició @break
                                                    @case('finished') finalizó @break
                                                    @case('cancelled') canceló @break
                                                    @default actualizó
                                                @endswitch
                                                {{ strtolower($auditLog->section) }}
                                            </span>
                                        </p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <time datetime="{{ $auditLog->created_at->toIso8601String() }}">
                                        {{ $auditLog->created_at->timezone('America/Santiago')->format('d/m/Y H:i') }}
                                    </time>
                                    <span class="rounded-full bg-gray-100 px-2 py-1">
                                        Sede: {{ $branchNames[$auditLog->clinic_branch_id] ?? 'Sin sede registrada' }}
                                    </span>
                                </div>
                            </div>

                            @if (!empty($auditLog->changes))
                                <ul class="mt-3 space-y-1 text-sm text-gray-600">
                                    @foreach ($auditLog->changes as $field => $change)
                                        @php
                                            $beforeValue = $change['before'] ?? null;
                                            $afterValue = $change['after'] ?? null;
                                            $fieldLabels = [
                                                'rut' => 'RUT',
                                                'name' => 'Nombre',
                                                'email' => 'Correo electrónico',
                                                'phone' => 'Teléfono',
                                                'gender' => 'Sexo',
                                                'status' => 'Estado',
                                                'address' => 'Dirección',
                                                'birth_date' => 'Fecha de nacimiento',
                                                'clinic_branch_id' => 'Sede',
                                                'insurance_id' => 'Previsión',
                                            ];
                                            $fieldLabel = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                                            $formatAuditValue = function ($value) use ($field, $branchNames) {
                                                if ($value === null || $value === '') {
                                                    return 'Sin dato';
                                                }

                                                if (is_array($value)) {
                                                    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                }

                                                if ($field === 'clinic_branch_id') {
                                                    return $branchNames[(int) $value] ?? 'Sede no disponible';
                                                }

                                                if ($field === 'status' && in_array((string) $value, ['0', '1'], true)) {
                                                    return (string) $value === '1' ? 'Activo' : 'Inactivo';
                                                }

                                                if ($field === 'birth_date') {
                                                    try {
                                                        return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
                                                    } catch (\Throwable) {
                                                        return (string) $value;
                                                    }
                                                }

                                                if ($field === 'gender') {
                                                    return match (mb_strtolower((string) $value)) {
                                                        'm', 'male' => 'Masculino',
                                                        'f', 'female' => 'Femenino',
                                                        default => (string) $value,
                                                    };
                                                }

                                                return (string) $value;
                                            };
                                        @endphp
                                        <li>
                                            <span class="font-medium text-gray-700">{{ $fieldLabel }}:</span>
                                            {{ $formatAuditValue($beforeValue) }}
                                            <span class="px-1 text-gray-400">→</span>
                                            {{ $formatAuditValue($afterValue) }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                    <div class="mb-3 text-sm text-gray-500">
                        Mostrando {{ $auditLogs->firstItem() }}–{{ $auditLogs->lastItem() }} de {{ $auditLogs->total() }} cambios
                    </div>
                    {{ $auditLogs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
