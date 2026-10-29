<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('specialty_user', 'clinic_branch_id')) {
            return;
        }

        $legacyIndex = DB::selectOne("SHOW INDEX FROM `specialty_user` WHERE `Key_name` = ?", ['specialty_user_specialty_id_user_id_unique']);
        if ($legacyIndex) {
            // Preserve an index for the specialty foreign key; MySQL can select the
            // obsolete composite unique index as its supporting index.
            if (!DB::selectOne("SHOW INDEX FROM `specialty_user` WHERE `Key_name` = ?", ['specialty_user_specialty_id_index'])) {
                DB::statement('ALTER TABLE `specialty_user` ADD INDEX `specialty_user_specialty_id_index` (`specialty_id`)');
            }
            DB::statement('ALTER TABLE `specialty_user` DROP INDEX `specialty_user_specialty_id_user_id_unique`');

            foreach (DB::table('specialty_user')->orderBy('id')->get() as $assignment) {
                $branchIds = DB::table('clinic_branch_user')
                    ->where('user_id', $assignment->user_id)
                    ->whereIn('clinic_branch_id', DB::table('clinic_branches')->select('id')->where('customer_id', $assignment->customer_id))
                    ->orderBy('clinic_branch_id')->pluck('clinic_branch_id');

                if ($branchIds->isEmpty()) {
                    $fallback = $assignment->clinic_branch_id
                        ?: DB::table('clinic_branches')->where('customer_id', $assignment->customer_id)->orderBy('id')->value('id');
                    if ($fallback) $branchIds = collect([$fallback]);
                }

                foreach ($branchIds->values() as $index => $branchId) {
                    if ($index === 0) {
                        DB::table('specialty_user')->where('id', $assignment->id)->update(['clinic_branch_id' => $branchId]);
                        continue;
                    }
                    DB::table('specialty_user')->insertOrIgnore([
                        'specialty_id' => $assignment->specialty_id,
                        'user_id' => $assignment->user_id,
                        'customer_id' => $assignment->customer_id,
                        'clinic_branch_id' => $branchId,
                        'created_at' => $assignment->created_at,
                        'updated_at' => $assignment->updated_at,
                    ]);
                }
            }
        }

        if (!DB::selectOne("SHOW INDEX FROM `specialty_user` WHERE `Key_name` = ?", ['specialty_user_branch_unique'])) {
            Schema::table('specialty_user', function (Blueprint $table) {
                $table->unique(['specialty_id', 'user_id', 'clinic_branch_id'], 'specialty_user_branch_unique');
            });
        }
    }

    public function down(): void
    {
        // This repair only removes the obsolete uniqueness rule; the branch-aware index
        // and assignments are managed by the original branch-specialty migration.
    }
};
