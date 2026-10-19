@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Notificaciones de citas</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="rounded-md bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-5 shadow-sm">
            <div>
                <h3 class="font-semibold text-gray-800">Avisos de tu agenda</h3>
                <p class="mt-1 text-sm text-gray-500">Recibir&aacute;s avisos cuando una cita se agende, actualice o cancele.</p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('customers.notifications.read-all', ['customer' => request()->route('customer')]) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color: {{ themeColor(600) }}">Marcar todas como le&iacute;das</button>
                </form>
            @endif
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            @forelse ($notifications as $notification)
                @php
                    $item = $notification->data;
                @endphp
                <article class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5 last:border-0 {{ $notification->unread() ? 'bg-blue-50/50' : '' }}">
                    <div class="flex min-w-0 flex-1 gap-3">
                        <span class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $notification->unread() ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">
                            <i class="fa-solid {{ ($item['event'] ?? '') === 'cancelled' ? 'fa-calendar-xmark' : 'fa-calendar-check' }}"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-semibold text-gray-800">
                                    <a href="{{ route('customers.notifications.open', ['customer' => request()->route('customer'), 'notification' => $notification->id]) }}" class="hover:underline" style="color: {{ themeColor(700) }}">
                                        {{ $item['title'] ?? 'Actualizaci&oacute;n de cita' }}
                                    </a>
                                </h4>
                                @if ($notification->unread())<span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Nueva</span>@endif
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ $item['message'] ?? '' }}</p>
                            <dl class="mt-3 grid gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                                <div><dt class="inline font-medium text-gray-700">Paciente: </dt><dd class="inline">{{ $item['patient_name'] ?? '—' }}@if(!empty($item['patient_rut'])) ({{ $item['patient_rut'] }})@endif</dd></div>
                                <div><dt class="inline font-medium text-gray-700">Fecha y hora: </dt><dd class="inline">{{ !empty($item['starts_at']) ? \Illuminate\Support\Carbon::parse($item['starts_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</dd></div>
                                <div><dt class="inline font-medium text-gray-700">Especialidad: </dt><dd class="inline">{{ $item['specialty'] ?? '—' }}</dd></div>
                                @if (!empty($item['reason']))<div><dt class="inline font-medium text-gray-700">Motivo: </dt><dd class="inline">{{ $item['reason'] }}</dd></div>@endif
                            </dl>
                            <p class="mt-2 text-xs text-gray-400">Recibida {{ $notification->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                    @if ($notification->unread())
                        <form method="POST" action="{{ route('customers.notifications.read', ['customer' => request()->route('customer'), 'notification' => $notification->id]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-sm font-medium hover:underline" style="color: {{ themeColor(700) }}">Marcar le&iacute;da</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="p-8 text-center text-gray-500">A&uacute;n no tienes notificaciones de citas.</p>
            @endforelse
        </div>

        <div>{{ $notifications->links() }}</div>
    </div>
@endsection
