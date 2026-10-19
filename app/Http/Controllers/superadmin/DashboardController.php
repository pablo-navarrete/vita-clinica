<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;
        $firstYear = Customer::query()->min(\Illuminate\Support\Facades\DB::raw('YEAR(created_at)'));
        $years = range($currentYear, min((int) ($firstYear ?: $currentYear), $currentYear));

        return view('superadmin.dashboard.index', compact('currentYear', 'years'));
    }

    public function statistics(Request $request)
    {
        $year = min($request->integer('year', now()->year), now()->year);
        $month = $request->integer('month');
        $month = $month >= 1 && $month <= 12 ? $month : null;

        $registrationsQuery = Customer::query()
            ->whereYear('created_at', $year)
            ->when($month, fn ($query) => $query->whereMonth('created_at', $month));

        if ($month) {
            $registrations = $registrationsQuery
                ->selectRaw('DAY(created_at) as period, COUNT(*) as total')
                ->groupByRaw('DAY(created_at)')
                ->pluck('total', 'period');
            $periodCount = now()->setDate($year, $month, 1)->daysInMonth;
        } else {
            $registrations = $registrationsQuery
                ->selectRaw('MONTH(created_at) as period, COUNT(*) as total')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('total', 'period');
            $periodCount = 12;
        }

        $periodTotals = [];
        for ($period = 1; $period <= $periodCount; $period++) {
            $periodTotals[] = (int) ($registrations[$period] ?? 0);
        }

        return response()->json([
            'total' => Customer::count(),
            'newThisMonth' => Customer::query()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'active' => Customer::where('status', 1)->count(),
            'inactive' => Customer::where('status', '!=', 1)->count(),
            'periods' => $periodTotals,
            'periodType' => $month ? 'day' : 'month',
        ]);
    }
}
