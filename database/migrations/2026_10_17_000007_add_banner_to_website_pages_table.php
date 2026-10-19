<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_pages', function (Blueprint $table) {
            $table->string('banner_path')->nullable()->after('title');
            $table->string('banner_alt', 160)->nullable()->after('banner_path');
        });
    }

    public function down(): void
    {
        Schema::table('website_pages', function (Blueprint $table) {
            $table->dropColumn(['banner_path', 'banner_alt']);
        });
    }
};
