<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\ClinicBranch;
use App\Models\ClinicActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicActivityController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = (int) app('customer')->id;
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'in:created,updated,deleted'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $user = auth()->user();
        $canManageAllBranches = $this->canViewAllBranches($user, $customerId);
        $activeBranchId = $this->activeBranchId($customerId);
        $headquartersBranchId = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->value('id');
        $showBranchFilter = $canManageAllBranches
            && $activeBranchId !== null
            && $headquartersBranchId !== null
            && $activeBranchId === (int) $headquartersBranchId;

        abort_unless($showBranchFilter || !$request->filled('branch_id'), 403);

        $branches = $showBranchFilter
            ? ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $query = ClinicActivityLog::query()
            ->where('customer_id', $customerId)
            ->with(['user', 'clinicBranch'])
            ->latest('created_at')
            ->latest('id');

        if ($showBranchFilter) {
            if ($request->filled('branch_id')) {
                $branchExists = $branches->contains(fn (ClinicBranch $branch) => (int) $branch->id === (int) $filters['branch_id']);
                abort_unless($branchExists, 404, 'La sede seleccionada no pertenece a esta clínica o está inactiva.');
                $query->where('clinic_branch_id', (int) $filters['branch_id']);
            }
        } elseif ($activeBranchId !== null) {
            $query->where('clinic_branch_id', $activeBranchId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->when($request->filled('module'), fn ($q) => $q->where('module', $filters['module']))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $filters['action']))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($request->filled('search'), function ($q) use ($filters) {
                $search = trim($filters['search']);
                $q->where(fn ($nested) => $nested
                    ->where('summary', 'like', "%{$search}%")
                    ->orWhere('subject_label', 'like', "%{$search}%"));
            });

        $logs = $query->paginate(25)->withQueryString();
        $modules = ClinicActivityLog::query()->where('customer_id', $customerId)
            ->select('module')->distinct()->orderBy('module')->pluck('module');
        $usersQuery = User::withTrashed()->where('customer_id', $customerId);
        if ($showBranchFilter && $request->filled('branch_id')) {
            $usersQuery->whereHas('branches', fn ($query) => $query->whereKey((int) $filters['branch_id']));
        } elseif (!$showBranchFilter && $activeBranchId !== null) {
            $usersQuery->whereHas('branches', fn ($query) => $query->whereKey($activeBranchId));
        }
        $users = $usersQuery->orderBy('name')->get(['id', 'name']);

        return view('customer.activity-history.index', compact('logs', 'modules', 'users', 'filters', 'branches', 'showBranchFilter'));
    }

    private function canViewAllBranches(?User $user, int $customerId): bool
    {
        return $user !== null
            && \App\Support\ClinicBranchContext::canAdministerAllBranches($user, $customerId);
    }

    private function activeBranchId(int $customerId): ?int
    {
        if ((int) session('active_branch_customer_id') !== $customerId || !session()->has('active_branch_id')) {
            return null;
        }

        $branchId = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->whereKey((int) session('active_branch_id'))
            ->value('id');

        return $branchId === null ? null : (int) $branchId;
    }
}
