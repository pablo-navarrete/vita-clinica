<?php

namespace App\Services;

use App\Models\{Customer, Specialty, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpecialtyCatalog
{
    public function name(?int $id): ?string
    {
        return config('specialties.catalog', [])[$id] ?? null;
    }

    // Negative form values distinguish fixed catalog IDs from historical local database IDs.
    // Database relationships ALWAYS keep the real, positive local specialty_id.
    public function selection(Specialty $specialty): int
    {
        return $specialty->catalog_id && $this->name($specialty->catalog_id)
            ? -(int) $specialty->catalog_id : (int) $specialty->id;
    }

    public function options(?User $user = null, ?int $customerId = null, ?int $branchId = null)
    {
        $options = collect(config('specialties.catalog', []))->map(fn ($name, $id) => (object) ['id' => -(int) $id, 'name' => $name])->values();
        if ($user && $customerId && $branchId) {
            // Unknown legacy assignments can be retained or removed, never newly assigned.
            foreach ($user->specialtiesForBranch($customerId, $branchId)->whereNull('catalog_id')->get() as $s) {
                $options->push((object) ['id' => (int) $s->id, 'name' => $s->name.' (histórica)']);
            }
        }
        return $options->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function sync(User $user, int $customerId, int $branchId, array $selections): void
    {
        abort_unless((int) $user->customer_id === $customerId, 403);
        DB::transaction(function () use ($user, $customerId, $branchId, $selections) {
            // Serialize lazy local creation AND assignments, including different professionals.
            Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->branches()->where('clinic_branches.customer_id', $customerId)
                ->where('clinic_branches.status', 1)->where('clinic_branch_user.status', 1)->whereKey($branchId)->exists(), 403);
            $existing = $user->specialtiesForBranch($customerId, $branchId)->get()->keyBy('id');
            $ids = [];
            foreach (array_unique($selections) as $value) {
                if (!is_numeric($value) || (string)(int)$value !== (string)$value) $this->invalid();
                $value = (int) $value;
                if ($value < 0 && ($name = $this->name(-$value))) {
                    // Preserve an existing assigned duplicate rather than rewriting its identity.
                    $local = $existing->first(fn ($s) => (int) $s->catalog_id === -$value && $s->status)
                        ?? Specialty::where('customer_id', $customerId)->where('catalog_id', -$value)->where('status', 1)->orderBy('id')->first();
                    $local ??= Specialty::create(['customer_id' => $customerId, 'name' => $name, 'status' => true, 'catalog_id' => -$value]);
                    $ids[] = $local->id;
                } elseif ($value > 0 && isset($existing[$value]) && !$existing[$value]->catalog_id) {
                    $ids[] = $value;
                } else $this->invalid();
            }
            // Keep all duplicate historical assignments representing the selected category.
            $selectedCatalog = array_map(fn ($v) => -(int)$v, array_filter($selections, fn ($v) => (int)$v < 0));
            foreach ($existing as $s) if ($s->catalog_id && in_array((int)$s->catalog_id, $selectedCatalog, true)) $ids[] = $s->id;
            $query = DB::table('specialty_user')->where('customer_id', $customerId)->where('user_id', $user->id)->where('clinic_branch_id', $branchId);
            (clone $query)->whereNotIn('specialty_id', $ids)->delete();
            foreach (array_unique($ids) as $id) {
                if (!$existing->has($id)) DB::table('specialty_user')->insert(['customer_id' => $customerId,
                    'user_id' => $user->id, 'clinic_branch_id' => $branchId, 'specialty_id' => $id,
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }, 3);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['specialties' => 'Selecciona especialidades válidas del catálogo de VITA.']);
    }

    public function offered(int $customerId, ?int $branchId = null)
    {
        return $this->offeredQuery($customerId, $branchId)->get()->unique('catalog_id')->map(function ($s) {
            $s->name = $this->name($s->catalog_id); return $s;
        })->sortBy('name')->values();
    }

    // The same operational rules serve individual clinics and set-based directory queries.
    public function offeredQuery(?int $customerId = null, ?int $branchId = null)
    {
        return Specialty::query()->when($customerId !== null, fn ($q) => $q->where('specialties.customer_id', $customerId))
            ->where('public_visible', true)->where('status', 1)
            ->whereIn('catalog_id', array_keys(config('specialties.catalog', [])))
            ->whereExists(function ($q) use ($customerId, $branchId) {
                $q->selectRaw('1')->from('specialty_user as su')
                    ->join('users as u', 'u.id', '=', 'su.user_id')
                    ->join('clinic_branches as b', 'b.id', '=', 'su.clinic_branch_id')
                    ->join('clinic_branch_user as membership', function ($join) {
                        $join->on('membership.user_id', '=', 'su.user_id')->on('membership.clinic_branch_id', '=', 'su.clinic_branch_id');
                    })
                    ->whereColumn('su.specialty_id', 'specialties.id')->whereColumn('su.customer_id', 'specialties.customer_id')
                    ->whereColumn('u.customer_id', 'specialties.customer_id')->where('u.status', 1)
                    ->whereNull('u.deleted_at')->whereNull('b.deleted_at')
                    ->whereColumn('b.customer_id', 'specialties.customer_id')->where('b.status', 1)->where('membership.status', 1)
                    ->when($branchId, fn ($q) => $q->where('b.id', $branchId));
                // Membership/branch validity is already checked above. Avoid joining it twice.
                $q->whereExists(function ($roles) {
                    $roles->selectRaw('1')->from('clinic_branch_user_role as offered_assignment')
                        ->join('roles as offered_role', 'offered_role.id', '=', 'offered_assignment.role_id')
                        ->whereColumn('offered_assignment.user_id','su.user_id')
                        ->whereColumn('offered_assignment.clinic_branch_id','su.clinic_branch_id')
                        ->whereColumn('offered_assignment.customer_id','specialties.customer_id')
                        ->whereColumn('offered_role.customer_id','specialties.customer_id')
                        ->where(function ($role) {
                            $role->whereRaw('LOWER(TRIM(offered_role.name)) = ?', ['doctor'])
                                ->orWhereExists(function ($permissions) {
                                    $permissions->selectRaw('1')->from('permission_role')
                                        ->join('permissions','permissions.id','=','permission_role.permission_id')
                                        ->whereColumn('permission_role.role_id','offered_role.id')
                                        ->where('permissions.slug','medical_records.manage');
                                });
                        });
                });
            });
    }

    public function equivalentIds(int $customerId, int $localId): array
    {
        $local = Specialty::where('customer_id', $customerId)->where('public_visible', 1)->where('status', 1)->find($localId);
        if (!$local || !$this->name($local->catalog_id)) return [];
        return Specialty::where('customer_id', $customerId)->where('catalog_id', $local->catalog_id)
            ->where('public_visible', 1)->where('status', 1)->pluck('id')->all();
    }

    public function publicBookingAssignment($query, ?int $customerId, string $assignmentTable = 'specialty_user'): void
    {
        $query->whereExists(function ($q) use ($customerId, $assignmentTable) {
            $q->selectRaw('1')->from('clinic_branch_user as active_member')
                ->join('clinic_branches as active_branch', 'active_branch.id', '=', 'active_member.clinic_branch_id')
                ->join('clinic_branch_user_role as br', function ($join) {
                    $join->on('br.user_id', '=', 'active_member.user_id')->on('br.clinic_branch_id', '=', 'active_member.clinic_branch_id');
                })
                ->join('roles as booking_role', 'booking_role.id', '=', 'br.role_id')
                ->whereColumn('active_member.user_id', $assignmentTable.'.user_id')
                ->whereColumn('active_member.clinic_branch_id', $assignmentTable.'.clinic_branch_id')
                ->where('active_member.status', 1)->where('active_branch.status', 1)
                ->whereNull('active_branch.deleted_at')
                ->when($customerId !== null,
                    fn ($q) => $q->where('active_branch.customer_id', $customerId)->where('br.customer_id', $customerId)->where('booking_role.customer_id', $customerId),
                    fn ($q) => $q->whereColumn('active_branch.customer_id', 'specialties.customer_id')
                        ->whereColumn('br.customer_id', 'specialties.customer_id')->whereColumn('booking_role.customer_id', 'specialties.customer_id'))
                ->where(function ($roles) {
                    $roles->whereRaw('LOWER(TRIM(booking_role.name)) = ?', ['doctor'])
                        ->orWhereExists(function ($permissions) {
                            $permissions->selectRaw('1')->from('permission_role')
                                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                                ->whereColumn('permission_role.role_id', 'booking_role.id')
                                ->where('permissions.slug', 'medical_records.manage');
                        });
                });
        });
    }
}
