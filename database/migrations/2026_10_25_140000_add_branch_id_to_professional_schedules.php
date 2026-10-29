<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_schedules', function (Blueprint $table) {
            $table->foreignId('clinic_branch_id')->nullable()->after('customer_id')
                ->constrained('clinic_branches')->nullOnDelete();
            $table->index(['customer_id', 'clinic_branch_id', 'user_id', 'day_of_week'], 'professional_schedules_branch_lookup');
        });

        $legacyScheduleMaxId = DB::table('professional_schedules')->max('id') ?? 0;
        DB::table('professional_schedules')->where('id', '<=', $legacyScheduleMaxId)->orderBy('id')->chunkById(200, function ($schedules) {
            foreach ($schedules as $schedule) {
                $branchIds = DB::table('clinic_branch_user')
                    ->join('clinic_branches', 'clinic_branches.id', '=', 'clinic_branch_user.clinic_branch_id')
                    ->where('clinic_branch_user.user_id', $schedule->user_id)
                    ->where('clinic_branches.customer_id', $schedule->customer_id)
                    ->whereNull('clinic_branches.deleted_at')
                    ->orderBy('clinic_branches.id')
                    ->pluck('clinic_branches.id');

                if ($branchIds->isEmpty()) {
                    $fallbackBranchId = DB::table('clinic_branches')->where('customer_id', $schedule->customer_id)
                        ->where('status', true)->whereNull('deleted_at')->orderBy('id')->value('id');
                    $branchIds = $fallbackBranchId ? collect([$fallbackBranchId]) : collect();
                }

                if ($branchIds->isEmpty()) {
                    continue;
                }

                $firstBranchId = $branchIds->shift();
                DB::table('professional_schedules')->where('id', $schedule->id)->update(['clinic_branch_id' => $firstBranchId]);

                foreach ($branchIds as $branchId) {
                    DB::table('professional_schedules')->insert([
                        'customer_id' => $schedule->customer_id,
                        'clinic_branch_id' => $branchId,
                        'user_id' => $schedule->user_id,
                        'day_of_week' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'created_at' => $schedule->created_at,
                        'updated_at' => $schedule->updated_at,
                    ]);
                }
            }
        });

        if (Schema::hasColumn('appointments', 'clinic_branch_id')) {
            DB::table('appointments')->whereNull('clinic_branch_id')->orderBy('id')->chunkById(200, function ($appointments) {
                foreach ($appointments as $appointment) {
                    $branchId = DB::table('professional_schedules')
                        ->where('customer_id', $appointment->customer_id)
                        ->where('user_id', $appointment->professional_id)
                        ->whereRaw('day_of_week = DAYOFWEEK(?) - 1', [$appointment->start_at])
                        ->whereRaw('start_time <= TIME(?)', [$appointment->start_at])
                        ->whereRaw('end_time >= TIME(?)', [$appointment->end_at])
                        ->orderBy('clinic_branch_id')
                        ->value('clinic_branch_id');
                    if (!$branchId) {
                        $branchId = DB::table('patients')->where('id', $appointment->patient_id)->value('clinic_branch_id');
                    }
                    if (!$branchId) {
                        $branchId = DB::table('clinic_branch_user')
                            ->join('clinic_branches', 'clinic_branches.id', '=', 'clinic_branch_user.clinic_branch_id')
                            ->where('clinic_branch_user.user_id', $appointment->professional_id)
                            ->where('clinic_branches.customer_id', $appointment->customer_id)
                            ->where('clinic_branches.status', true)
                            ->whereNull('clinic_branches.deleted_at')
                            ->orderBy('clinic_branches.id')->value('clinic_branches.id');
                    }
                    if (!$branchId) {
                        $branchId = DB::table('clinic_branches')->where('customer_id', $appointment->customer_id)
                            ->where('status', true)->whereNull('deleted_at')->orderBy('id')->value('id');
                    }
                    if ($branchId) {
                        DB::table('appointments')->where('id', $appointment->id)->update(['clinic_branch_id' => $branchId]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('professional_schedules', function (Blueprint $table) {
            $table->dropIndex('professional_schedules_branch_lookup');
            $table->dropConstrainedForeignId('clinic_branch_id');
        });
    }
};
