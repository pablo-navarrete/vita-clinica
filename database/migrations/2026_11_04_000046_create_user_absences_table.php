<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_absences', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'user_absences_user_id_foreign');
            $table->index(['created_by'], 'user_absences_created_by_foreign');
            $table->index(['customer_id', 'user_id', 'starts_on', 'ends_on'], 'user_absences_customer_id_user_id_starts_on_ends_on_index');
            $table->foreign(['created_by'], 'user_absences_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'user_absences_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'user_absences_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_absences');
    }
};
