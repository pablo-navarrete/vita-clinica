<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('encounters')->whereNull('clinic_branch_id')->orderBy('id')->chunkById(200, function ($encounters) {
            foreach ($encounters as $encounter) {
                $authorId = $encounter->opened_by ?? $encounter->professional_id;
                if (!$authorId || !$encounter->created_at) {
                    continue;
                }
                $createdAt = Carbon::parse($encounter->created_at);
                $logs = DB::table('patient_audit_logs')
                    ->where('customer_id', $encounter->customer_id)
                    ->where('patient_id', $encounter->patient_id)
                    ->where('user_id', $authorId)->where('action', 'started')
                    ->whereBetween('created_at', [$createdAt, $createdAt->copy()->addSeconds(5)])
                    ->get();
                if ($logs->count() !== 1 || !$logs->first()->clinic_branch_id) {
                    continue;
                }
                $log = $logs->first();
                // Only recover an unambiguous start event for this patient and author.
                $matches = DB::table('encounters')->where('customer_id', $encounter->customer_id)
                    ->where('patient_id', $encounter->patient_id)
                    ->whereRaw('COALESCE(opened_by, professional_id) = ?', [$authorId])
                    ->whereBetween('created_at', [Carbon::parse($log->created_at)->subSeconds(5), $log->created_at])->count();
                if ($matches === 1 && DB::table('clinic_branches')->where('customer_id', $encounter->customer_id)->where('id', $log->clinic_branch_id)->exists()) {
                    DB::table('encounters')->where('id', $encounter->id)->update(['clinic_branch_id' => $log->clinic_branch_id]);
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve recovered historical data.
    }
};
