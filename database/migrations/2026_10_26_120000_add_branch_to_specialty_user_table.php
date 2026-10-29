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
            Schema::table('specialty_user', function (Blueprint $table) {
                $table->foreignId('clinic_branch_id')->nullable()->after('customer_id')
                    ->constrained('clinic_branches')->cascadeOnDelete();
            });
        }

        $legacyIndex = DB::selectOne("SHOW INDEX FROM `specialty_user` WHERE `Key_name` = ?", ['specialty_user_specialty_id_user_id_unique']);
        if ($legacyIndex) {
            // MySQL may be using the composite unique index to back the specialty FK.
            // Keep an index on that FK column before removing the obsolete uniqueness.
            if (!DB::selectOne("SHOW INDEX FROM `specialty_user` WHERE `Key_name` = ?", ['specialty_user_specialty_id_index'])) {
                DB::statement('ALTER TABLE `specialty_user` ADD INDEX `specialty_user_specialty_id_index` (`specialty_id`)');
            }
            DB::statement('ALTER TABLE `specialty_user` DROP INDEX `specialty_user_specialty_id_user_id_unique`');
        }

        $assignments = DB::table('specialty_user')->orderBy('id')->get();
        foreach ($assignments as $assignment) {
            $branchIds = DB::table('clinic_branch_user')
                ->where('user_id', $assignment->user_id)
                ->whereIn('clinic_branch_id', DB::table('clinic_branches')->select('id')->where('customer_id', $assignment->customer_id))
                ->pluck('clinic_branch_id');

            if ($branchIds->isEmpty()) {
                $defaultBranchId = DB::table('clinic_branches')->where('customer_id', $assignment->customer_id)->orderBy('id')->value('id');
                if ($defaultBranchId) $branchIds = collect([$defaultBranchId]);
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

        $branchIndex = DB::selectOne("SHOW INDEX FROM `specialty_user` WHERE `Key_name` = ?", ['specialty_user_branch_unique']);
        if (!$branchIndex) {
            Schema::table('specialty_user', function (Blueprint $table) {
                $table->unique(['specialty_id', 'user_id', 'clinic_branch_id'], 'specialty_user_branch_unique');
            });
        }
    }

    public function down(): void
    {
        $assignments = DB::table('specialty_user')->orderBy('id')->get()->groupBy(fn ($row) => $row->specialty_id . ':' . $row->user_id);
        foreach ($assignments as $rows) {
            $idsToDelete = $rows->slice(1)->pluck('id');
            if ($idsToDelete->isNotEmpty()) DB::table('specialty_user')->whereIn('id', $idsToDelete)->delete();
        }

        Schema::table('specialty_user', function (Blueprint $table) {
            $table->dropUnique('specialty_user_branch_unique');
            $table->dropForeign(['clinic_branch_id']);
            $table->dropColumn('clinic_branch_id');
            $table->unique(['specialty_id', 'user_id']);
        });
    }
};
