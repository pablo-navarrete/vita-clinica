<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('directory_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('public_name', 60);
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->timestamp('publication_consented_at');
            $table->string('consent_version', 40);
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['portal_account_id', 'customer_id'], 'directory_review_account_clinic');
            $table->index(['customer_id', 'withdrawn_at', 'created_at'], 'directory_review_public');
        });
        Schema::create('directory_review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directory_review_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('author_name');
            $table->string('author_role', 24);
            $table->text('body');
            $table->timestamps();
            $table->unique(['directory_review_id', 'user_id'], 'directory_review_reply_author');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_review_replies');
        Schema::dropIfExists('directory_reviews');
    }
};
