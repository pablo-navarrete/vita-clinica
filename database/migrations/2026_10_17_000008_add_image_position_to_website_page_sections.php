<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_page_sections', function (Blueprint $table) {
            $table->string('image_position', 12)->default('inline')->after('image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('website_page_sections', function (Blueprint $table) {
            $table->dropColumn('image_position');
        });
    }
};
