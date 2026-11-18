<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clinic_mail_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->boolean('enabled')->default('1');
            $table->string('from_email', 160);
            $table->string('from_name', 160);
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            $table->string('encryption', 10)->default('tls');
            $table->string('username', 255);
            $table->text('password');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_id'], 'clinic_mail_settings_customer_id_foreign');
            $table->foreign(['customer_id'], 'clinic_mail_settings_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_mail_settings');
    }
};
