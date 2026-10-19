<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_social_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->json('social_links')->nullable();
            $table->string('display_location', 12)->default('footer');
            $table->timestamps();
            $table->unique('customer_id');
        });

        if (Schema::hasTable('website_footers')) {
            DB::table('website_footers')->orderBy('customer_id')->get()->each(function ($footer) {
                DB::table('website_social_settings')->insert([
                    'customer_id' => $footer->customer_id,
                    'social_links' => $footer->social_links,
                    'display_location' => $footer->social_display ?: 'footer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_social_settings');
    }
};
