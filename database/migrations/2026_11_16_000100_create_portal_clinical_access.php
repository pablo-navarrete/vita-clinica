<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_clinical_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('link_id')->constrained('portal_patient_links')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('clinic_branch_id')->constrained()->restrictOnDelete();
            $table->string('account_reference', 64);
            $table->string('patient_reference', 64);
            $table->string('lookup_key_version', 24);
            $table->char('identity_hash', 64)->collation('ascii_bin');
            $table->string('verification_method', 24);
            $table->string('authorization_version', 40);
            $table->timestamp('approved_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('active_pair', 64)->nullable()->storedAs("CASE WHEN revoked_at IS NULL THEN CONCAT(portal_account_id, ':', patient_id) ELSE NULL END");
            $table->unique('active_pair');
            $table->timestamps();
            $table->index(['portal_account_id','revoked_at']);
        });
        Schema::create('portal_clinical_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('grant_id');
            $table->foreign('grant_id')->references('id')->on('portal_clinical_grants')->restrictOnDelete();
            $table->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->foreignId('encounter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('patient_documents')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['grant_id','created_at']);
        });
        DB::statement("ALTER TABLE portal_clinical_grants ADD CONSTRAINT portal_clinical_method CHECK (verification_method IN ('in_person','verified_contact'))");
        DB::statement("ALTER TABLE portal_clinical_grants ADD CONSTRAINT portal_clinical_revocation CHECK ((revoked_at IS NULL AND revoked_by IS NULL) OR (revoked_at IS NOT NULL AND revoked_by IS NOT NULL))");
        DB::unprepared("CREATE TRIGGER portal_clinical_grant_insert BEFORE INSERT ON portal_clinical_grants FOR EACH ROW BEGIN
            IF NOT EXISTS (SELECT 1 FROM portal_patient_links WHERE id=NEW.link_id AND customer_id=NEW.customer_id AND patient_id=NEW.patient_id AND portal_account_id=NEW.portal_account_id AND status='candidate' AND account_reference=NEW.account_reference AND patient_reference=NEW.patient_reference)
            OR NOT EXISTS (SELECT 1 FROM users WHERE id=NEW.approved_by AND customer_id=NEW.customer_id AND status=1)
            OR NOT EXISTS (SELECT 1 FROM clinic_branches WHERE id=NEW.clinic_branch_id AND customer_id=NEW.customer_id AND status=1)
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Clinical grant context mismatch'; END IF;
        END");
        DB::unprepared("CREATE TRIGGER portal_clinical_grant_update BEFORE UPDATE ON portal_clinical_grants FOR EACH ROW BEGIN
            IF OLD.revoked_at IS NOT NULL OR NEW.revoked_at IS NULL OR NEW.revoked_by IS NULL
            OR NOT(NEW.id <=> OLD.id) OR NOT(NEW.customer_id <=> OLD.customer_id) OR NOT(NEW.patient_id <=> OLD.patient_id)
            OR NOT(NEW.portal_account_id <=> OLD.portal_account_id) OR NOT(NEW.link_id <=> OLD.link_id)
            OR NOT(NEW.approved_by <=> OLD.approved_by) OR NOT(NEW.clinic_branch_id <=> OLD.clinic_branch_id)
            OR NOT(NEW.account_reference <=> OLD.account_reference) OR NOT(NEW.patient_reference <=> OLD.patient_reference)
            OR NOT(NEW.lookup_key_version <=> OLD.lookup_key_version) OR NOT(NEW.identity_hash <=> OLD.identity_hash)
            OR NOT(NEW.verification_method <=> OLD.verification_method) OR NOT(NEW.authorization_version <=> OLD.authorization_version)
            OR NOT(NEW.approved_at <=> OLD.approved_at) OR NOT(NEW.created_at <=> OLD.created_at)
            OR NOT EXISTS (SELECT 1 FROM users WHERE id=NEW.revoked_by AND customer_id=NEW.customer_id AND status=1)
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Clinical grant evidence is immutable'; END IF;
        END");
        DB::unprepared("CREATE TRIGGER portal_clinical_grant_delete BEFORE DELETE ON portal_clinical_grants FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Clinical grant history is retained'");
        DB::unprepared("CREATE TRIGGER portal_clinical_event_insert BEFORE INSERT ON portal_clinical_events FOR EACH ROW BEGIN
            IF NOT EXISTS (SELECT 1 FROM portal_clinical_grants g WHERE g.id=NEW.grant_id AND g.portal_account_id=NEW.portal_account_id
                AND (NEW.actor_id IS NULL OR EXISTS (SELECT 1 FROM users WHERE id=NEW.actor_id AND customer_id=g.customer_id))
                AND (NEW.encounter_id IS NULL OR EXISTS (SELECT 1 FROM encounters WHERE id=NEW.encounter_id AND patient_id=g.patient_id AND customer_id=g.customer_id))
                AND (NEW.document_id IS NULL OR EXISTS (SELECT 1 FROM patient_documents WHERE id=NEW.document_id AND patient_id=g.patient_id AND customer_id=g.customer_id AND encounter_id=NEW.encounter_id)))
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Clinical audit context mismatch'; END IF;
        END");
        foreach (['UPDATE','DELETE'] as $event) DB::unprepared("CREATE TRIGGER portal_clinical_event_".strtolower($event)." BEFORE $event ON portal_clinical_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Clinical access audit is immutable'");
    }

    public function down(): void
    {
        if (DB::table('portal_clinical_grants')->exists()) throw new RuntimeException('Clinical access history must be archived explicitly before rollback.');
        Schema::dropIfExists('portal_clinical_events');
        Schema::dropIfExists('portal_clinical_grants');
    }
};
