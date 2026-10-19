<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('slug', 140);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'slug']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_pages');
    }
};
