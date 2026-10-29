<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('superadmin.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $changingPassword = $request->filled('password');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('customer_id')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'current_password' => $changingPassword
                ? ['required', 'current_password']
                : ['nullable'],
            'password' => $changingPassword
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['nullable'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;

        if (filled($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('superadmin.profile.index')
            ->with('success', 'Tu perfil se actualizó correctamente.');
    }
}
