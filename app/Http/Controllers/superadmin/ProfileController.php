<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
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
        return view('superadmin.profile', ['user' => $request->user(), 'identificationForm' => app(\App\Services\Identification\IdentificationForms::class)->form($request->user(), $request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ids = app(\App\Services\Identification\IdentificationForms::class);
        $document = $ids->parse($request, false, $user->rut, false);
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

        DB::transaction(function () use ($user, $validated, $document, $ids, $request) {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->refresh();
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->phone = $validated['phone'] ?? null;

            if (filled($validated['password'] ?? null)) {
                $user->password = Hash::make($validated['password']);
            }

            if ($document) {
                $user->rut = $ids->legacyValue($document, $user->rut);
            }
            $user->save();
            $ids->save($user, $request->user(), $document, $request);
        });

        return redirect()
            ->route('superadmin.profile.index')
            ->with('success', 'Tu perfil se actualizó correctamente.');
    }
}
