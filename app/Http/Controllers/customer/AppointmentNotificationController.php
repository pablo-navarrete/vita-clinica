<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class AppointmentNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = (int) app('customer')->id;
        $notifications = $request->user()->notifications()
            ->where('data->customer_id', $customerId)
            ->latest()
            ->paginate(20);
        $unreadCount = $request->user()->unreadNotifications()
            ->where('data->customer_id', $customerId)
            ->count();

        return view('customer.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $this->findForCurrentTenant($request, $notification);
        if ($record->unread()) {
            $record->markAsRead();
        }

        return back()->with('success', 'Notificación marcada como leída.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('data->customer_id', (int) app('customer')->id)
            ->update(['read_at' => now()]);

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $this->findForCurrentTenant($request, $notification);
        if ($record->unread()) {
            $record->markAsRead();
        }

        $appointmentId = $record->data['appointment_id'] ?? null;
        if (!$appointmentId) {
            return redirect()->route('customers.notifications.index', [
                'customer' => $request->route('customer'),
            ]);
        }

        $appointment = Appointment::query()
            ->where('customer_id', (int) app('customer')->id)
            ->find($appointmentId);

        if (!$appointment) {
            return redirect()->route('customers.appointments.index', [
                'customer' => $request->route('customer'),
            ])->with('info', 'La cita asociada a esta notificación ya no está disponible.');
        }

        return redirect()->route('customers.appointments.index', [
            'customer' => $request->route('customer'),
            'appointment_id' => $appointment->id,
            'date' => \Illuminate\Support\Carbon::parse($appointment->start_at)
                ->timezone(config('app.timezone'))
                ->format('Y-m-d'),
        ]);
    }

    private function findForCurrentTenant(Request $request, string $notification): DatabaseNotification
    {
        return $request->user()->notifications()
            ->where('data->customer_id', (int) app('customer')->id)
            ->whereKey($notification)
            ->firstOrFail();
    }
}
