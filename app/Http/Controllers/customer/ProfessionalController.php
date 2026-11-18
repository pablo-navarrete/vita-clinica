<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicBranch;
use App\Models\Encounter;
use App\Models\Role;
use App\Models\User;
use App\Support\ClinicActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Yajra\DataTables\Facades\DataTables;

class ProfessionalController extends Controller
{
    public function identification(Request $request)
    {
        $user = $this->clinicUsersQuery((int) app('customer')->id)->findOrFail($request->route('id'));

        return response()->json(app(\App\Services\Identification\IdentificationForms::class)->form($user, $request->user()))->header('Cache-Control', 'no-store');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $specialties = app(\App\Services\SpecialtyCatalog::class)->options();
        $customerId = (int) app('customer')->id;
        app(RolePermissionController::class)->ensureClinicRoles($customerId);
        $isSiteAdministrator = $this->actorIsSiteAdministrator($customerId);
        $activeBranchId = $this->activeBranchId($customerId);
        $headquartersBranchId = ClinicBranch::query()->where('customer_id', $customerId)->orderBy('id')->value('id');
        $effectiveBranchId = $activeBranchId ?? \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), $customerId)?->id;
        $activeBranch = $activeBranchId
            ? ClinicBranch::query()->where('customer_id', $customerId)->whereKey($activeBranchId)->first(['id', 'name'])
            : null;
        $branches = ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)
            ->when($isSiteAdministrator, fn ($query) => $query->whereKey($activeBranchId ?? 0))
            ->orderBy('name')->get(['id', 'name', 'address']);
        $roles = Role::query()->where('customer_id', $customerId)
            ->when($isSiteAdministrator, fn ($query) => $query->whereRaw('LOWER(TRIM(name)) != ?', ['administrador']))
            ->orderBy('name')->get(['id', 'name']);
        $isHeadquarters = auth()->user() && \App\Support\ClinicBranchContext::canAdministerAllBranches(auth()->user(), $customerId);
        if ($isHeadquarters) {
            $branches = \App\Support\ClinicBranchContext::centralAdministrationBranches(auth()->user(), $customerId);
        }
        $showScheduleManagement = $effectiveBranchId !== null
            && \App\Support\ClinicBranchContext::canManageSchedulesInBranch(auth()->user(), $customerId, (int) $effectiveBranchId);

        return view('customer.professional.index', compact('specialties', 'roles', 'branches', 'isSiteAdministrator', 'activeBranch', 'showScheduleManagement', 'isHeadquarters', 'activeBranchId', 'effectiveBranchId'));
    }

    // datatable profesional
    public function getProfessional()
    {
        $customerId = (int) app('customer')->id;
        $branchId = $this->activeBranchId($customerId)
            ?? \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), $customerId)?->id;
        $headquartersBranchId = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->value('id');
        $isHeadquarters = \App\Support\ClinicBranchContext::canAdministerAllBranches(auth()->user(), $customerId);
        $allBranches = ClinicBranch::query()->where('customer_id', $customerId)->orderBy('name')->get(['id', 'name', 'status']);
        $visibleBranchIds = \App\Support\ClinicBranchContext::availableBranches(auth()->user(), $customerId)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $centralBranches = \App\Support\ClinicBranchContext::centralAdministrationBranches(auth()->user(), $customerId);
        $tableBranchIds = $isHeadquarters
            ? $centralBranches->pluck('id')->map(fn ($id) => (int) $id)->all()
            : ($branchId ? [(int) $branchId] : []);
        $tableBranchNames = $allBranches->whereIn('id', $tableBranchIds)->pluck('name', 'id');
        $allActiveBranches = $allBranches->where('status', true);
        $allActiveBranchIds = $isHeadquarters
            ? $allActiveBranches->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $allActiveBranches->whereIn('id', $tableBranchIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $query = $this->clinicUsersQuery($customerId)
            ->when(! $isHeadquarters, function ($query) use ($customerId, $branchId) {
                if (! $branchId) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas('branches', fn ($branches) => $branches
                    ->where('clinic_branches.customer_id', $customerId)
                    ->where('clinic_branches.status', true)
                    ->whereKey($branchId));
            })
            ->with(['branchRoles' => fn ($q) => $q->where('roles.customer_id', $customerId)
                ->wherePivot('customer_id', $customerId)
                ->wherePivotIn('clinic_branch_id', $tableBranchIds ?: [0]),
                'specialties' => function ($query) use ($customerId, $tableBranchIds) {
                    $query->where('specialties.customer_id', $customerId)
                        ->wherePivot('customer_id', $customerId)
                        ->wherePivotIn('clinic_branch_id', $tableBranchIds ?: [0]);
                },
                'branches' => fn ($query) => $query->where('clinic_branches.customer_id', $customerId)->where('clinic_branches.status', true)
                    ->when(! $isHeadquarters, fn ($branches) => $branches->whereKey($branchId ?? 0))])
            ->withCount('appointments');

        return DataTables::of($query)
            ->removeColumn('rut')

            ->addColumn('user_id', fn ($user) => (int) $user->getKey())

            ->addColumn('specialties_html', function ($user) use ($branchId) {

                return $user->specialties
                    ->filter(fn ($specialty) => (int) $specialty->pivot->clinic_branch_id === (int) ($branchId ?? 0))
                    ->unique(fn ($specialty) => app(\App\Services\SpecialtyCatalog::class)->selection($specialty))
                    ->map(function ($specialty) {

                        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700 mr-1">
                        '.e(app(\App\Services\SpecialtyCatalog::class)->name($specialty->catalog_id) ?? $specialty->name).'
                    </span>';
                    })
                    ->implode(' ');
            })
            ->addColumn('branch_specialties', fn ($user) => $user->specialties
                ->filter(fn ($specialty) => (int) $specialty->pivot->clinic_branch_id === (int) ($branchId ?? 0))
                ->map(fn ($specialty) => ['id' => app(\App\Services\SpecialtyCatalog::class)->selection($specialty), 'name' => app(\App\Services\SpecialtyCatalog::class)->name($specialty->catalog_id) ?? $specialty->name])->unique('id')->values()->all())
            ->addColumn('specialties_by_branch', function ($user) use ($tableBranchNames) {
                return $user->specialties
                    ->groupBy(fn ($specialty) => (int) $specialty->pivot->clinic_branch_id)
                    ->map(fn ($specialties, $id) => [
                        'branch_id' => (int) $id,
                        'branch_name' => $tableBranchNames->get($id, 'Sede'),
                        'specialties' => $specialties->map(fn ($specialty) => ['id' => app(\App\Services\SpecialtyCatalog::class)->selection($specialty), 'name' => app(\App\Services\SpecialtyCatalog::class)->name($specialty->catalog_id) ?? $specialty->name])->unique('id')->values()->all(),
                    ])->values()->all();
            })

            ->addColumn('roles_html', fn ($user) => $user->branchRoles
                ->filter(fn ($role) => (int) $role->pivot->clinic_branch_id === (int) ($branchId ?? 0))
                ->map(fn ($role) => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-700 mr-1">'.e($role->name).'</span>')
                ->implode(' '))
            ->addColumn('roles', fn ($user) => $user->branchRoles
                ->filter(fn ($role) => (int) $role->pivot->clinic_branch_id === (int) ($branchId ?? 0))
                ->map(fn ($role) => ['id' => (int) $role->id, 'name' => $role->name])->values()->all())
            ->addColumn('roles_by_branch', function ($user) use ($tableBranchNames) {
                return $user->branchRoles
                    ->groupBy(fn ($role) => (int) $role->pivot->clinic_branch_id)
                    ->map(fn ($roles, $id) => [
                        'branch_id' => (int) $id,
                        'branch_name' => $tableBranchNames->get($id, 'Sede'),
                        'roles' => $roles->map(fn ($role) => ['id' => (int) $role->id, 'name' => $role->name])->values()->all(),
                    ])->values()->all();
            })
            ->addColumn('branch_ids', function ($user) use ($allActiveBranchIds) {
                $isGeneralAdministrator = $user->branchRoles->contains(fn ($role) => mb_strtolower(trim($role->name)) === 'administrador');

                return $isGeneralAdministrator
                    ? array_values($allActiveBranchIds)
                    : $user->branches->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            })
            ->addColumn('branches_html', function ($user) {
                $isGeneralAdministrator = $user->branchRoles->contains(fn ($role) => mb_strtolower(trim($role->name)) === 'administrador');
                if ($isGeneralAdministrator) {
                    return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800 mr-1">Todas las sedes</span>';
                }

                return $user->branches
                    ->map(fn ($branch) => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800 mr-1">'.e($branch->name).'</span>')
                    ->implode(' ') ?: '<span class="text-gray-500">Sin sedes</span>';
            })
            ->addColumn('branches_data', fn ($user) => $user->branches
                ->map(fn ($branch) => ['id' => (int) $branch->id, 'name' => $branch->name, 'status' => (int) $branch->pivot->status])
                ->values()->all())
            ->addColumn('branch_status', function ($user) use ($branchId) {
                $branch = $user->branches->firstWhere('id', (int) ($branchId ?? 0));

                return $branch ? (int) $branch->pivot->status : 0;
            })
            ->rawColumns(['specialties_html', 'roles_html', 'branches_html'])

            ->make(true);
    }

    // crear professional
    public function store(Request $request)
    {
        $ids = app(\App\Services\Identification\IdentificationForms::class);
        $document = $ids->parse($request, false, null, true);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rut' => 'nullable|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('customer_id', app('customer')->id);
                }),
            ],
            'password' => 'required|string|min:8',
            'status' => 'nullable|in:0,1',
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', Rule::exists('roles', 'id')->where('customer_id', app('customer')->id)],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['required', 'integer', $this->assignableBranchRule((int) app('customer')->id)],
            'specialties' => 'nullable|array',
            'specialties.*' => ['integer'],
            'specialty_branch_id' => ['nullable', 'integer', $this->assignableBranchRule((int) app('customer')->id)],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'phone.required' => 'El celular es obligatorio.',
            'rut.required' => 'El rut es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Debe ingresar un correo válido.',
            'email.unique' => 'Este correo ya existe para este cliente.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $hasBranches = ClinicBranch::query()->where('customer_id', app('customer')->id)->where('status', true)->exists();
            $isGeneralAdmin = Role::query()->where('customer_id', app('customer')->id)
                ->whereIn('id', $request->input('role_ids', []))
                ->whereRaw('LOWER(TRIM(name)) = ?', ['administrador'])->exists();
            if ($isGeneralAdmin && ! $this->actorIsGeneralAdministrator((int) app('customer')->id)) {
                $validator->errors()->add('role_ids', 'Solo el Administrador general puede asignar ese rol.');
            }
            if ($hasBranches && ! $isGeneralAdmin && empty($request->input('branch_ids', []))) {
                $validator->errors()->add('branch_ids', 'Asigna al usuario al menos una sede para permitirle iniciar sesión.');
            }
            $doctorSelected = Role::query()->where('customer_id', app('customer')->id)->where('name', 'Doctor')
                ->whereIn('id', $request->input('role_ids', []))->exists();
            if ($doctorSelected || $request->filled('specialties')) {
                if (empty($request->input('specialties', []))) {
                    $validator->errors()->add('specialties', 'Selecciona al menos una especialidad para el rol Doctor.');
                }
                $effectiveBranchIds = $isGeneralAdmin
                    ? ClinicBranch::query()->where('customer_id', app('customer')->id)->where('status', true)->pluck('id')->map(fn ($id) => (int) $id)->all()
                    : array_map('intval', $request->input('branch_ids', []));
                if (! in_array((int) $request->input('specialty_branch_id'), $effectiveBranchIds, true)) {
                    $validator->errors()->add('specialty_branch_id', 'Elige una sede asignada al usuario para esta especialidad.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = DB::transaction(function () use ($request, $document, $ids) {
            DB::table('customers')->where('id', app('customer')->id)->lockForUpdate()->first();

            $user = User::create([
                'customer_id' => app('customer')->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'status' => $request->status,
                'rut' => $ids->legacyValue($document),
                'phone' => $request->phone,
            ]);

            $allBranchIds = ClinicBranch::query()->where('customer_id', app('customer')->id)->where('status', true)->pluck('id')->all();
            $roleIds = array_map('intval', $request->role_ids);
            $hasGeneralAdminRole = Role::query()->where('customer_id', app('customer')->id)->whereIn('id', $roleIds)
                ->whereRaw('LOWER(TRIM(name)) = ?', ['administrador'])->exists();
            $branchIds = $hasGeneralAdminRole ? $allBranchIds : array_map('intval', $request->input('branch_ids', []));
            $user->branches()->sync($branchIds);
            foreach ($branchIds as $branchId) {
                $this->syncBranchRoles($user, (int) app('customer')->id, (int) $branchId, $roleIds);
            }
            $user->syncClinicRoleMirror((int) app('customer')->id);
            if ($request->filled('specialty_branch_id')) {
                $this->syncUserSpecialties($user, (int) app('customer')->id, (int) $request->input('specialty_branch_id'), $request->input('specialties', []));
            }
            $ids->save($user, $request->user(), $document, $request, '', true);

            return $user;
        });

        $createdSpecialties = $user->specialties()->where('specialties.customer_id', app('customer')->id)->pluck('specialties.name')->unique()->all();
        if ($createdSpecialties) {
            ClinicActivityLogger::recordManual(
                (int) app('customer')->id,
                'Usuarios',
                'updated',
                'usuario',
                $user->name,
                'Asignó especialidades al profesional «'.$user->name.'».',
                $createdSpecialties,
                User::class,
                $user->id
            );
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    // Actualizar profesional

    public function updateBranches(Request $request, $id)
    {
        $id = filter_var($request->route('id'), FILTER_VALIDATE_INT);
        if (! $id || $id < 1) {
            return response()->json(['message' => 'El identificador del usuario no es válido.'], 422);
        }

        $customerId = (int) app('customer')->id;
        $headquartersBranchId = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->value('id');
        $effectiveBranchId = $this->activeBranchId($customerId)
            ?? \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), $customerId)?->id;
        if (! $effectiveBranchId
            || ! \App\Support\ClinicBranchContext::canAdministerAllBranches(auth()->user(), $customerId)
            || ! \App\Support\ClinicBranchContext::hasPermissionInBranch(auth()->user(), $customerId, (int) $effectiveBranchId, 'professionals.manage')) {
            return response()->json(['message' => 'Solo se pueden modificar las sedes desde la sede principal.'], 403);
        }

        $user = $this->clinicUsersQuery($customerId)->find($id);
        if (! $user) {
            return response()->json(['message' => 'No se encontró el usuario en esta clínica.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['required', 'integer', $this->assignableBranchRule($customerId)],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $isGeneralAdministrator = $user->roles()
            ->where('roles.customer_id', $customerId)
            ->whereRaw('LOWER(TRIM(roles.name)) = ?', ['administrador'])
            ->exists();
        $activeBranchIds = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->pluck('id')->map(fn ($branchId) => (int) $branchId)->all();

        if ($isGeneralAdministrator) {
            $branchIds = $activeBranchIds;
        } else {
            $branchIds = array_map('intval', $request->input('branch_ids', []));
            if ($this->actorIsSiteAdministrator($customerId)) {
                $activeBranchId = $this->activeBranchId($customerId);
                $otherBranchIds = $user->branches()
                    ->where('clinic_branches.id', '!=', $activeBranchId ?? 0)
                    ->pluck('clinic_branches.id')->map(fn ($branchId) => (int) $branchId)->all();
                $branchIds = array_values(array_unique(array_merge($otherBranchIds, $branchIds)));
            }

            $hasActiveAssignment = array_intersect($branchIds, $activeBranchIds) !== [];
            if ($activeBranchIds && ! $hasActiveAssignment) {
                return response()->json([
                    'errors' => ['branch_ids' => ['Asigna al usuario al menos una sede activa para permitirle iniciar sesión.']],
                ], 422);
            }
        }

        $existingBranchIds = $user->branches()->pluck('clinic_branches.id')->map(fn ($value) => (int) $value)->all();
        $roleIdsByBranch = [];
        foreach ($existingBranchIds as $existingBranchId) {
            $roleIdsByBranch[$existingBranchId] = $user->clinicRolesForBranch($customerId, $existingBranchId)->pluck('roles.id')->map(fn ($value) => (int) $value)->all();
        }
        $user->branches()->sync($branchIds);
        DB::table('clinic_branch_user_role')->where('customer_id', $customerId)->where('user_id', $user->id)
            ->whereNotIn('clinic_branch_id', $branchIds ?: [0])->delete();
        foreach (array_diff($branchIds, $existingBranchIds) as $newBranchId) {
            $seedRoles = $roleIdsByBranch[$this->activeBranchId($customerId)] ?? reset($roleIdsByBranch) ?: [];
            if ($seedRoles) {
                $this->syncBranchRoles($user, $customerId, (int) $newBranchId, $seedRoles);
            }
        }
        $user->syncClinicRoleMirror($customerId);

        return response()->json([
            'success' => true,
            'branch_ids' => $user->branches()
                ->where('clinic_branches.status', true)
                ->pluck('clinic_branches.id')->map(fn ($branchId) => (int) $branchId)->values(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $id = $request->route('id');

        $user = $this->clinicUsersQuery((int) app('customer')->id)
            ->where('id', $id)
            ->firstOrFail();
        $ids = app(\App\Services\Identification\IdentificationForms::class);
        $document = $ids->parse($request, false, $user->rut, false);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rut' => 'nullable|string|max:255',
            'phone' => 'required|string|max:255',

            'email' => [
                'required',
                'email',
                Rule::unique('users')
                    ->where(function ($query) {
                        return $query->where(
                            'customer_id',
                            app('customer')->id
                        );
                    })
                    ->ignore($user->id),
            ],

            'status' => 'required|in:0,1',
            'password' => 'nullable|string|min:8',
            'specialties' => 'nullable|array',
            'specialties.*' => ['integer'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'phone.required' => 'El celular es obligatorio.',
            'rut.required' => 'El rut es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Debe ingresar un correo válido.',
            'email.unique' => 'Este correo ya existe para este cliente.',
            'specialties.array' => 'Las especialidades no son válidas.',
            'specialties.*.exists' => 'Una de las especialidades seleccionadas no existe.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $customerId = (int) app('customer')->id;
        $branchId = $this->activeBranchId($customerId) ?? \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), $customerId)?->id;
        $previousSpecialties = $branchId ? $user->specialtiesForBranch($customerId, (int) $branchId)
            ->orderBy('specialties.name')
            ->pluck('specialties.name')->all() : [];

        DB::transaction(function () use ($request, $user, $customerId, $branchId, $document, $ids) {
            DB::table('customers')->where('id', $customerId)->lockForUpdate()->first();
            $user->refresh();

            // Actualizar datos del profesional
            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'status' => $request->status,
                ...($document ? ['rut' => $ids->legacyValue($document, $user->rut)] : []),
                'phone' => $request->phone,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);
            $ids->save($user, $request->user(), $document, $request);
            if ($branchId) {
                if ($request->exists('specialties')) {
                    $this->syncUserSpecialties($user, $customerId, (int) $branchId, $request->input('specialties', []));
                }
            }
        });

        $currentSpecialties = $branchId ? $user->specialtiesForBranch($customerId, (int) $branchId)
            ->orderBy('specialties.name')
            ->pluck('specialties.name')->all() : [];
        if ($previousSpecialties !== $currentSpecialties) {
            ClinicActivityLogger::recordManual(
                (int) app('customer')->id,
                'Usuarios',
                'updated',
                'usuario',
                $user->name,
                'Actualizó las especialidades del profesional «'.$user->name.'».',
                ['Antes: '.($previousSpecialties ? implode(', ', $previousSpecialties) : 'ninguna'), 'Ahora: '.($currentSpecialties ? implode(', ', $currentSpecialties) : 'ninguna')],
                User::class,
                $user->id
            );
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($user->toArray(), ['specialties' => $branchId ? $user->specialtiesForBranch($customerId, (int) $branchId)->get(['specialties.id', 'specialties.name', 'specialties.catalog_id']) : []]),
        ]);
    }

    // cambiar estado
    public function toggleStatus(Request $request)
    {
        $id = $request->route('id');

        $customerId = (int) app('customer')->id;
        $professional = $this->clinicUsersQuery($customerId)->where('id', $id)->firstOrFail();
        $data = $request->validate([
            'status' => ['required', 'in:0,1'],
            'branch_id' => ['required', 'integer'],
        ]);
        $branchId = (int) $data['branch_id'];
        $activeBranchIds = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            ? \App\Support\ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all()
            : \App\Support\ClinicBranchContext::availableBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all();
        abort_unless(in_array($branchId, $activeBranchIds, true), 403, 'No tienes acceso a esta sede.');
        abort_unless($professional->branches()->whereKey($branchId)->exists(), 422, 'El usuario no está asignado a esta sede.');
        abort_unless($this->canManageRolesInBranch($request->user(), $customerId, $branchId), 403, 'No tienes permiso para administrar usuarios en esta sede.');

        if ((int) $data['status'] === 0) {
            $hasFutureAppointments = Appointment::withoutGlobalScope('active_clinic_branch')
                ->where('customer_id', $customerId)
                ->where('professional_id', $professional->id)
                ->where('clinic_branch_id', $branchId)
                ->where('start_at', '>=', now())
                ->whereIn('status', [0, 1, 2])
                ->exists();

            $hasOpenEncounter = Encounter::query()
                ->where('customer_id', $customerId)
                ->where('status', 1)
                ->where(function ($query) use ($professional) {
                    $query->where('professional_id', $professional->id)
                        ->orWhere(function ($legacyQuery) use ($professional) {
                            $legacyQuery->whereNull('professional_id')
                                ->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery
                                    ->where('professional_id', $professional->id));
                        });
                })
                ->where(function ($query) use ($branchId) {
                    $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery
                        ->where('clinic_branch_id', $branchId))
                        ->orWhere(function ($unbookedQuery) use ($branchId) {
                            $unbookedQuery->whereNull('appointment_id')
                                ->whereHas('patient', fn ($patientQuery) => $patientQuery
                                    ->where('clinic_branch_id', $branchId));
                        });
                })
                ->exists();

            if ($hasFutureAppointments || $hasOpenEncounter) {
                return response()->json([
                    'message' => 'No se puede desactivar este usuario en esta sede porque tiene citas futuras o una atención en curso. Reasigna o finaliza la atención y vuelve a intentarlo.',
                ], 422);
            }
        }

        DB::table('clinic_branch_user')->where('user_id', $professional->id)
            ->where('clinic_branch_id', $branchId)
            ->update(['status' => (int) $data['status'], 'updated_at' => now()]);

        $hasActiveBranch = DB::table('clinic_branch_user')
            ->where('user_id', $professional->id)
            ->where('status', 1)
            ->exists();
        $professional->update(['status' => $hasActiveBranch ? 1 : 0]);

        return response()->json(['success' => true, 'status' => (int) $data['status'], 'branch_id' => $branchId]);
    }

    public function updateRoles(Request $request, $customer, $id)
    {
        // En rutas por subdominio Laravel también entrega el parámetro {customer};
        // el ID del usuario debe leerse del segmento {id}, no del argumento posicional.
        $id = $request->route('id');
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (! $id || $id < 1) {
            return response()->json(['message' => 'El identificador del usuario no es válido. Recarga la tabla e inténtalo nuevamente.'], 422);
        }
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUsersQuery($customerId)->find($id);
        if (! $user) {
            return response()->json(['message' => 'No se encontró el usuario en esta clínica. Recarga la tabla e inténtalo nuevamente.'], 404);
        }
        $data = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', Rule::exists('roles', 'id')->where('customer_id', $customerId)],
        ]);
        $branchId = filter_var($request->input('branch_id'), FILTER_VALIDATE_INT);
        $allowedBranchIds = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            ? \App\Support\ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all()
            : \App\Support\ClinicBranchContext::availableBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all();
        $headquartersBranchId = ClinicBranch::query()->where('customer_id', $customerId)->orderBy('id')->value('id');
        $effectiveBranchId = $this->activeBranchId($customerId)
            ?? \App\Support\ClinicBranchContext::defaultBranch($request->user(), $customerId)?->id;
        $isHeadquarters = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId);
        if (! $isHeadquarters) {
            $allowedBranchIds = $effectiveBranchId ? [(int) $effectiveBranchId] : [];
        }
        if (! $branchId || ! in_array((int) $branchId, $allowedBranchIds, true) || ! $user->branches()->whereKey($branchId)->exists()) {
            return response()->json(['message' => 'Selecciona una sede asignada al usuario y disponible para tu cuenta.'], 422);
        }
        abort_unless($this->canManageRolesInBranch($request->user(), $customerId, (int) $branchId), 403, 'No tienes permiso para administrar roles en esta sede.');
        $assignsGeneralAdministrator = Role::query()->where('customer_id', $customerId)
            ->whereIn('id', $data['role_ids'])
            ->whereRaw('LOWER(TRIM(name)) = ?', ['administrador'])->exists();
        $actor = $request->user();
        $isSuperAdmin = $actor->roles()->whereNull('roles.customer_id')->where('roles.name', 'Super Administrador')->exists();
        if ($assignsGeneralAdministrator && ! $isSuperAdmin && ! $actor->hasClinicRole('Administrador', $customerId, (int) $branchId)) {
            return response()->json(['message' => 'Solo el Administrador general puede asignar ese rol.'], 403);
        }
        DB::transaction(function () use ($data, $customerId, $user, $branchId) {
            DB::table('customers')->where('id', $customerId)->lockForUpdate()->first();
            $roleIds = array_map('intval', array_unique($data['role_ids']));
            $generalAdminRoleId = Role::query()->where('customer_id', $customerId)
                ->whereRaw('LOWER(TRIM(name)) = ?', ['administrador'])->value('id');
            $assignsGeneralAdministrator = $generalAdminRoleId && in_array((int) $generalAdminRoleId, $roleIds, true);
            $targetBranchIds = $assignsGeneralAdministrator
                ? $user->branches()->where('clinic_branches.status', true)->pluck('clinic_branches.id')->map(fn ($id) => (int) $id)->all()
                : [(int) $branchId];
            foreach ($targetBranchIds as $targetBranchId) {
                $branchRoleIds = $targetBranchId === (int) $branchId || ! $assignsGeneralAdministrator
                    ? $roleIds
                    : array_values(array_unique(array_merge(
                        $user->clinicRolesForBranch($customerId, $targetBranchId)->pluck('roles.id')->map(fn ($id) => (int) $id)->all(),
                        [(int) $generalAdminRoleId]
                    )));
                $this->syncBranchRoles($user, $customerId, $targetBranchId, $branchRoleIds);
            }
            $user->syncClinicRoleMirror($customerId);

        });

        return response()->json(['success' => true, 'branch_id' => (int) $branchId, 'roles' => $user->clinicRolesForBranch($customerId, (int) $branchId)->get(['roles.id', 'roles.name'])]);
    }

    public function getUserRoles(Request $request, $customer, $id)
    {
        $customerId = (int) app('customer')->id;
        $userId = filter_var($request->route('id'), FILTER_VALIDATE_INT);
        $branchId = filter_var($request->query('branch_id'), FILTER_VALIDATE_INT);
        $user = $userId ? $this->clinicUsersQuery($customerId)->find($userId) : null;
        abort_unless($user, 404);

        $allowedBranchIds = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            ? \App\Support\ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all()
            : \App\Support\ClinicBranchContext::availableBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all();
        abort_unless($branchId && in_array((int) $branchId, $allowedBranchIds, true) && $user->branches()->whereKey($branchId)->exists(), 403);
        abort_unless($this->canManageRolesInBranch($request->user(), $customerId, (int) $branchId), 403, 'No tienes permiso para administrar roles en esta sede.');

        $visibleBranches = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->whereIn('clinic_branches.id', $allowedBranchIds)
            ->whereIn('clinic_branches.id', $user->branches()->select('clinic_branches.id'))
            ->orderBy('clinic_branches.name')->get(['clinic_branches.id', 'clinic_branches.name']);
        $manageableBranches = $visibleBranches->filter(fn ($branch) => $this->canManageRolesInBranch($request->user(), $customerId, (int) $branch->id)
        )->values();
        $manageableBranchIds = $manageableBranches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $assignedRoles = $user->branchRoles()
            ->wherePivot('customer_id', $customerId)
            ->wherePivotIn('clinic_branch_id', $manageableBranchIds ?: [0])
            ->orderBy('roles.name')->get(['roles.id', 'roles.name']);
        $groups = $manageableBranches->map(fn ($branch) => [
            'branch_id' => (int) $branch->id,
            'branch_name' => $branch->name,
            'roles' => $assignedRoles->filter(fn ($role) => (int) $role->pivot->clinic_branch_id === (int) $branch->id)
                ->map(fn ($role) => ['id' => (int) $role->id, 'name' => $role->name])->values()->all(),
        ])->all();

        return response()->json([
            'roles' => $user->clinicRolesForBranch($customerId, (int) $branchId)->get(['roles.id', 'roles.name']),
            'groups' => $groups,
        ]);
    }

    public function getUserSpecialties(Request $request, $customer, $id)
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUsersQuery($customerId)->find((int) $request->route('id'));
        abort_unless($user, 404);
        $branchId = (int) $request->query('branch_id');
        $allowedBranchIds = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            ? \App\Support\ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all()
            : \App\Support\ClinicBranchContext::availableBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all();
        $headquartersBranchId = ClinicBranch::query()->where('customer_id', $customerId)->orderBy('id')->value('id');
        $effectiveBranchId = $this->activeBranchId($customerId)
            ?? \App\Support\ClinicBranchContext::defaultBranch($request->user(), $customerId)?->id;
        $isHeadquarters = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId);
        if (! $isHeadquarters) {
            $allowedBranchIds = $effectiveBranchId ? [(int) $effectiveBranchId] : [];
        }
        abort_unless($branchId && in_array($branchId, $allowedBranchIds, true) && $user->branches()->whereKey($branchId)->exists(), 403);
        abort_unless($this->canManageRolesInBranch($request->user(), $customerId, $branchId), 403, 'No tienes permiso para administrar especialidades en esta sede.');

        $visibleBranches = ClinicBranch::query()->where('customer_id', $customerId)->whereIn('id', $allowedBranchIds)
            ->whereIn('id', $user->branches()->select('clinic_branches.id'))->orderBy('name')->get(['id', 'name']);
        $manageableBranches = $visibleBranches->filter(fn ($branch) => $this->canManageRolesInBranch($request->user(), $customerId, (int) $branch->id))->values();
        $canAssign = true; // Assignment does not grant clinical roles or permissions.
        $groups = $manageableBranches->map(fn ($branch) => [
            'branch_id' => (int) $branch->id,
            'branch_name' => $branch->name,
            'can_assign' => true,
            'specialties' => $user->specialtiesForBranch($customerId, (int) $branch->id)->orderBy('specialties.name')
                ->get(['specialties.id', 'specialties.name', 'specialties.catalog_id'])->map(fn ($specialty) => ['id' => app(\App\Services\SpecialtyCatalog::class)->selection($specialty), 'name' => app(\App\Services\SpecialtyCatalog::class)->name($specialty->catalog_id) ?? $specialty->name])->unique('id')->values()->all(),
        ])->all();

        return response()->json([
            'specialties' => app(\App\Services\SpecialtyCatalog::class)->options($user, $customerId, $branchId),
            'assigned' => $user->specialtiesForBranch($customerId, $branchId)->get()->map(fn ($s) => app(\App\Services\SpecialtyCatalog::class)->selection($s))->unique()->values(),
            'can_assign' => $canAssign,
            'groups' => $groups,
        ]);
    }

    public function updateUserSpecialties(Request $request, $customer, $id)
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUsersQuery($customerId)->find((int) $request->route('id'));
        abort_unless($user, 404);
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'specialty_ids' => ['present', 'array'],
            'specialty_ids.*' => ['integer'],
        ]);
        $branchId = (int) $data['branch_id'];
        $allowedBranchIds = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            ? \App\Support\ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all()
            : \App\Support\ClinicBranchContext::availableBranches($request->user(), $customerId)->pluck('id')->map(fn ($value) => (int) $value)->all();
        $headquartersBranchId = ClinicBranch::query()->where('customer_id', $customerId)->orderBy('id')->value('id');
        $effectiveBranchId = $this->activeBranchId($customerId)
            ?? \App\Support\ClinicBranchContext::defaultBranch($request->user(), $customerId)?->id;
        $isHeadquarters = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId);
        if (! $isHeadquarters) {
            $allowedBranchIds = $effectiveBranchId ? [(int) $effectiveBranchId] : [];
        }
        abort_unless(in_array($branchId, $allowedBranchIds, true) && $user->branches()->whereKey($branchId)->exists(), 403);
        abort_unless($this->canManageRolesInBranch($request->user(), $customerId, $branchId), 403, 'No tienes permiso para administrar especialidades en esta sede.');
        $this->syncUserSpecialties($user, $customerId, $branchId, $data['specialty_ids']);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $id = $request->route('id');
        $professional = $this->clinicUsersQuery((int) app('customer')->id)
            ->where('id', $id)
            ->firstOrFail();

        $isClinicAdministrator = $professional->roles()
            ->where('roles.customer_id', app('customer')->id)
            ->whereRaw('LOWER(TRIM(roles.name)) = ?', ['administrador'])
            ->exists();

        if ((int) $professional->id === (int) auth()->id() || $isClinicAdministrator) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta ni la cuenta administradora de la clínica.'], 422);
        }

        // Verificar si tiene citas
        if ($professional->appointments()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el usuario porque tiene citas registradas.',
            ], 422);
        }

        // Eliminar especialidades asociadas
        $professional->specialties()->detach();

        // Eliminar usuario
        $professional->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    private function clinicUsersQuery(int $customerId): Builder
    {
        $query = User::query()->where('customer_id', $customerId);
        if ($this->actorIsSiteAdministrator($customerId)) {
            $branchId = $this->activeBranchId($customerId);
            $query->whereDoesntHave('branchRoles', fn ($roles) => $roles->where('roles.customer_id', $customerId)
                ->wherePivot('customer_id', $customerId)->wherePivot('clinic_branch_id', $branchId ?? 0)
                ->whereRaw('LOWER(TRIM(roles.name)) = ?', ['administrador']));
            $query->whereHas('branches', fn ($branches) => $branches
                ->where('clinic_branches.customer_id', $customerId)
                ->where('clinic_branches.status', true)
                ->whereKey($branchId ?? 0));
        }

        return $query;
    }

    private function actorIsGeneralAdministrator(int $customerId): bool
    {
        $user = auth()->user();
        $branchId = $this->activeBranchId($customerId) ?? ($user ? \App\Support\ClinicBranchContext::defaultBranch($user, $customerId)?->id : null);

        return $user?->hasClinicRole('Administrador', $customerId, $branchId) ?? false;
    }

    private function actorIsSiteAdministrator(int $customerId): bool
    {
        $user = auth()->user();
        if ($user && \App\Support\ClinicBranchContext::canAdministerAllBranches($user, $customerId)) {
            return false;
        }

        if ($this->actorIsGeneralAdministrator($customerId)) {
            return false;
        }

        $branchId = $this->activeBranchId($customerId) ?? ($user ? \App\Support\ClinicBranchContext::defaultBranch($user, $customerId)?->id : null);

        return $user?->hasClinicRole('Administrador de sede', $customerId, $branchId) ?? false;
    }

    private function activeBranchId(int $customerId): ?int
    {
        if ((int) session('active_branch_customer_id') !== $customerId || ! session()->has('active_branch_id')) {
            return null;
        }

        return ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)
            ->whereKey((int) session('active_branch_id'))->value('id');
    }

    private function assignableBranchRule(int $customerId): Exists
    {
        $rule = Rule::exists('clinic_branches', 'id')->where('customer_id', $customerId)->where('status', true);
        $user = auth()->user();
        $activeBranchId = $this->activeBranchId($customerId);
        $canAssignAcrossBranches = $user && $activeBranchId
            && \App\Support\ClinicBranchContext::canAdministerAllBranches($user, $customerId)
            && \App\Support\ClinicBranchContext::hasPermissionInBranch($user, $customerId, (int) $activeBranchId, 'professionals.manage');
        if (! $canAssignAcrossBranches) {
            $rule->where('id', $activeBranchId ?? 0);
        }

        return $rule;
    }

    private function syncBranchRoles(User $user, int $customerId, int $branchId, array $roleIds): void
    {
        DB::table('clinic_branch_user_role')->where('customer_id', $customerId)
            ->where('clinic_branch_id', $branchId)->where('user_id', $user->id)->delete();

        $now = now();
        DB::table('clinic_branch_user_role')->insert(array_map(fn ($roleId) => [
            'customer_id' => $customerId,
            'clinic_branch_id' => $branchId,
            'user_id' => $user->id,
            'role_id' => (int) $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values(array_unique($roleIds))));
    }

    private function syncUserSpecialties(User $user, int $customerId, int $branchId, array $specialtyIds): void
    {
        app(\App\Services\SpecialtyCatalog::class)->sync($user, $customerId, $branchId, $specialtyIds);
    }

    private function canManageRolesInBranch(User $actor, int $customerId, int $branchId): bool
    {
        if ($actor->roles()->whereNull('roles.customer_id')->where('roles.name', 'Super Administrador')->exists()) {
            return true;
        }

        // La operación entre sedes habilita el alcance; roles.manage sigue
        // determinando si este usuario puede administrar usuarios y roles.
        $activeBranchId = $this->activeBranchId($customerId)
            ?? \App\Support\ClinicBranchContext::defaultBranch($actor, $customerId)?->id;
        if ($activeBranchId && (int) $activeBranchId !== $branchId) {
            return \App\Support\ClinicBranchContext::canAdministerAllBranches($actor, $customerId)
                && \App\Support\ClinicBranchContext::hasPermissionInBranch($actor, $customerId, (int) $activeBranchId, 'roles.manage');
        }

        return $actor->hasClinicRole('Administrador', $customerId, $branchId)
            || $actor->hasClinicRole('Administrador de sede', $customerId, $branchId)
            || $actor->clinicRolesForBranch($customerId, $branchId)
                ->whereHas('permissions', fn ($query) => $query->where('permissions.slug', 'roles.manage'))->exists();
    }
}
