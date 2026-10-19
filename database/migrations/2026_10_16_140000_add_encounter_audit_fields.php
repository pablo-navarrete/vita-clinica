<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('opened_by')->nullable()->after('professional_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('opened_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropForeign(['opened_by']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['opened_by', 'created_by']);
        });
    }
};
