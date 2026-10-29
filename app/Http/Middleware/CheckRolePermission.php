<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\ClinicBranchContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRolePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // El superadministrador administra todas las clínicas y no depende
        // de los permisos configurados para el personal de una clínica.
        if ($user->roles()
            ->whereNull('roles.customer_id')
            ->where('roles.name', 'Super Administrador')
            ->exists()) {
            return $next($request);
        }

        $permission = $this->permissionFor($request);
        if (! $permission) {
            return $next($request);
        }

        // O tenant é resolvido por IdentifyCustomer antes deste middleware.
        // Mantém o fallback pelo usuário para requisições tenant sem binding.
        $customer = app()->bound('customer') ? app('customer') : null;
        $customerId = $customer?->id ?? $user->customer_id;
        $customerId = $customerId !== null ? (int) $customerId : null;

        $branchId = $customerId ? ClinicBranchContext::defaultBranch($user, $customerId)?->id : null;
        if ($request->is('theme') && $customerId && $branchId
            && $user->hasClinicRole('Administrador', $customerId, (int) $branchId)) {
            return $next($request);
        }
        if (in_array($permission, ['website.manage', 'clinic_configuration.manage'], true)) {
            if (!$customerId || !ClinicBranchContext::canAdministerAllBranches($user, $customerId)) {
                if ($request->isMethod('GET') && !$request->expectsJson()) {
                    return redirect()->route('customers.dashboard.index', ['customer' => $request->route('customer')])
                        ->with('status', 'Se abrió el panel de la sede seleccionada.');
                }
                abort(403, 'Esta configuración global solo está disponible desde la sede principal con permisos centrales.');
            }
        }
        $clinicRoles = fn () => $customerId && $branchId ? $user->clinicRolesForBranch($customerId, (int) $branchId) : null;
        $isClinicAdmin = $clinicRoles()?->whereRaw('LOWER(TRIM(roles.name)) = ?', ['administrador'])->exists() ?? false;
        if ($permission === 'roles.manage' && $isClinicAdmin) {
            return $next($request);
        }

        $roles = $clinicRoles()?->get(['roles.id', 'roles.permissions_configured']) ?? collect();
        abort_if($roles->isEmpty(), 403, 'No tienes roles asignados en la sede seleccionada.');
        if ($roles->every(fn ($role) => ! (bool) $role->permissions_configured)) {
            return $next($request);
        }

        $allowed = $clinicRoles()
            ? $clinicRoles()->whereHas('permissions', fn ($query) => $query->where('permissions.slug', $permission))->exists()
            : false;

        abort_unless($allowed, 403, 'Tu rol no tiene permiso para realizar esta acción.');

        return $next($request);
    }

    private function permissionFor(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        if ($path === 'professionals/by-specialty') return 'appointments.view';
        if ($path === 'dashboard') return 'dashboard.view';
        if ($path === 'my-encounters') return 'medical_records.view';
        if (preg_match('#^patients/\d+/telemedicine(/|$)#', $path)) return 'medical_records.manage';
        if (str_starts_with($path, 'assistant/')) return 'dashboard.view';
        if ($path === 'activity-history' || str_starts_with($path, 'patients/') && str_ends_with($path, '/medical-record/activity')) return 'audit.view';
        if (str_contains($path, '/encounters') || in_array($path, ['allergies', 'chronic-diseases', 'regular-medications', 'previous-surgeries'], true)) return 'medical_records.manage';
        if (str_starts_with($path, 'patients/') && str_contains($path, '/medical-record')) return $method === 'GET' ? 'medical_records.view' : 'medical_records.manage';
        if (preg_match('#^customer/appointments/[^/]+/cancel$#', $path)) return 'appointments.cancel';
        if (str_starts_with($path, 'patient') || str_starts_with($path, 'patients/')) {
            return $method === 'GET' ? 'patients.view' : 'patients.' . match ($method) {
                'POST' => 'create', 'PUT', 'PATCH' => 'update', 'DELETE' => 'delete', default => 'view',
            };
        }
        if (str_starts_with($path, 'appointments')) {
            return $method === 'GET' ? 'appointments.view' : 'appointments.' . match ($method) {
                'POST' => 'create', 'PUT' => 'update', 'PATCH', 'DELETE' => 'cancel', default => 'view',
            };
        }
        if (str_starts_with($path, 'notifications')) return 'notifications.view';
        if (str_starts_with($path, 'chat')) {
            if ($method === 'GET') return 'chat.view';
            if (str_contains($path, 'participants') || str_ends_with($path, '/hide')) return 'chat.manage';
            return 'chat.send';
        }
        if (preg_match('#^(professional/[^/]+/schedule|professional/schedule/[^/]+|my-schedules|customer/schedules)(/|$)#', $path)) return 'schedules.manage';
        if (str_starts_with($path, 'users') || str_starts_with($path, 'professional')) return 'professionals.manage';
        if (str_starts_with($path, 'my-schedules') || str_starts_with($path, 'customer/schedules')) return 'schedules.manage';
        if (str_starts_with($path, 'telemedicine')) return 'medical_records.manage';
        if (str_starts_with($path, 'insurances') || str_starts_with($path, 'specialties')) return 'clinic_configuration.manage';
        if ($path === 'theme' || preg_match('/^(logo|menus|banners|pages|website-|mail-settings)/', $path)) return 'website.manage';

        return null;
    }
}
