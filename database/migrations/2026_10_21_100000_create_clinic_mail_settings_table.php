<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('enabled')->default(true);
            $table->string('from_email', 160);
            $table->string('from_name', 160);
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            $table->string('encryption', 10)->default('tls');
            $table->string('username', 255);
            $table->text('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_mail_settings');
    }
};
