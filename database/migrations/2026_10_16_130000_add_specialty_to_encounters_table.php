<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('encounters', 'specialty_id')) {
            return;
        }

        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('specialty_id')->nullable()->after('professional_id')
                ->constrained('specialties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('encounters', 'specialty_id')) {
            return;
        }

        Schema::table('encounters', function (Blueprint $table) {
            $table->dropForeign(['specialty_id']);
            $table->dropColumn('specialty_id');
        });
    }
};
