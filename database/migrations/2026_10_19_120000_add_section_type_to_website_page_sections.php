<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_page_sections', function (Blueprint $table) {
            $table->string('section_type', 20)->default('content')->after('website_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('website_page_sections', function (Blueprint $table) {
            $table->dropColumn('section_type');
        });
    }
};
