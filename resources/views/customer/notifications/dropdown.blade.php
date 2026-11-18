            @forelse ($tenantNotifications as $notification)
                @php
                    $notificationData = $notification->data;
                @endphp
                <div class="border-b border-gray-100 px-4 py-3 {{ $notification->unread() ? 'bg-blue-50' : 'bg-white' }}">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid {{ ($notificationData['event'] ?? '') === 'cancelled' ? 'fa-calendar-xmark' : 'fa-calendar-check' }} mt-1" style="color: {{ themeColor(600) }}"></i>
                        <a href="{{ route('customers.notifications.open', ['customer' => request()->route('customer'), 'notification' => $notification->id]) }}" class="min-w-0 flex-1 hover:underline">
                            <p class="text-sm font-semibold text-gray-800">{{ $notificationData['title'] ?? 'Cita médica' }}</p>
                            <p class="mt-0.5 truncate text-xs text-gray-600">{{ $notificationData['patient_name'] ?? 'Paciente' }} · {{ !empty($notificationData['starts_at']) ? \Illuminate\Support\Carbon::parse($notificationData['starts_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $notificationData['message'] ?? '' }}</p>
                        </a>
                        @if ($notification->unread())<span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-600" title="No leída"></span>@endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-500">No tienes notificaciones del sistema.</p>
            @endforelse
