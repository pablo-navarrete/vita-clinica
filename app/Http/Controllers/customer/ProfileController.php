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
        $roles = $activeBranch
            ? $user->clinicRolesForBranch((int) app('customer')->id, (int) $activeBranch->id)
                ->orderBy('roles.name')->get(['roles.id', 'roles.name'])
            : collect();
        $isClinicAdministrator = $roles->contains(fn ($role) => in_array(
            mb_strtolower(trim($role->name)), ['administrador', 'administrador de sede'], true
        ));
        $roleGroups = $user->branches()
            ->where('clinic_branches.customer_id', (int) app('customer')->id)
            ->when(! $isClinicAdministrator, fn ($query) => $query->where('clinic_branches.id', $activeBranch?->id ?? 0))
            ->orderBy('clinic_branches.name')
            ->get(['clinic_branches.id', 'clinic_branches.name', 'clinic_branches.status'])
            ->map(fn ($branch) => [
                'branch' => $branch,
                'roles' => $user->clinicRolesForBranch((int) app('customer')->id, (int) $branch->id)
                    ->orderBy('roles.name')->get(['roles.id', 'roles.name']),
            ]);

        $signatureService = app(\App\Services\Signatures\ProfessionalSignatureService::class);
        $specialtiesByBranch = $isClinicAdministrator
            ? $user->specialties()->where('specialties.customer_id', app('customer')->id)
                ->wherePivot('customer_id', app('customer')->id)
                ->wherePivotIn('clinic_branch_id', $roleGroups->pluck('branch.id')->all())
                ->orderBy('specialties.name')->get()->groupBy(fn ($specialty) => (int) $specialty->pivot->clinic_branch_id)
            : collect();
        $canManageSignature = $signatureService->canManage($user, (int) app('customer')->id);

        return view('customer.user.profile', [
            'user' => $user,
            'identificationForm' => app(\App\Services\Identification\IdentificationForms::class)->form($user, $request->user()),
            'roles' => $roles,
            'roleGroups' => $roleGroups,
            'specialtyGroups' => $isClinicAdministrator ? $roleGroups->map(fn ($group) => [
                'branch' => $group['branch'],
                'specialties' => $specialtiesByBranch->get((int) $group['branch']->id, collect()),
            ]) : collect(),
            'activeBranch' => $activeBranch,
            'specialties' => $specialties,
            'isDoctor' => $roles->contains('name', 'Doctor'),
            'isClinicAdministrator' => $isClinicAdministrator,
            'canManageOwnAssignments' => $activeBranch && $isClinicAdministrator
                && ClinicBranchContext::hasPermissionInBranch($user, (int) app('customer')->id, (int) $activeBranch->id, 'roles.manage'),
            'assignmentRoles' => \App\Models\Role::where('customer_id', app('customer')->id)->orderBy('name')->get(['id', 'name']),
            'assignmentBranches' => $roleGroups->filter(fn ($group) => $activeBranch &&
                (ClinicBranchContext::canAdministerAllBranches($user, (int) app('customer')->id)
                    || (int) $group['branch']->id === (int) $activeBranch->id))->values(),
            'canManageSignature' => $canManageSignature,
            'signatureProfile' => $canManageSignature ? $signatureService->profile($user, (int) app('customer')->id) : null,
            'signatureMethods' => app(\App\Services\Signatures\SignatureMethodRegistry::class)->available(),
            'signatureSpecialties' => $canManageSignature ? $user->specialties()->wherePivot('customer_id', app('customer')->id)
                ->where('specialties.customer_id', app('customer')->id)->get()->unique('id') : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentProfessional($request);
        $customerId = $user->customer_id;
        $activeBranch = ClinicBranchContext::defaultBranch($user, (int) app('customer')->id);
        $isClinicAdministrator = $activeBranch && $user->clinicRolesForBranch((int) app('customer')->id, (int) $activeBranch->id)
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
        if (! $isClinicAdministrator) {
            foreach (['name', 'email', 'phone', 'rut'] as $field) {
                $rules[$field] = ['prohibited'];
            }
            foreach (['enabled', 'country', 'type', 'holder', 'number', 'expected_primary'] as $field) {
                $rules['identification_'.$field] = ['prohibited'];
            }
        }
        $validated = $request->validate($rules);
        $ids = app(\App\Services\Identification\IdentificationForms::class);
        $document = $isClinicAdministrator ? $ids->parse($request, false, $user->rut, false) : null;

        DB::transaction(function () use ($user, $validated, $isClinicAdministrator, $ids, $document, $request) {
            DB::table('customers')->where('id', $user->customer_id)->lockForUpdate()->first();
            $user->refresh();
            $data = $isClinicAdministrator ? [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ] : [];

            if (filled($validated['password'] ?? null)) {
                $data['password'] = Hash::make($validated['password']);
            }
            if ($isClinicAdministrator && $document) {
                $data['rut'] = $ids->legacyValue($document, $user->rut);
            }

            $user->update($data);
            $ids->save($user, $request->user(), $document, $request);
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
