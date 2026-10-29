<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Support\ClinicBranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClinicBranchContextController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $customerId = (int) app('customer')->id;
        $branch = ClinicBranchContext::availableBranches($request->user(), $customerId)
            ->firstWhere('id', (int) $data['branch_id']);

        if (!$branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'No tienes acceso a esa sede o ya no está activa.',
            ]);
        }

        $request->session()->put('active_branch_id', (int) $branch->id);
        $request->session()->put('active_branch_customer_id', $customerId);

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('customers.dashboard.index', ['customer' => $request->route('customer')]),
            ]);
        }

        return redirect()
            ->route('customers.dashboard.index', ['customer' => $request->route('customer')], 303)
            ->with('status', 'Sede cambiada correctamente.');
    }
}
