<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AppointmentNotificationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $query = $request->user()->notifications()->where('data->customer_id', (int) app('customer')->id);
        return response()->json([
            'unread' => (clone $query)->whereNull('read_at')->count(),
            'html' => view('customer.notifications.dropdown', ['tenantNotifications' => $query->latest()->take(6)->get()])->render(),
        ])->header('Cache-Control', 'private, no-store');
    }
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

    public function markRead(Request $request, string $customer, string $notification): RedirectResponse|JsonResponse
    {
        $record = DB::table('notifications')->where('id', $notification)->first();
        if (! $record) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No se encontró la notificación solicitada.'], 404);
            }

            abort(404, 'No se encontró la notificación solicitada.');
        }

        $user = $request->user();
        $data = is_array($record->data) ? $record->data : (json_decode($record->data ?? '', true) ?: []);
        abort_unless(
            $record->notifiable_type === $user->getMorphClass()
                && (string) $record->notifiable_id === (string) $user->getAuthIdentifier()
                && (int) ($data['customer_id'] ?? 0) === (int) app('customer')->id,
            404
        );

        if (! $record->read_at) {
            DB::table('notifications')->where('id', $notification)->whereNull('read_at')->update([
                'read_at' => now(),
            ]);
            \App\Support\UserRealtime::notify((int) app('customer')->id, (int) $user->id, 'system');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Notificación marcada como leída.']);
        }

        return back()->with('success', 'Notificación marcada como leída.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('data->customer_id', (int) app('customer')->id)
            ->update(['read_at' => now()]);
        \App\Support\UserRealtime::notify((int) app('customer')->id, (int) $request->user()->id, 'system');

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }

    public function open(Request $request, string $customer, string $notification): RedirectResponse
    {
        // The tenant middleware resolves the active clinic. The appointment itself is
        // scoped to that clinic below, so a serialized customer_id comparison is not
        // needed here.
        $record = $request->user()->notifications()->whereKey($notification)->first();
        $customerId = (int) app('customer')->id;
        if (! $record) {
            return redirect()->route('customers.notifications.index', [
                'customer' => $request->route('customer'),
            ])->with('info', 'Esta notificación no está disponible para esta clínica.');
        }

        $isRecipient = $record->notifiable_type === $request->user()->getMorphClass()
            && (string) $record->notifiable_id === (string) $request->user()->getAuthIdentifier()
            && (int) ($record->data['customer_id'] ?? 0) === $customerId;
        if (! $isRecipient) {
            return redirect()->route('customers.notifications.index', [
                'customer' => $request->route('customer'),
            ])->with('info', 'Esta notificaciÃ³n no pertenece a tu usuario.');
        }

        if ($record->unread()) {
            $record->markAsRead();
            \App\Support\UserRealtime::notify($customerId, (int) $request->user()->id, 'system');
        }

        $appointmentId = $record->data['appointment_id'] ?? null;
        if (!$appointmentId) {
            return redirect()->route('customers.notifications.index', [
                'customer' => $request->route('customer'),
            ])->with('info', 'Esta notificación no tiene una cita asociada para abrir.');
        }

        $appointment = Appointment::query()
            ->where('customer_id', $customerId)
            ->find($appointmentId);

        if (!$appointment) {
            return redirect()->route('customers.appointments.index', [
                'customer' => $request->route('customer'),
            ])->with('info', 'La cita asociada a esta notificación ya no está disponible.');
        }

        return redirect()->route('customers.appointments.index', [
            'customer' => $request->route('customer'),
            'appointment_id' => $appointment->id,
            'professional_id' => $appointment->professional_id,
            'date' => \Illuminate\Support\Carbon::parse($appointment->start_at)
                ->timezone(config('app.timezone'))
                ->format('Y-m-d'),
        ]);
    }

}
