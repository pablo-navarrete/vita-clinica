<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_link_mutex', function (Blueprint $t) { $t->unsignedInteger('id')->primary(); });
        DB::table('portal_link_mutex')->insert(['id'=>1]);
        Schema::create('portal_document_lookup_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('portal_account_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('patient_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('source_reference',64); $t->string('key_version',24);
            $t->char('document_hash',64)->collation('ascii_bin'); $t->timestamps();
            $t->index(['key_version','document_hash'],'portal_document_match');
            $t->unique(['portal_account_id','key_version'],'portal_document_account_version');
            $t->unique(['patient_id','key_version'],'portal_document_patient_version');
        });
        DB::statement('ALTER TABLE portal_document_lookup_keys ADD CONSTRAINT portal_lookup_owner CHECK ((portal_account_id IS NOT NULL AND patient_id IS NULL AND customer_id IS NULL) OR (portal_account_id IS NULL AND patient_id IS NOT NULL AND customer_id IS NOT NULL))');
        Schema::create('portal_patient_links', function (Blueprint $t) {
            $t->id(); $t->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $t->foreignId('patient_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('status',24); $t->string('account_reference',64); $t->string('patient_reference',64);
            $t->string('source',32)->default('document_match'); $t->boolean('clinical_access')->default(false);
            $t->timestamps(); $t->unique(['portal_account_id','patient_id'],'portal_patient_pair');
            $t->index(['customer_id','patient_id','status']);
        });
        DB::statement("ALTER TABLE portal_patient_links ADD CONSTRAINT portal_link_state CHECK (status IN ('candidate','review_required','revoked'))");
        DB::statement('ALTER TABLE portal_patient_links ADD CONSTRAINT portal_link_no_clinical_access CHECK (clinical_access = 0)');
        Schema::create('portal_patient_link_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('link_id')->constrained('portal_patient_links')->restrictOnDelete();
            $t->string('event',32); $t->string('previous_status',24)->nullable(); $t->string('status',24);
            $t->string('account_reference',64); $t->string('patient_reference',64); $t->timestamp('created_at');
        });
        Schema::create('portal_link_sync_requests', function (Blueprint $t) {
            $t->id(); $t->string('subject_kind',16); $t->unsignedBigInteger('subject_id');
            $t->timestamp('completed_at')->nullable(); $t->timestamps(); $t->index(['completed_at','id']);
        });
        foreach (['portal_document_lookup_keys','portal_patient_links'] as $table) {
            foreach (['INSERT','UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER {$table}_".strtolower($event)." BEFORE $event ON $table FOR EACH ROW BEGIN IF NEW.patient_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM patients WHERE id=NEW.patient_id AND customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Patient tenant mismatch'; END IF; END");
            }
        }
        foreach (['UPDATE','DELETE'] as $event) DB::unprepared("CREATE TRIGGER portal_link_event_".strtolower($event)." BEFORE $event ON portal_patient_link_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable link audit'");
    }

    public function down(): void
    {
        if (DB::table('portal_patient_links')->exists() || DB::table('portal_link_sync_requests')->whereNull('completed_at')->exists()) throw new RuntimeException('Link history or pending requests exist; rollback requires explicit archival.');
        foreach (['portal_link_sync_requests','portal_patient_link_events','portal_patient_links','portal_document_lookup_keys','portal_link_mutex'] as $table) Schema::dropIfExists($table);
    }
};
