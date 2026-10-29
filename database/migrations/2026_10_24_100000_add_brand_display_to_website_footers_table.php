<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_footers', function (Blueprint $table) {
            $table->string('brand_display', 10)->default('both')->after('show_social_links');
        });
    }

    public function down(): void
    {
        Schema::table('website_footers', function (Blueprint $table) {
            $table->dropColumn('brand_display');
        });
    }
};
