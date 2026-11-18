<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patient_registration_attempts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->uuid('operation_id')->primary();
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('clinic_branch_id')->constrained('clinic_branches')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('channel', 24);
            $table->string('status', 32);
            $table->longText('encrypted_payload')->nullable();
            $table->string('lookup_key_version', 24);
            $table->char('document_hash', 64)->collation('ascii_bin');
            $table->char('request_hash', 64)->collation('ascii_bin');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->unsignedSmallInteger('verification_attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('contact_verified_at')->nullable();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'channel', 'status']);
            $table->index(['patient_id', 'status', 'contact_verified_at'], 'registration_trusted_contact');
            $table->index(['status', 'expires_at']);
        });
        DB::statement("ALTER TABLE patient_registration_attempts ADD CONSTRAINT registration_channel CHECK (channel IN ('internal','viti','public_booking'))");
        DB::statement("ALTER TABLE patient_registration_attempts ADD CONSTRAINT registration_status CHECK (status IN ('pending','processing','completed','review','unavailable','expired','delivery_failed'))");
        DB::statement("ALTER TABLE patient_registration_attempts ADD CONSTRAINT registration_actor CHECK ((channel='public_booking' AND actor_id IS NULL) OR (channel IN ('internal','viti') AND actor_id IS NOT NULL))");
        DB::statement("ALTER TABLE patient_registration_attempts ADD CONSTRAINT registration_result CHECK (status<>'completed' OR (patient_id IS NOT NULL AND (channel<>'public_booking' OR appointment_id IS NOT NULL)))");
        $tenantCheck = "IF NOT EXISTS (SELECT 1 FROM clinic_branches WHERE id=NEW.clinic_branch_id AND customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registration branch mismatch'; END IF;
            IF NEW.actor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users WHERE id=NEW.actor_id AND customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registration actor mismatch'; END IF;
            IF NEW.patient_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM patients WHERE id=NEW.patient_id AND customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registration patient mismatch'; END IF;
            IF NEW.appointment_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM appointments WHERE id=NEW.appointment_id AND customer_id=NEW.customer_id AND patient_id=NEW.patient_id AND clinic_branch_id=NEW.clinic_branch_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registration appointment mismatch'; END IF;";
        DB::unprepared("CREATE TRIGGER vita_registration_insert BEFORE INSERT ON patient_registration_attempts FOR EACH ROW BEGIN $tenantCheck END");
        DB::unprepared("CREATE TRIGGER vita_registration_update BEFORE UPDATE ON patient_registration_attempts FOR EACH ROW BEGIN
            IF OLD.status='completed' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Completed registration evidence is immutable'; END IF;
            IF NOT (NEW.operation_id <=> OLD.operation_id) OR NOT (NEW.customer_id <=> OLD.customer_id) OR NOT (NEW.clinic_branch_id <=> OLD.clinic_branch_id)
                OR NOT (NEW.actor_id <=> OLD.actor_id) OR NOT (NEW.channel <=> OLD.channel) OR NOT (NEW.document_hash <=> OLD.document_hash)
                OR NOT (NEW.request_hash <=> OLD.request_hash) OR NOT (NEW.lookup_key_version <=> OLD.lookup_key_version)
                THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registration context is immutable'; END IF;
            $tenantCheck END");
        DB::unprepared("CREATE TRIGGER vita_registration_delete BEFORE DELETE ON patient_registration_attempts FOR EACH ROW BEGIN
            IF OLD.status='completed' OR OLD.patient_id IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confirmed registration evidence must be retained'; END IF; END");
    }

    public function down(): void
    {
        if (DB::table('patient_registration_attempts')->exists()) throw new LogicException('Registration evidence must be retained.');
        Schema::dropIfExists('patient_registration_attempts');
    }
};
