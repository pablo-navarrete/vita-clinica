@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mis horarios</h2>
@endsection

@section('content')
    @php($days = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'])
    <div class="px-4 pb-8 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-xl bg-white shadow-sm">
            <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                <h3 class="font-semibold text-gray-800"><i class="fa-regular fa-calendar-days mr-2" aria-hidden="true"></i>Horarios de atención</h3>
                <p class="mt-1 text-sm text-gray-600">Sede: <strong>{{ $activeBranch?->name ?? 'Sin sede activa' }}</strong></p>
                @if($canManageSchedules)
                    <button type="button" x-data @click="Alpine.$data(document.querySelector('[x-ref=\'scheduleComponent\']')).open(@js($professional))"
                        class="mt-3 rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background-color: {{ themeColor(600) }}">
                        <i class="fa-regular fa-calendar-days mr-2" aria-hidden="true"></i>Administrar mis horarios
                    </button>
                @else
                    <p class="mt-1 text-sm text-gray-600">Estos horarios son asignados y modificados por la administración de la clínica.</p>
                @endif
            </div>
            <div class="space-y-3 p-6">
                @forelse($schedules as $schedule)
                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 p-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: {{ themeColor(100) }}; color: {{ themeColor(700) }}"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                        <div>
                            <p class="font-semibold text-gray-800">{{ $days[$schedule->day_of_week] }}</p>
                            <p class="text-sm text-gray-600">{{ \Illuminate\Support\Carbon::parse($schedule->start_time)->format('H:i') }} – {{ \Illuminate\Support\Carbon::parse($schedule->end_time)->format('H:i') }}</p>
                            @if($schedule->future_appointments_count > 0)
                                <p class="mt-1 text-xs text-amber-700">{{ $schedule->future_appointments_count }} cita(s) futura(s).</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">Aún no tienes horarios registrados en esta sede.</p>
                @endforelse
            </div>
        </section>
    </div>
    @if($canManageSchedules)
        @include('customer.user.partials.schedule-modal')
    @endif
@endsection

@push('scripts')
    @if($canManageSchedules)
        <script>
            @include('customer.user.partials.schedule-script')
        </script>
    @endif
@endpush
