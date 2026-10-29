<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ClinicBranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        $customerId = (int) app('customer')->id;
        $this->authorizeManager();
        $this->ensureCatalog();
        $oldCrossBranchPermission = Permission::query()->where('slug', 'chat.cross_branch')->first();
        if ($oldCrossBranchPermission) {
            DB::table('permission_role')->where('permission_id', $oldCrossBranchPermission->id)->delete();
            $oldCrossBranchPermission->delete();
        }
        $this->ensureBaseRoles($customerId);

        $roles = Role::query()->where('customer_id', $customerId)
            ->with('permissions')->withCount('users')->orderBy('id')->get();
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module');
        $assignableRoles = $roles->map(fn ($role) => $role->only(['id', 'name']));

        return view('customer.roles-permissions.index', compact('roles', 'permissions', 'assignableRoles'));
    }

    public function ensureClinicRoles(int $customerId): void
    {
        $this->ensureCatalog();
        $this->ensureBaseRoles($customerId);
    }

    public function usersData()
    {
        $this->authorizeManager();
        $customerId = (int) app('customer')->id;
        $branchId = ClinicBranchContext::defaultBranch(request()->user(), $customerId)?->id;
        $roles = Role::query()->where('customer_id', $customerId)->orderBy('name')->get(['id', 'name']);
        $query = User::query()->where('customer_id', $customerId)
            ->with(['branchRoles' => fn ($relation) => $relation->where('roles.customer_id', $customerId)
                ->wherePivot('customer_id', $customerId)->wherePivot('clinic_branch_id', $branchId ?? 0)])
            ->orderBy('name');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('role_control', function (User $user) use ($roles, $branchId) {
                $selectedRoleIds = $user->branchRoles->pluck('id')->map(fn ($id) => (int) $id)->all();
                $roleOptions = $roles->map(function (Role $role) use ($selectedRoleIds) {
                    $checked = in_array((int) $role->id, $selectedRoleIds, true) ? ' checked' : '';
                    return '<label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700">'
                        . '<input type="checkbox" name="role_ids[]" value="' . $role->id . '"' . $checked . ' class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">'
                        . '<span>' . e($role->name) . '</span></label>';
                })->implode('');
                $action = route('customers.roles-permissions.users.update', [
                    'customer' => request()->route('customer'), 'userId' => $user->id,
                ]);

                return '<form method="POST" action="' . e($action) . '" class="flex min-w-[18rem] flex-col items-start gap-2">'
                    . csrf_field() . method_field('PATCH')
                    . '<input type="hidden" name="branch_id" value="' . (int) $branchId . '">'
                    . '<div class="flex flex-wrap gap-1.5">' . $roleOptions . '</div>'
                    . '<button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Guardar</button></form>';
            })
            ->rawColumns(['role_control'])
            ->make(true);
    }

    public function store(Request $request): RedirectResponse
    {
        $customerId = (int) app('customer')->id;
        $this->authorizeManager();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles')->where('customer_id', $customerId)],
        ]);

        Role::query()->create(['customer_id' => $customerId, 'name' => trim($data['name']), 'permissions_configured' => true]);

        return back()->with('success', 'Rol creado. Selecciona sus permisos.');
    }

    public function update(Request $request, int $roleId): RedirectResponse
    {
        $this->authorizeManager();
        $role = $this->clinicRole($roleId);
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);
        $role->forceFill(['permissions_configured' => true])->save();

        return back()->with('success', 'Permisos actualizados para ' . $role->name . '.');
    }

    public function destroy(int $roleId): RedirectResponse
    {
        $this->authorizeManager();
        $role = $this->clinicRole($roleId);
        abort_if(in_array($role->name, ['Administrador', 'Administrador de sede', 'Doctor', 'Secretaria'], true), 403, 'Los roles base no se pueden eliminar.');
        abort_if($role->users()->exists(), 422, 'No puedes eliminar un rol que tiene usuarios asignados.');
        $role->delete();

        return back()->with('success', 'Rol eliminado.');
    }

    public function updateUserRole(Request $request, $customer, $userId): RedirectResponse
    {
        $this->authorizeManager();
        $userId = filter_var($request->route('userId'), FILTER_VALIDATE_INT);
        abort_unless($userId && $userId > 0, 404);
        $customerId = (int) app('customer')->id;
        $user = User::query()->where('customer_id', $customerId)->findOrFail($userId);
        $branchId = filter_var($request->input('branch_id', session('active_branch_id')), FILTER_VALIDATE_INT);
        $branchIds = \App\Support\ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            ? \App\Support\ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : \App\Support\ClinicBranchContext::availableBranches($request->user(), $customerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_unless($branchId && in_array((int) $branchId, $branchIds, true) && $user->branches()->whereKey($branchId)->exists(), 403);
        $actor = $request->user();
        $isSuperAdmin = $actor->roles()->whereNull('roles.customer_id')->where('roles.name', 'Super Administrador')->exists();
        $activeBranchId = \App\Support\ClinicBranchContext::defaultBranch($actor, $customerId)?->id;
        $centralRoleManagement = $activeBranchId && (int) $activeBranchId !== (int) $branchId
            && \App\Support\ClinicBranchContext::canAdministerAllBranches($actor, $customerId)
            && \App\Support\ClinicBranchContext::hasPermissionInBranch($actor, $customerId, (int) $activeBranchId, 'roles.manage');
        $canManageRoles = $isSuperAdmin || $centralRoleManagement
            || $actor->hasClinicRole('Administrador', $customerId, (int) $branchId)
            || \App\Support\ClinicBranchContext::hasPermissionInBranch($actor, $customerId, (int) $branchId, 'roles.manage');
        abort_unless($canManageRoles, 403, 'No tienes permiso para administrar roles en esta sede.');
        $data = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', Rule::exists('roles', 'id')->where('customer_id', $customerId)],
        ]);
        $adminRoleId = Role::query()->where('customer_id', $customerId)->whereRaw('LOWER(TRIM(name)) = ?', ['administrador'])->value('id');
        if ($adminRoleId && in_array((int) $adminRoleId, array_map('intval', $data['role_ids']), true)) {
            abort_unless($isSuperAdmin || $actor->hasClinicRole('Administrador', $customerId, (int) $branchId), 403, 'Solo el Administrador general puede asignar ese rol.');
        }
        $roleIds = array_values(array_unique(array_map('intval', $data['role_ids'])));
        $assignsGeneralAdmin = $adminRoleId && in_array((int) $adminRoleId, $roleIds, true);
        $targetBranchIds = $assignsGeneralAdmin
            ? $user->branches()->where('clinic_branches.status', true)->pluck('clinic_branches.id')->map(fn ($id) => (int) $id)->all()
            : [(int) $branchId];
        foreach ($targetBranchIds as $targetBranchId) {
            $branchRoleIds = $targetBranchId === (int) $branchId || !$assignsGeneralAdmin
                ? $roleIds
                : array_values(array_unique(array_merge(
                    $user->clinicRolesForBranch($customerId, $targetBranchId)->pluck('roles.id')->map(fn ($id) => (int) $id)->all(),
                    [(int) $adminRoleId]
                )));
            DB::table('clinic_branch_user_role')->where('customer_id', $customerId)
                ->where('clinic_branch_id', $targetBranchId)->where('user_id', $user->id)->delete();
            $now = now();
            DB::table('clinic_branch_user_role')->insert(array_map(fn ($roleId) => [
                'customer_id' => $customerId,
                'clinic_branch_id' => $targetBranchId,
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $branchRoleIds));
        }
        $user->syncClinicRoleMirror($customerId);

        return back()->with('success', 'Rol del usuario actualizado.');
    }

    private function clinicRole(int $roleId): Role
    {
        return Role::query()->where('customer_id', (int) app('customer')->id)->findOrFail($roleId);
    }

    private function authorizeManager(): void
    {
        $user = request()->user();
        $customerId = (int) app('customer')->id;
        $branchId = \App\Support\ClinicBranchContext::defaultBranch($user, $customerId)?->id;
        $isSuperAdmin = $user->roles()
            ->whereNull('roles.customer_id')
            ->where('roles.name', 'Super Administrador')
            ->exists();
        $canManageCentrally = $branchId
            && \App\Support\ClinicBranchContext::canAdministerAllBranches($user, $customerId)
            && \App\Support\ClinicBranchContext::hasPermissionInBranch($user, $customerId, (int) $branchId, 'roles.manage');
        abort_unless($isSuperAdmin || $canManageCentrally, 403, 'La configuración global de roles requiere permisos centrales desde la sede principal.');
    }

    private function ensureBaseRoles(int $customerId): void
    {
        foreach (['Administrador', 'Administrador de sede', 'Doctor', 'Secretaria'] as $name) {
            $role = Role::query()->firstOrCreate(
                ['customer_id' => $customerId, 'name' => $name],
                ['permissions_configured' => false]
            );

            if (! $role->permissions_configured) {
                $role->permissions()->sync(Permission::query()->whereIn('slug', $this->defaultPermissions($name))->pluck('id'));
                $role->forceFill(['permissions_configured' => true])->save();
            }

            if ($name === 'Administrador') {
                $managePermissionId = Permission::query()->where('slug', 'roles.manage')->value('id');
                if ($managePermissionId) {
                    $role->permissions()->syncWithoutDetaching([$managePermissionId]);
                }
            }
        }
    }

    private function ensureCatalog(): void
    {
        $catalog = [
            'Panel' => ['dashboard.view' => 'Ver panel'],
            'Pacientes' => ['patients.view' => 'Ver pacientes', 'patients.create' => 'Crear pacientes', 'patients.update' => 'Editar pacientes', 'patients.delete' => 'Eliminar pacientes'],
            'Agenda' => ['appointments.view' => 'Ver agenda', 'appointments.create' => 'Crear citas', 'appointments.update' => 'Editar citas', 'appointments.cancel' => 'Cancelar citas'],
            'Sedes' => [
                'branches.admin_all' => 'Administrar todas las sedes desde la sede principal',
                'branches.operate_all' => 'Operar en todas las sedes',
            ],
            'Ficha clínica' => ['medical_records.view' => 'Ver ficha clínica', 'medical_records.manage' => 'Modificar antecedentes y atenciones'],
            'Administración' => ['professionals.manage' => 'Gestionar profesionales', 'schedules.manage' => 'Gestionar horarios profesionales', 'clinic_configuration.manage' => 'Gestionar previsiones y especialidades', 'website.manage' => 'Gestionar sitio público'],
            'Chat y avisos' => ['chat.view' => 'Ver conversaciones', 'chat.send' => 'Enviar mensajes', 'chat.manage' => 'Administrar conversaciones y participantes', 'notifications.view' => 'Ver notificaciones'],
            'Auditoría' => ['audit.view' => 'Ver historial de actividad'],
            'Sistema' => ['roles.manage' => 'Administrar roles y permisos'],
        ];
        foreach ($catalog as $module => $items) {
            foreach ($items as $slug => $name) {
                Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'module' => $module]);
            }
        }
    }

    private function defaultPermissions(string $role): array
    {
        $all = Permission::query()->pluck('slug')->all();
        return match ($role) {
            'Administrador' => $all,
            'Administrador de sede' => array_values(array_filter($all, fn ($slug) => !in_array($slug, [
                'roles.manage', 'website.manage', 'clinic_configuration.manage', 'branches.admin_all', 'branches.operate_all',
            ], true))),
            'Doctor' => ['dashboard.view', 'patients.view', 'appointments.view', 'appointments.create', 'appointments.update', 'medical_records.view', 'medical_records.manage', 'schedules.manage', 'chat.view', 'chat.send', 'notifications.view'],
            'Secretaria' => ['dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel', 'chat.view', 'chat.send', 'notifications.view'],
            default => [],
        };
    }
}
