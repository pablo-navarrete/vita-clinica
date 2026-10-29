<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->string('modality', 20)->default('in_person')->after('specialty_id');
            $table->foreignId('telemedicine_session_id')->nullable()->after('modality')
                ->constrained('telemedicine_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropForeign(['telemedicine_session_id']);
            $table->dropColumn(['telemedicine_session_id', 'modality']);
        });
    }
};
