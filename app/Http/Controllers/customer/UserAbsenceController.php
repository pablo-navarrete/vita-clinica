<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAbsence;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAbsenceController extends Controller
{
    public function index(Request $request, string $customer, string $userId)
    {
        $customerId = (int) app('customer')->id;
        $user = User::query()->where('customer_id', $customerId)->findOrFail($userId);

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name],
            'absences' => UserAbsence::query()->where('customer_id', $customerId)
                ->where('user_id', $user->id)->orderByDesc('starts_on')->get()
                ->map(fn (UserAbsence $absence) => [
                    'id' => $absence->id,
                    'type' => $absence->type,
                    'starts_on' => $absence->starts_on->format('Y-m-d'),
                    'ends_on' => $absence->ends_on->format('Y-m-d'),
                    'notes' => $absence->notes,
                ]),
        ]);
    }

    public function store(Request $request, string $customer, string $userId)
    {
        $customerId = (int) app('customer')->id;
        $user = User::query()->where('customer_id', $customerId)->findOrFail($userId);
        $data = $request->validate([
            'type' => ['required', Rule::in(['vacaciones', 'permiso', 'otro'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $doctor = Role::query()->where('customer_id', $customerId)->whereRaw('LOWER(TRIM(name)) = ?', ['doctor'])
            ->whereHas('users', fn ($query) => $query->whereKey($user->id))->exists();
        $conflictingAppointments = 0;
        if ($doctor) {
            $conflictingAppointments = Appointment::withoutGlobalScope('active_clinic_branch')
                ->where('customer_id', $customerId)->where('professional_id', $user->id)
                ->whereNotIn('status', [4, 5])
                ->whereDate('start_at', '<=', $data['ends_on'])
                ->whereDate('end_at', '>=', $data['starts_on'])
                ->count();
        }

        $absence = UserAbsence::query()->create([
            ...$data,
            'customer_id' => $customerId,
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'absence' => $absence,
            'blocks_appointments' => $doctor,
            'conflicting_appointments' => $conflictingAppointments,
        ], 201);
    }

    public function destroy(string $customer, string $userId, string $absenceId)
    {
        $customerId = (int) app('customer')->id;
        User::query()->where('customer_id', $customerId)->findOrFail($userId);
        $absence = UserAbsence::query()->where('customer_id', $customerId)
            ->where('user_id', $userId)->findOrFail($absenceId);
        $absence->delete();

        return response()->json(['success' => true]);
    }
}
