<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_social_settings', function (Blueprint $table) {
            $table->boolean('show_in_topbar')->default(false)->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('website_social_settings', function (Blueprint $table) {
            $table->dropColumn('show_in_topbar');
        });
    }
};
