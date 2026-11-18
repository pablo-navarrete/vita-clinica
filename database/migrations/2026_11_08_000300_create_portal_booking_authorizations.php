<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_booking_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('portal_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('session_hash', 64)->collation('ascii_bin');
            $table->char('code_hash', 64)->nullable()->collation('ascii_bin');
            $table->char('identity_hash', 64)->nullable()->collation('ascii_bin');
            $table->uuid('registration_operation')->nullable()->unique();
            $table->text('encrypted_payload');
            $table->timestamp('expires_at')->index();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'portal_account_id', 'patient_id'], 'portal_booking_owner');
        });
    }
    public function down(): void
    {
        if (Schema::hasTable('portal_booking_authorizations') && DB::table('portal_booking_authorizations')->exists()) {
            throw new RuntimeException('Booking authorization evidence must be retained.');
        }
        Schema::dropIfExists('portal_booking_authorizations');
    }
};
