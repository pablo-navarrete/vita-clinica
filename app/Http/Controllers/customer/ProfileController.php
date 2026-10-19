<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->currentProfessional($request);

        return view('customer.user.profile', [
            'user' => $user,
            'isDoctor' => $user->roles()->where('roles.name', 'Doctor')->exists(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentProfessional($request);
        $customerId = $user->customer_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $customerId === null
                        ? $query->whereNull('customer_id')
                        : $query->where('customer_id', $customerId))
                    ->ignore($user->id),
            ],
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $data = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ];

            if (filled($validated['password'] ?? null)) {
                $data['password'] = Hash::make($validated['password']);
            }

            $user->update($data);
        });

        return redirect()
            ->route('customers.profile.index', ['customer' => $request->route('customer')])
            ->with('success', 'Tu perfil se actualizó correctamente.');
    }

    private function currentProfessional(Request $request): User
    {
        return $request->user();
    }
}
