<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ClinicBranchContext;
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
        $activeBranch = $user->customer_id
            ? ClinicBranchContext::defaultBranch($user, (int) app('customer')->id)
            : null;
        $specialties = $activeBranch && $user->customer_id
            ? $user->specialtiesForBranch((int) app('customer')->id, (int) $activeBranch->id)->orderBy('specialties.name')->get(['specialties.id', 'specialties.name'])
            : collect();
        $roles = $user->roles()
            ->where('roles.customer_id', app('customer')->id)
            ->orderBy('roles.name')
            ->get(['roles.id', 'roles.name']);
        $roleGroups = $user->branches()
            ->where('clinic_branches.customer_id', (int) app('customer')->id)
            ->orderBy('clinic_branches.name')
            ->get(['clinic_branches.id', 'clinic_branches.name', 'clinic_branches.status'])
            ->map(fn ($branch) => [
                'branch' => $branch,
                'roles' => $user->clinicRolesForBranch((int) app('customer')->id, (int) $branch->id)
                    ->orderBy('roles.name')->get(['roles.id', 'roles.name']),
            ]);
        $isClinicAdministrator = $roles->contains(fn ($role) => in_array(
            mb_strtolower(trim($role->name)),
            ['administrador', 'administrador de sede'],
            true
        ));

        return view('customer.user.profile', [
            'user' => $user,
            'roles' => $roles,
            'roleGroups' => $roleGroups,
            'activeBranch' => $activeBranch,
            'specialties' => $specialties,
            'isDoctor' => $roles->contains('name', 'Doctor'),
            'isClinicAdministrator' => $isClinicAdministrator,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentProfessional($request);
        $customerId = $user->customer_id;
        $isClinicAdministrator = $user->roles()
            ->where('roles.customer_id', $customerId)
            ->whereRaw('LOWER(TRIM(roles.name)) IN (?, ?)', ['administrador', 'administrador de sede'])
            ->exists();

        $rules = [
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
        ];
        if ($isClinicAdministrator) {
            $rules['rut'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'rut')->where('customer_id', $customerId)->ignore($user->id),
            ];
        }
        $validated = $request->validate($rules);

        DB::transaction(function () use ($user, $validated, $isClinicAdministrator) {
            $data = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ];

            if (filled($validated['password'] ?? null)) {
                $data['password'] = Hash::make($validated['password']);
            }
            if ($isClinicAdministrator) {
                $data['rut'] = $validated['rut'];
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
