<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            // Historical encounters without evidence retain a null branch.
            $table->foreignId('clinic_branch_id')->nullable()->after('customer_id')
                ->constrained('clinic_branches')->restrictOnDelete();
        });

        DB::table('encounters')->whereNull('clinic_branch_id')->orderBy('id')->chunkById(200, function ($encounters) {
            foreach ($encounters as $encounter) {
                $branchId = DB::table('appointments')
                    ->where('id', $encounter->appointment_id)
                    ->where('customer_id', $encounter->customer_id)
                    ->where('patient_id', $encounter->patient_id)
                    ->value('clinic_branch_id');
                if ($branchId) {
                    DB::table('encounters')->where('id', $encounter->id)->update(['clinic_branch_id' => $branchId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('encounters', fn (Blueprint $table) => $table->dropConstrainedForeignId('clinic_branch_id'));
    }
};
