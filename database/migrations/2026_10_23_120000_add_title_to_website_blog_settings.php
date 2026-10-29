<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_blog_settings', function (Blueprint $table) {
            $table->string('title', 80)->default('Blog')->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('website_blog_settings', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
