<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_branch_id')->constrained('clinic_branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['clinic_branch_id', 'user_id']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('clinic_branch_id')->nullable()->after('customer_id')
                ->constrained('clinic_branches')->nullOnDelete();
        });

        DB::table('patients')->whereNull('clinic_branch_id')->orderBy('id')->chunkById(200, function ($patients) {
            foreach ($patients as $patient) {
                $branchId = DB::table('appointments')
                    ->where('patient_id', $patient->id)
                    ->whereNotNull('clinic_branch_id')
                    ->orderByDesc('start_at')
                    ->value('clinic_branch_id');

                if ($branchId) {
                    DB::table('patients')->where('id', $patient->id)->update(['clinic_branch_id' => $branchId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_branch_id');
        });

        Schema::dropIfExists('clinic_branch_user');
    }
};
