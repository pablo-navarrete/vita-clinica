<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_navigation_buttons', function (Blueprint $table) {
            $table->boolean('public_booking_enabled')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('website_navigation_buttons', function (Blueprint $table) {
            $table->dropColumn('public_booking_enabled');
        });
    }
};
