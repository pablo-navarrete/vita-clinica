<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_footers', function (Blueprint $table) {
            $table->boolean('show_social_links')->default(true)->after('enabled');
            $table->dropColumn(['social_links', 'social_display']);
        });

        Schema::table('website_social_settings', function (Blueprint $table) {
            $table->dropColumn('display_location');
        });
    }

    public function down(): void
    {
        Schema::table('website_footers', function (Blueprint $table) {
            $table->json('social_links')->nullable();
            $table->string('social_display', 12)->default('footer');
            $table->dropColumn('show_social_links');
        });

        Schema::table('website_social_settings', function (Blueprint $table) {
            $table->string('display_location', 12)->default('footer');
        });

        DB::table('website_social_settings')->orderBy('customer_id')->get()->each(function ($settings) {
            DB::table('website_footers')->where('customer_id', $settings->customer_id)->update([
                'social_links' => $settings->social_links,
                'social_display' => 'footer',
            ]);
        });
    }
};
