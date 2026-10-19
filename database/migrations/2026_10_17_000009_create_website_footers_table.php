<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_footers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('title', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address', 255)->nullable();
            $table->json('social_links')->nullable();
            $table->string('social_display', 12)->default('footer');
            $table->timestamps();
            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_footers');
    }
};
