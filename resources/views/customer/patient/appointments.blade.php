@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Citas médicas de {{ $patient->name }}
    </h2>
@endsection

@section('content')
    @php
        $statusLabels = [
            0 => 'Agendada',
            1 => 'Confirmada',
            2 => 'En atención',
            3 => 'Atendida',
            4 => 'No asistió',
            5 => 'Cancelada',
        ];
        $statusColors = [
            0 => 'bg-blue-100 text-blue-700',
            1 => 'bg-green-100 text-green-700',
            2 => 'bg-yellow-100 text-yellow-800',
            3 => 'bg-indigo-100 text-indigo-700',
            4 => 'bg-red-100 text-red-700',
            5 => 'bg-gray-100 text-gray-700',
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b p-6 sm:flex-row sm:items-center sm:justify-between"
                    style="background-color: {{ themeColor(100) }}">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $patient->name }} · RUT: {{ $patient->rut }}</p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $scope === 'future' ? 'Citas futuras agendadas' : 'Historial completo de citas' }}
                        </p>
                    </div>
                    <a href="{{ route('customers.patient.index') }}"
                        class="inline-flex items-center justify-center rounded px-4 py-2 text-white transition hover:brightness-90"
                        style="background-color: {{ themeColor(600) }}">
                        Volver a pacientes
                    </a>
                </div>

                <div class="p-6">
                    <div class="mb-4 text-sm text-gray-600">
                        {{ $appointments->total() }} cita(s) {{ $scope === 'future' ? 'futura(s)' : 'registrada(s)' }}
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead style="background-color: {{ themeColor(50) }}">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Fecha y hora</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Profesional</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Especialidad</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($appointments as $appointment)
                                    <tr class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-800">
                                            {{ \Illuminate\Support\Carbon::parse($appointment->start_at)->format('d/m/Y H:i') }}
                                            @if ($appointment->end_at)
                                                – {{ \Illuminate\Support\Carbon::parse($appointment->end_at)->format('H:i') }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $appointment->professional?->name ?? 'Sin profesional' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $appointment->specialty?->name ?? 'Sin especialidad' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusColors[$appointment->status] ?? 'bg-gray-100 text-gray-700' }}">
                                                {{ $statusLabels[$appointment->status] ?? 'Sin estado' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">
                                            {{ $scope === 'future' ? 'No hay citas futuras para este paciente.' : 'Este paciente todavía no tiene citas médicas.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($appointments->hasPages())
                        <div class="mt-6">{{ $appointments->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
