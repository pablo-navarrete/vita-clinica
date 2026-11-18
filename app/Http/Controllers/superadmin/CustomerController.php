<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\ClinicBranch;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CustomerController extends Controller
{
    public function identification(Request $request, Customer $customer)
    {
        return response()->json(app(\App\Services\Identification\IdentificationForms::class)->form($customer, $request->user()))->header('Cache-Control', 'no-store');
    }

    public function administratorIdentification(Request $request, Customer $customer, int $userId)
    {
        $user = $customer->administratorUsers()->findOrFail($userId);

        return response()->json(app(\App\Services\Identification\IdentificationForms::class)->form($user, $request->user()))->header('Cache-Control', 'no-store');
    }

    public function branches(Customer $customer)
    {
        return view('superadmin.customer.branches', [
            'customer' => $customer,
            'branches' => $customer->branches()->orderBy('name')->get(),
            'subscription' => \App\Models\CustomerSubscription::where('customer_id', $customer->id)->first(),
        ]);
    }

    public function storeBranch(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'operating_country_code' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        app(\App\Services\Billing\BranchCapacityService::class)->create($customer, $data);

        return redirect()->route('superadmin.customers.branches', $customer)->with('success', 'Sede agregada correctamente.');
    }

    public function updateBranch(Request $request, Customer $customer, int $branchId)
    {
        $branch = $customer->branches()->findOrFail($branchId);
        $data = $request->validateWithBag('editBranch', [
            'operating_country_code' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);
        $branch->update($data);

        return redirect()->route('superadmin.customers.branches', $customer)
            ->with('success', 'Sede actualizada correctamente.');
    }

    public function toggleBranchStatus(Request $request, Customer $customer, int $branchId)
    {
        $request->validate(['status' => 'required|boolean']);
        $activate = $request->boolean('status');
        $branch = app(\App\Services\Billing\BranchCapacityService::class)->setStatus($customer, $branchId, $activate);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => (bool) $branch->status,
            ]);
        }

        return redirect()->route('superadmin.customers.branches', $customer)
            ->with('success', $activate ? 'Sede activada correctamente.' : 'Sede desactivada correctamente.');
    }

    public function destroyBranch(Customer $customer, int $branchId)
    {
        $branch = $customer->branches()->findOrFail($branchId);
        if ($branch->status && $customer->branches()->where('status', true)->count() <= 1) {
            return redirect()->route('superadmin.customers.branches', $customer)
                ->with('error', 'La clínica debe conservar al menos una sede activa.');
        }

        $branch->delete();

        return redirect()->route('superadmin.customers.branches', $customer)->with('success', 'Sede quitada. Las citas históricas se conservan.');
    }

    public function index()
    {
        return view('superadmin.customer.index', ['billingPlans' => \App\Models\BillingPlan::where('active', true)->with('currentVersion.prices')->orderBy('name')->get()]);
    }

    // datatable clientes
    public function getCustomer()
    {
        return DataTables::of(Customer::query()->with([
            'operatingDeclaration', 'operatingReview',
            'administratorUsers' => fn ($q) => $q->select('users.id', 'users.customer_id', 'users.name', 'users.email', 'users.phone'),
            'administratorUsers.branchRoles' => fn ($q) => $q->whereIn('roles.name', ['Administrador', 'Administrador de sede']),
            'branches' => fn ($q) => $q->where('status', true)->orderBy('id')->select('id', 'customer_id', 'name'),
        ]))
        // Administrator fields are JSON data bound through Alpine x-text/x-model, never HTML.
            ->escapeColumns(['name', 'legal_name', 'holder', 'email', 'phone', 'address', 'domain', 'color', 'subscription_url'])
            ->addColumn('administrators', fn ($customer) => $customer->administratorUsers->map(fn ($user) => $user->only(['id', 'name', 'email', 'phone']) + [
                'branch_ids' => $user->branchRoles->filter(fn ($role) => (int) $role->customer_id === (int) $customer->id && (int) $role->pivot->customer_id === (int) $customer->id)->pluck('pivot.clinic_branch_id')->unique()->values()->all(),
            ])->values()->all())
            ->addColumn('admin_branches', fn ($customer) => $customer->branches->map(fn ($branch) => $branch->only(['id', 'name']))->values()->all())
            ->removeColumn('rut')->removeColumn('administrator_users')->removeColumn('branches')->addColumn('subscription_url', function ($customer) {
                $s = \App\Models\CustomerSubscription::where('customer_id', $customer->id)->first();

                return $s ? route('superadmin.billing.subscriptions.show', $s) : route('superadmin.billing.subscriptions.create', ['customer_id' => $customer->id]);
            })->make(true);
    }

    // crear cliente
    public function store(Request $request)
    {
        app(\App\Services\CustomerOperatingScope::class)->validate($request, true);
        $ids = app(\App\Services\Identification\IdentificationForms::class);
        $document = $ids->parse($request, true, null, true);
        $adminDocument = $ids->parse($request, false, null, true, 'admin_');
        $ids->assertCustomerSchema($document, null);
        if ($request->filled('billing_plan_price_id')) {
            $request->validate(['billing_plan_price_id' => 'required|integer|exists:billing_plan_prices,id', 'subscription_starts_on' => 'required|date_format:Y-m-d', 'subscription_branches' => 'required|integer|min:1|max:2147483647', 'subscription_payment_mode' => 'required|in:automatic,manual']);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'legal_name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
            'rut' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'holder' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_rut' => 'nullable|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_phone' => 'required|string|max:50',
            'admin_password' => 'required|string|min:8|confirmed',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'legal_name.required' => 'La Razón Social es obligatoria',
            'status.required' => 'Debe seleccionar un estado',
            'rut.required' => 'El RUT es obligatorio',
            'email.required' => 'El email es obligatorio',
            'holder.required' => 'El sostenedor es obligatorio',
            'admin_name.required' => 'Ingresa el nombre del administrador.',
            'admin_rut.required' => 'Ingresa el RUT del administrador.',
            'admin_email.required' => 'Ingresa el correo de acceso del administrador.',
            'admin_email.email' => 'Ingresa un correo de acceso válido.',
            'admin_phone.required' => 'Ingresa el teléfono del administrador.',
            'admin_password.required' => 'Ingresa una contraseña para el administrador.',
            'admin_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'admin_password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generar subdominio limpio
        $domain = strtolower($request->name);
        $domain = preg_replace('/[^a-z0-9]/', '', $domain);

        // Evitar duplicados
        if (Customer::where('domain', $domain)->exists()) {
            return response()->json([
                'errors' => [
                    'name' => ['Ya existe un cliente con ese subdominio.'],
                ],
            ], 422);
        }

        $customer = DB::transaction(function () use ($request, $domain, $document, $ids) {
            $customer = Customer::create([
                'name' => $request->name,
                'rut' => $ids->legacyValue($document),
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'holder' => $request->holder,
                'legal_name' => $request->legal_name,
                'domain' => $domain,
                'status' => $request->status,
                'payment_status' => 1,
                'paid_at' => now(),
                'expires_at' => now()->addMonth(),
            ]);

            $branch = $customer->branches()->create([
                'name' => 'Sede principal',
                'address' => $request->address,
                'phone' => $request->phone,
                'status' => true,
            ]);

            $this->createAdministrator($customer, $branch, $request);
            $ids->save($customer, $request->user(), $document, $request, '', true);
            app(\App\Services\CustomerOperatingScope::class)->record($customer, $request->user(), $request);

            if ($request->filled('billing_plan_price_id')) {
                app(\App\Services\Billing\SubscriptionService::class)->create($request->user(), [
                    'customer_id' => $customer->id, 'billing_plan_price_id' => $request->integer('billing_plan_price_id'),
                    'starts_on' => $request->subscription_starts_on, 'branch_quantity' => $request->integer('subscription_branches'),
                    'payment_mode' => $request->subscription_payment_mode, 'use_trial' => false, 'note' => null,
                ]);
            }

            return $customer;
        });

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    // Actualizar cliente
    public function update(Request $request, Customer $customer)
    {
        app(\App\Services\CustomerOperatingScope::class)->validate($request, false);
        $ids = app(\App\Services\Identification\IdentificationForms::class);
        $document = $ids->parse($request, true, $customer->rut, false);
        $ids->assertCustomerSchema($document, $customer->rut);
        $createAdmin = $request->boolean('create_admin');
        if ($request->filled('admin_user_id')) {
            $request->validate(['admin_user_id' => ['required', 'integer']]);
            $customer->administratorUsers()->findOrFail($request->integer('admin_user_id'));
        }
        $validator = Validator::make($request->all(), [

            'name' => 'required|string|max:255',
            'legal_name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
            'rut' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'holder' => 'required|string|max:255',
            'create_admin' => ['sometimes', 'boolean'],
            'admin_user_id' => [Rule::prohibitedIf($createAdmin), Rule::requiredIf(! $createAdmin && $request->filled('admin_password')), 'nullable', 'integer'],
            'admin_name' => [Rule::requiredIf($createAdmin || $request->filled('admin_user_id')), 'nullable', 'string', 'max:255'],
            'admin_rut' => ['nullable', 'string', 'max:255'],
            'admin_email' => [Rule::requiredIf($createAdmin || $request->filled('admin_user_id')), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->where('customer_id', $customer->id)->ignore($request->input('admin_user_id'))],
            'admin_phone' => [Rule::requiredIf($createAdmin || $request->filled('admin_user_id')), 'nullable', 'string', 'max:50'],
            'admin_password' => [Rule::requiredIf($createAdmin), 'nullable', 'string', 'min:8', 'confirmed'],

        ], [

            'name.required' => 'El nombre es obligatorio',
            'legal_name.required' => 'La Razón Social es obligatoria',
            'status.required' => 'Debe seleccionar un estado',
            'rut.required' => 'El RUT es obligatorio',
            'rut.unique' => 'El RUT ya existe',
            'email.required' => 'El email es obligatorio',
            'holder.required' => 'El sostenedor es obligatorio',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generar subdominio
        $domain = strtolower($request->name);
        $domain = preg_replace('/[^a-z0-9]/', '', $domain);

        // Validar subdominio excluyendo el registro actual
        $exists = Customer::where('domain', $domain)
            ->where('id', '!=', $customer->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [
                    'name' => ['Ya existe un cliente con ese subdominio.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($customer, $request, $domain, $ids, $document) {
            $customer->newQuery()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $customer->refresh();
            if ($request->boolean('create_admin')) {
                $branch = $customer->branches()->where('status', true)->lockForUpdate()->first();
                if (! $branch) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['admin_user_id' => 'Activa una sede antes de agregar el administrador general.']);
                }
                if ($customer->administratorUsers()->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['admin_user_id' => 'El cliente ya tiene un administrador general. Recarga para editarlo.']);
                }
                $this->createAdministrator($customer, $branch, $request);
            } elseif ($request->filled('admin_user_id')) {
                $admin = $customer->administratorUsers()->lockForUpdate()->findOrFail($request->integer('admin_user_id'));
                $adminDocument = $ids->parse($request, false, $admin->rut, false, 'admin_');
                $data = [
                    'name' => $request->admin_name,
                    ...($adminDocument ? ['rut' => $ids->legacyValue($adminDocument, $admin->rut)] : []),
                    'email' => $request->admin_email, 'phone' => $request->admin_phone,
                ];
                if ($request->filled('admin_password')) {
                    $data['password'] = $request->admin_password;
                }
                $admin->update($data);
                $this->grantGeneralAdministration($customer, $admin);
                $ids->save($admin, $request->user(), $adminDocument, $request, 'admin_');
            }
            $customer->update([
                'name' => $request->name,
                'legal_name' => $request->legal_name,
                ...($document ? ['rut' => $ids->legacyValue($document, $customer->rut)] : []),
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'holder' => $request->holder,
                'domain' => $domain,
                'status' => $request->status,
            ]);
            $ids->save($customer, $request->user(), $document, $request);
            app(\App\Services\CustomerOperatingScope::class)->record($customer, $request->user(), $request);
        });

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    private function createAdministrator(Customer $customer, ClinicBranch $branch, Request $request): void
    {
        $ids = app(\App\Services\Identification\IdentificationForms::class);
        $document = $ids->parse($request, false, null, true, 'admin_');
        app(\App\Http\Controllers\customer\RolePermissionController::class)->ensureClinicRoles((int) $customer->id);
        $role = \App\Models\Role::where('customer_id', $customer->id)
            ->where('name', 'Administrador')->firstOrFail();
        $admin = \App\Models\User::create([
            'customer_id' => $customer->id, 'name' => $request->admin_name, 'rut' => $ids->legacyValue($document),
            'email' => trim($request->admin_email), 'phone' => $request->admin_phone,
            'password' => $request->admin_password, 'status' => 1,
        ]);
        $this->grantGeneralAdministration($customer, $admin);
        $ids->save($admin, $request->user(), $document, $request, 'admin_', true);
    }

    private function grantGeneralAdministration(Customer $customer, \App\Models\User $admin): void
    {
        $role = \App\Models\Role::where('customer_id', $customer->id)->where('name', 'Administrador')->firstOrFail();
        $admin->forceFill(['is_customer_administrator' => true])->save();
        foreach ($customer->branches()->where('status', true)->get() as $assignedBranch) {
            $admin->branches()->syncWithoutDetaching([$assignedBranch->id => ['status' => 1]]);
            if (! $admin->branchRoles()->wherePivot('customer_id', $customer->id)->wherePivot('clinic_branch_id', $assignedBranch->id)->whereKey($role->id)->exists()) {
                $admin->branchRoles()->attach($role->id, ['customer_id' => $customer->id, 'clinic_branch_id' => $assignedBranch->id]);
            }
        }
        $admin->syncClinicRoleMirror((int) $customer->id);
    }

    // cambiar estado
    public function toggleStatus(Request $request, Customer $customer)
    {
        $customer->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    // cambiar estado pago
    public function togglePaymentStatus(Request $request, Customer $customer)
    {
        $isPaid = $request->boolean('payment_status');

        $data = [
            'payment_status' => $isPaid,
        ];

        if ($isPaid) {
            $data['paid_at'] = now();
            $data['expires_at'] = now()->addMonth();
        }

        $customer->update($data);

        return response()->json([
            'success' => true,
        ]);
    }

    // eliminar cliente softdelete (logico)
    public function destroy(Customer $customer)
    {
        $customer->update([
            'status' => 0,
        ]);

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ]);
    }

    // papelera clientes
    public function getTrash()
    {
        return DataTables::of(
            Customer::onlyTrashed()
        )->make(true);
    }

    public function restore($id)
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);

        $customer->restore();

        return response()->json([
            'success' => true,
            'message' => 'Restaurado correctamente',
        ]);
    }
}
