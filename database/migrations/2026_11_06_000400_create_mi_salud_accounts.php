<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_accounts', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->unique(); $t->string('password');
            $t->timestamp('email_verified_at')->nullable(); $t->boolean('active')->default(true);
            $t->unsignedInteger('session_version')->default(1); $t->timestamps();
        });
        Schema::create('portal_account_identifiers', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $t->char('issuing_country_code', 2); $t->string('document_type', 32); $t->string('identifier_namespace', 64);
            $t->longText('encrypted_payload'); $t->char('lookup_hash', 64)->collation('ascii_bin');
            $t->string('lookup_key_version', 24); $t->string('normalization_version', 32); $t->string('validation_status', 32);
            $t->timestamp('retired_at')->nullable(); $t->timestamps();
            $t->index(['lookup_key_version','lookup_hash'], 'portal_identifier_lookup');
            // Declared documents reserve only this account, not a global identity.
            $t->index(['portal_account_id','lookup_key_version','lookup_hash'], 'portal_identifier_owner_lookup');
            $t->unsignedBigInteger('primary_owner')->nullable()->storedAs('IF(retired_at IS NULL, portal_account_id, NULL)');
            $t->unique('primary_owner');
        });
        Schema::create('portal_auth_tokens', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $t->string('purpose', 16); $t->char('token_hash',64)->collation('ascii_bin')->unique();
            $t->timestamp('expires_at'); $t->timestamp('consumed_at')->nullable(); $t->timestamps();
            $t->index(['portal_account_id','purpose','consumed_at']); $t->index('expires_at');
        });
        Schema::create('portal_audit_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('portal_account_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('event',48); $t->timestamp('created_at')->useCurrent();
            $t->index(['portal_account_id','created_at']);
        });
        DB::statement("ALTER TABLE portal_auth_tokens ADD CONSTRAINT portal_token_purpose CHECK (purpose IN ('verify','reset'))");
        DB::statement("ALTER TABLE portal_account_identifiers ADD CONSTRAINT portal_personal_document CHECK (document_type IN ('national_id','passport','tax_id'))");
        foreach (['update','delete'] as $event) DB::unprepared("CREATE TRIGGER portal_audit_$event BEFORE $event ON portal_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Portal security audit is append only'");
    }
    public function down(): void
    {
        if (DB::table('portal_accounts')->exists() || DB::table('portal_audit_events')->exists()) throw new LogicException('Portal accounts and audit must be retained.');
        foreach (['portal_audit_events','portal_auth_tokens','portal_account_identifiers','portal_accounts'] as $table) Schema::dropIfExists($table);
    }
};
