<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_topbars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->boolean('show_social_links')->default(false);
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->timestamps();
            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_topbars');
    }
};
