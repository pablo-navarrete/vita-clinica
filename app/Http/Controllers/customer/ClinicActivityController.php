<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
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
            'action' => ['nullable', 'in:created,updated,deleted'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = ClinicActivityLog::query()
            ->where('customer_id', $customerId)
            ->with('user')
            ->latest('created_at')
            ->latest('id');

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
        $users = User::withTrashed()->where('customer_id', $customerId)->orderBy('name')->get(['id', 'name']);

        return view('customer.activity-history.index', compact('logs', 'modules', 'users', 'filters'));
    }
}
