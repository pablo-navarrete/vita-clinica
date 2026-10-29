<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_branch_user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['clinic_branch_id', 'user_id', 'role_id'], 'branch_user_role_unique');
            $table->index(['customer_id', 'user_id', 'clinic_branch_id'], 'branch_user_role_scope_idx');
        });

        // Conserva las asignaciones actuales, distribuyéndolas en las sedes que ya tenía cada usuario.
        DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->whereNotNull('roles.customer_id')
            ->whereColumn('roles.customer_id', 'users.customer_id')
            ->select('roles.customer_id', 'role_user.user_id', 'role_user.role_id')
            ->orderBy('role_user.user_id')->orderBy('role_user.role_id')
            ->chunk(500, function ($assignments) {
                foreach ($assignments as $assignment) {
                    $branchIds = DB::table('clinic_branch_user')
                        ->join('clinic_branches', 'clinic_branches.id', '=', 'clinic_branch_user.clinic_branch_id')
                        ->where('user_id', $assignment->user_id)
                        ->where('clinic_branches.customer_id', $assignment->customer_id)
                        ->pluck('clinic_branch_user.clinic_branch_id');
                    foreach ($branchIds as $branchId) {
                        DB::table('clinic_branch_user_role')->insertOrIgnore([
                            'customer_id' => $assignment->customer_id,
                            'clinic_branch_id' => $branchId,
                            'user_id' => $assignment->user_id,
                            'role_id' => $assignment->role_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

        // role_user se conserva como índice de compatibilidad para listados existentes;
        // la asignación autorizante vive desde ahora en clinic_branch_user_role.
    }

    public function down(): void
    {
        DB::table('clinic_branch_user_role')->orderBy('id')->chunk(500, function ($assignments) {
            foreach ($assignments as $assignment) {
                DB::table('role_user')->insertOrIgnore([
                    'user_id' => $assignment->user_id,
                    'role_id' => $assignment->role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
        Schema::dropIfExists('clinic_branch_user_role');
    }
};
