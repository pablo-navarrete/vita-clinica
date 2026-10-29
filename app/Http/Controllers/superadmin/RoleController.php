<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->ensurePermissionCatalog();
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module');
        $roles = Role::query()->with('permissions')->withCount('users')->orderBy('id')->get();
        $users = User::query()->with(['customer:id,name', 'roles:id,name'])
            ->whereNotNull('customer_id')
            ->orderBy('customer_id')->orderBy('name')
            ->paginate(20, ['id', 'customer_id', 'name', 'email', 'status'], 'users_page');
        $assignableRoles = Role::query()->where('id', '!=', 1)->orderBy('name')->get(['id', 'name']);

        foreach ($roles->where('permissions_configured', false) as $role) {
            $defaultSlugs = $this->defaultPermissionsFor($role->name);
            $role->permissions()->sync(Permission::query()->whereIn('slug', $defaultSlugs)->pluck('id'));
            $role->forceFill(['permissions_configured' => true])->save();
            $role->load('permissions');
        }

        return view('superadmin.roles.index', compact('roles', 'permissions', 'users', 'assignableRoles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:roles,name']]);
        $role = Role::query()->create([
            'name' => trim($data['name']),
            'permissions_configured' => true,
        ]);
        $role->permissions()->sync([]);

        return redirect()->route('superadmin.roles.index')->with('success', 'Rol creado. Asigna sus permisos en la tabla.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);
        $role->forceFill(['permissions_configured' => true])->save();

        return redirect()->route('superadmin.roles.index')->with('success', 'Permisos del rol actualizados.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->id <= 4 || in_array($role->name, ['Super Administrador', 'Administrador', 'Doctor', 'Secretaria'], true)) {
            return redirect()->route('superadmin.roles.index')->with('error', 'Los roles base del sistema no se pueden eliminar.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('superadmin.roles.index')->with('error', 'No se puede eliminar un rol que tiene usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('superadmin.roles.index')->with('success', 'Rol eliminado.');
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->customer_id === null, 404);
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);
        abort_if((int) $data['role_id'] === 1, 403, 'No se puede asignar el rol Super Administrador desde esta pantalla.');

        $user->roles()->sync([(int) $data['role_id']]);

        return redirect()->route('superadmin.roles.index', ['users_page' => $request->input('users_page')])
            ->with('success', 'Rol del usuario actualizado.');
    }

    private function ensurePermissionCatalog(): void
    {
        foreach ($this->permissionCatalog() as $module => $items) {
            foreach ($items as $slug => $name) {
                Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'module' => $module]);
            }
        }
    }

    private function permissionCatalog(): array
    {
        return [
            'Panel' => ['dashboard.view' => 'Ver panel'],
            'Pacientes' => [
                'patients.view' => 'Ver pacientes',
                'patients.create' => 'Crear pacientes',
                'patients.update' => 'Editar pacientes',
                'patients.delete' => 'Eliminar pacientes',
            ],
            'Agenda' => [
                'appointments.view' => 'Ver agenda',
                'appointments.create' => 'Crear citas',
                'appointments.update' => 'Editar citas',
                'appointments.cancel' => 'Cancelar citas',
            ],
            'Ficha clínica' => [
                'medical_records.view' => 'Ver ficha clínica',
                'medical_records.manage' => 'Modificar antecedentes y atenciones',
            ],
            'Administración' => [
                'professionals.manage' => 'Gestionar profesionales',
                'schedules.manage' => 'Gestionar horarios profesionales',
                'clinic_configuration.manage' => 'Gestionar previsiones y especialidades',
                'website.manage' => 'Gestionar sitio público',
            ],
            'Chat y avisos' => [
                'chat.view' => 'Ver conversaciones',
                'chat.send' => 'Enviar mensajes',
                'chat.manage' => 'Administrar conversaciones y participantes',
                'notifications.view' => 'Ver notificaciones',
            ],
            'Auditoría' => ['audit.view' => 'Ver historial de actividad'],
            'Sistema' => ['roles.manage' => 'Administrar roles y permisos'],
        ];
    }

    private function defaultPermissionsFor(string $roleName): array
    {
        $all = Permission::query()->pluck('slug')->all();

        return match ($roleName) {
            'Super Administrador' => $all,
            'Administrador' => array_values(array_filter($all, fn ($slug) => $slug !== 'roles.manage')),
            'Doctor' => [
                'dashboard.view', 'patients.view', 'appointments.view', 'appointments.create', 'appointments.update',
                'medical_records.view', 'medical_records.manage', 'schedules.manage', 'chat.view', 'chat.send', 'notifications.view',
            ],
            'Secretaria' => [
                'dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'appointments.view',
                'appointments.create', 'appointments.update', 'appointments.cancel', 'chat.view', 'chat.send',
                'notifications.view',
            ],
            default => [],
        };
    }
}
