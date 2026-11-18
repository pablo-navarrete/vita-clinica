<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new LogicException('Identifier storage requires reviewed MySQL 8 constraints.');
        }
        foreach (['customer', 'user', 'patient'] as $kind) {
            $table = $kind.'_identifiers';
            $owner = $kind === 'customer' ? 'customer_id' : $kind.'_id';
            Schema::create($table, function (Blueprint $t) use ($kind, $owner) {
                $t->engine = 'InnoDB';
                $t->id();
                $t->uuid('record_uuid')->unique();
                $t->unsignedBigInteger('customer_id')->nullable($kind === 'user');
                $t->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
                if ($kind !== 'customer') {
                    $t->foreignId($owner)->constrained($kind === 'user' ? 'users' : 'patients')->restrictOnDelete();
                }
                $t->string('scope_key', 48)->collation('ascii_bin');
                $t->char('issuing_country_code', 2)->nullable()->collation('ascii_bin');
                $t->string('document_type', 32)->nullable();
                $t->string('identifier_namespace', 64)->nullable();
                $t->string('holder_kind', 24);
                $t->string('normalization_version', 48)->nullable();
                $t->string('validation_status', 24);
                $t->string('record_state', 24)->default('validated');
                $t->string('source_kind', 24)->default('manual');
                $t->string('legacy_source_ref', 120)->nullable()->unique();
                $t->string('legacy_classification', 48)->nullable();
                $t->longText('encrypted_payload');
                $t->string('encryption_key_version', 24);
                $t->string('lookup_key_version', 24)->nullable();
                $t->char('lookup_hash', 64)->nullable()->collation('ascii_bin');
                // Only classified, usable identifiers reserve uniqueness. Quarantined legacy
                // rows may carry a candidate lookup, but never an automatic identity claim.
                $t->char('claim_hash', 64)->nullable()->collation('ascii_bin');
                $t->boolean('is_primary')->default(false);
                $t->dateTime('retired_at')->nullable(); // UTC, no session timezone conversion in authenticated metadata.
                $t->unsignedBigInteger('primary_owner_id')->nullable()
                    ->storedAs("CASE WHEN is_primary = 1 AND retired_at IS NULL THEN $owner ELSE NULL END");
                $t->unique('primary_owner_id', 'ident_'.$kind.'_primary');
                $uniqueScope = $kind === 'patient' ? 'customer_id' : $owner;
                $t->unique([$uniqueScope, 'lookup_key_version', 'claim_hash'], 'ident_'.$kind.'_claim');
                $t->index(['scope_key', 'lookup_key_version', 'lookup_hash', 'retired_at'], 'ident_'.$kind.'_lookup');
                $t->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->foreignId('last_changed_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->foreignId('retired_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->unsignedInteger('revision')->default(1);
                $t->timestamps();
            });
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `ident_{$kind}_state` CHECK (
                (record_state = 'validated' AND validation_status IN ('basic','checksum')
                    AND issuing_country_code IS NOT NULL AND document_type IS NOT NULL
                    AND identifier_namespace IS NOT NULL AND normalization_version IS NOT NULL
                    AND lookup_key_version IS NOT NULL AND lookup_hash IS NOT NULL
                    AND claim_hash IS NOT NULL AND claim_hash = lookup_hash)
                OR (record_state IN ('legacy_unclassified','legacy_conflict')
                    AND validation_status = 'legacy_unverified' AND source_kind = 'legacy'
                    AND claim_hash IS NULL AND is_primary = 0 AND legacy_source_ref IS NOT NULL))");
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `ident_{$kind}_primary_live` CHECK (is_primary = 0 OR retired_at IS NULL)");
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `ident_{$kind}_boolean` CHECK (is_primary IN (0,1))");
            $holders = $kind === 'customer' ? "'natural_person','legal_entity'" : "'natural_person'";
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `ident_{$kind}_holder` CHECK (holder_kind IN ($holders))");
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `ident_{$kind}_scope` CHECK (scope_key = IF(customer_id IS NULL, 'global', CONCAT('customer:', customer_id)))");
            $ownerGuard = $kind === 'customer' ? "IF NEW.customer_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Identifier requires customer'; END IF;" : 'IF NOT EXISTS (SELECT 1 FROM `'.($kind === 'user' ? 'users' : 'patients')."` WHERE id = NEW.`$owner` AND customer_id <=> NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Identifier owner tenant mismatch'; END IF;";
            DB::unprepared("CREATE TRIGGER `ident_{$kind}_insert` BEFORE INSERT ON `$table` FOR EACH ROW BEGIN $ownerGuard END");
            DB::unprepared("CREATE TRIGGER `ident_{$kind}_update` BEFORE UPDATE ON `$table` FOR EACH ROW BEGIN
                $ownerGuard
                IF NOT (NEW.`$owner` <=> OLD.`$owner`) OR NOT (NEW.customer_id <=> OLD.customer_id)
                    OR NEW.scope_key <> OLD.scope_key OR NEW.record_uuid <> OLD.record_uuid
                    OR NOT (NEW.issuing_country_code <=> OLD.issuing_country_code)
                    OR NOT (NEW.document_type <=> OLD.document_type)
                    OR NOT (NEW.identifier_namespace <=> OLD.identifier_namespace)
                    OR NEW.holder_kind <> OLD.holder_kind
                    OR NOT (NEW.normalization_version <=> OLD.normalization_version)
                    OR NEW.record_state <> OLD.record_state OR NEW.validation_status <> OLD.validation_status
                    OR NEW.source_kind <> OLD.source_kind OR NOT (NEW.legacy_source_ref <=> OLD.legacy_source_ref)
                    OR NOT (NEW.legacy_classification <=> OLD.legacy_classification)
                    OR NOT (NEW.created_by <=> OLD.created_by) OR NOT (NEW.created_at <=> OLD.created_at)
                    OR (OLD.retired_at IS NOT NULL AND NOT (NEW.retired_at <=> OLD.retired_at))
                THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable identifier metadata'; END IF;
                IF NEW.revision <> OLD.revision + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Identifier revision required'; END IF;
                END");
            DB::unprepared("CREATE TRIGGER `ident_{$kind}_delete` BEFORE DELETE ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Retire identifiers instead of deleting'");
        }
        foreach (['user' => 'users', 'patient' => 'patients'] as $kind => $ownerTable) {
            DB::unprepared("CREATE TRIGGER `ident_{$kind}_owner_scope_update` BEFORE UPDATE ON `$ownerTable` FOR EACH ROW BEGIN
                IF NOT (NEW.customer_id <=> OLD.customer_id) AND EXISTS (SELECT 1 FROM `{$kind}_identifiers` WHERE `{$kind}_id` = OLD.id)
                THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Identifier owner cannot move tenant'; END IF;
                END");
        }
    }

    public function down(): void
    {
        // Never erase identifiers as an automatic rollback, including retired/conflicting rows.
        foreach (['customer', 'user', 'patient'] as $kind) {
            if (Schema::hasTable($kind.'_identifiers') && DB::table($kind.'_identifiers')->exists()) {
                throw new LogicException('Rollback refused: identifier data must be retained. Disable integration instead.');
            }
        }
        foreach (['patient', 'user', 'customer'] as $kind) {
            if ($kind !== 'customer') {
                DB::unprepared("DROP TRIGGER IF EXISTS `ident_{$kind}_owner_scope_update`");
            }
            foreach (['insert', 'update', 'delete'] as $action) {
                DB::unprepared("DROP TRIGGER IF EXISTS `ident_{$kind}_$action`");
            }
            Schema::dropIfExists($kind.'_identifiers');
        }
    }
};
