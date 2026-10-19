<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('professional_id')->nullable()->after('appointment_id')
                ->constrained('users')->nullOnDelete();
            $table->longText('clinical_assessment')->nullable()->after('chief_complaint');
            $table->longText('treatment_plan')->nullable()->after('clinical_assessment');
            $table->text('cancellation_reason')->nullable()->after('treatment_plan');
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropForeign(['professional_id']);
            $table->dropColumn(['professional_id', 'clinical_assessment', 'treatment_plan', 'cancellation_reason']);
        });
    }
};
