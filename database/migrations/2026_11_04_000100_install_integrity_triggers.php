<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared('CREATE TRIGGER `vita_signed_appointment_update` BEFORE UPDATE ON `appointments` FOR EACH ROW BEGIN
            IF EXISTS(SELECT 1 FROM encounters e JOIN encounter_signatures s ON s.encounter_id=e.id WHERE e.appointment_id=OLD.id)
            AND (NEW.status <> 3 OR NOT(NEW.customer_id <=> OLD.customer_id) OR NOT(NEW.patient_id <=> OLD.patient_id)
            OR NOT(NEW.professional_id <=> OLD.professional_id) OR NOT(NEW.clinic_branch_id <=> OLD.clinic_branch_id))
            THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Appointment belongs to a signed encounter\'; END IF;
        END');
        DB::unprepared('CREATE TRIGGER `vita_signed_appointment_delete` BEFORE DELETE ON `appointments` FOR EACH ROW BEGIN
                IF EXISTS(SELECT 1 FROM encounters e JOIN encounter_signatures s ON s.encounter_id=e.id WHERE e.appointment_id=OLD.id)
                THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter reference must be retained\'; END IF;
            END');
        DB::unprepared('CREATE TRIGGER `vita_billing_audit_update` BEFORE UPDATE ON `billing_catalog_events` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Billing audit is append only\'');
        DB::unprepared('CREATE TRIGGER `vita_billing_audit_delete` BEFORE DELETE ON `billing_catalog_events` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Billing audit is append only\'');
        DB::unprepared('CREATE TRIGGER `vita_billing_features_insert` BEFORE INSERT ON `billing_plan_features` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_features_update` BEFORE UPDATE ON `billing_plan_features` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    IF NEW.billing_plan_version_id <> OLD.billing_plan_version_id THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Cannot move commercial rows\'; END IF;
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_features_delete` BEFORE DELETE ON `billing_plan_features` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=OLD.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_limits_insert` BEFORE INSERT ON `billing_plan_limits` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_limits_update` BEFORE UPDATE ON `billing_plan_limits` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    IF NEW.billing_plan_version_id <> OLD.billing_plan_version_id THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Cannot move commercial rows\'; END IF;
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_limits_delete` BEFORE DELETE ON `billing_plan_limits` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=OLD.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_prices_insert` BEFORE INSERT ON `billing_plan_prices` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_prices_update` BEFORE UPDATE ON `billing_plan_prices` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    IF NEW.billing_plan_version_id <> OLD.billing_plan_version_id THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Cannot move commercial rows\'; END IF;
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_prices_delete` BEFORE DELETE ON `billing_plan_prices` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=OLD.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_quotas_insert` BEFORE INSERT ON `billing_plan_quotas` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_quotas_update` BEFORE UPDATE ON `billing_plan_quotas` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=NEW.billing_plan_version_id FOR UPDATE;
                    IF NEW.billing_plan_version_id <> OLD.billing_plan_version_id THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Cannot move commercial rows\'; END IF;
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_quotas_delete` BEFORE DELETE ON `billing_plan_quotas` FOR EACH ROW BEGIN
                    DECLARE version_state VARCHAR(16);
                    SELECT status INTO version_state FROM billing_plan_versions WHERE id=OLD.billing_plan_version_id FOR UPDATE;
                    
                    IF version_state IS NULL OR version_state <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial terms are immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_version_insert` BEFORE INSERT ON `billing_plan_versions` FOR EACH ROW BEGIN
            IF NEW.status <> \'draft\' OR NEW.snapshot IS NOT NULL OR NEW.sha256 IS NOT NULL OR NEW.published_at IS NOT NULL OR NEW.published_by IS NOT NULL
            THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Versions must start as drafts\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_version_update` BEFORE UPDATE ON `billing_plan_versions` FOR EACH ROW BEGIN
            IF OLD.status <> \'draft\' OR NEW.status NOT IN (\'draft\',\'published\') OR NEW.billing_plan_id <> OLD.billing_plan_id OR NEW.number <> OLD.number
            THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published commercial versions are immutable\'; END IF;
            IF NEW.status=\'published\' AND (NEW.snapshot IS NULL OR NEW.sha256 IS NULL OR NEW.published_at IS NULL OR NEW.published_by IS NULL OR NEW.included_branches IS NULL OR NEW.trial_days IS NULL OR NEW.grace_days IS NULL)
            THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Incomplete publication\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_billing_version_delete` BEFORE DELETE ON `billing_plan_versions` FOR EACH ROW BEGIN
            IF OLD.status <> \'draft\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Published versions must be retained\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_directory_audit_update` BEFORE UPDATE ON `customer_directory_events` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Directory consent history is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_directory_audit_delete` BEFORE DELETE ON `customer_directory_events` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Directory consent history is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_version_insert` BEFORE INSERT ON `encounter_document_versions` FOR EACH ROW BEGIN
            DECLARE locked_encounter BIGINT;
            SELECT id INTO locked_encounter FROM encounters WHERE id=NEW.encounter_id FOR UPDATE;
            IF NOT EXISTS(SELECT 1 FROM encounters e WHERE e.id=NEW.encounter_id AND e.customer_id=NEW.customer_id
                AND e.patient_id=NEW.patient_id AND e.clinic_branch_id=NEW.clinic_branch_id)
            THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Version ownership mismatch\'; END IF;
            IF NEW.kind=\'original\' THEN
                IF NEW.version<>1 OR NEW.parent_version_id IS NOT NULL OR NEW.rectifies_version_id IS NOT NULL
                    OR NOT EXISTS(SELECT 1 FROM encounters WHERE id=NEW.encounter_id AND status=1)
                THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Invalid original version\'; END IF;
            ELSEIF NEW.kind=\'addendum\' THEN
                IF NOT EXISTS(SELECT 1 FROM encounter_document_versions p JOIN encounter_signatures s ON s.document_version_id=p.id
                    JOIN encounters e ON e.id=p.encounter_id WHERE p.id=NEW.parent_version_id AND p.kind=\'original\'
                    AND p.encounter_id=NEW.encounter_id AND p.customer_id=NEW.customer_id AND e.status=2)
                    OR NEW.version<>(SELECT COALESCE(MAX(v.version),0)+1 FROM encounter_document_versions v WHERE v.encounter_id=NEW.encounter_id)
                    OR (NEW.rectifies_version_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM encounter_document_versions r
                        JOIN encounter_signatures s ON s.document_version_id=r.id WHERE r.id=NEW.rectifies_version_id
                        AND r.encounter_id=NEW.encounter_id AND r.customer_id=NEW.customer_id AND r.version<NEW.version))
                THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Invalid addendum parent, sequence or rectification\'; END IF;
            ELSE SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Unknown document kind\'; END IF;
        END');
        DB::unprepared('CREATE TRIGGER `vita_version_update` BEFORE UPDATE ON `encounter_document_versions` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signature evidence is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_version_delete` BEFORE DELETE ON `encounter_document_versions` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signature evidence is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_observation_insert` BEFORE INSERT ON `encounter_observations` FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = NEW.encounter_id) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter content requires a signed addendum\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_observation_update` BEFORE UPDATE ON `encounter_observations` FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = NEW.encounter_id) OR EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = OLD.encounter_id) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter content requires a signed addendum\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_observation_delete` BEFORE DELETE ON `encounter_observations` FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = OLD.encounter_id) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter content requires a signed addendum\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_signature_insert` BEFORE INSERT ON `encounter_signatures` FOR EACH ROW BEGIN
            IF NEW.accepted<>1 OR NOT EXISTS(
                SELECT 1 FROM encounter_document_versions v JOIN encounters e ON e.id=v.encounter_id
                JOIN encounter_signing_operations o ON o.id=NEW.operation_id
                WHERE v.id=NEW.document_version_id AND v.customer_id=NEW.customer_id AND e.customer_id=NEW.customer_id
                AND e.id=NEW.encounter_id AND v.sha256=NEW.content_hash
                AND o.encounter_id=e.id AND o.customer_id=NEW.customer_id AND o.user_id=NEW.user_id
                AND o.content_hash=NEW.content_hash AND o.status=\'prepared\'
                AND ((v.kind=\'original\' AND v.version=1 AND e.status=1 AND o.purpose=\'original\' AND o.parent_version_id IS NULL
                    AND (e.professional_id=NEW.user_id OR (e.professional_id IS NULL AND EXISTS(SELECT 1 FROM appointments a
                        WHERE a.id=e.appointment_id AND a.professional_id=NEW.user_id AND a.customer_id=NEW.customer_id))))
                OR (v.kind=\'addendum\' AND e.status=2 AND o.purpose=\'addendum\' AND o.parent_version_id=v.parent_version_id
                    AND EXISTS(SELECT 1 FROM encounter_document_versions p JOIN encounter_signatures s ON s.document_version_id=p.id
                        WHERE p.id=v.parent_version_id AND p.kind=\'original\' AND p.encounter_id=e.id
                        AND p.customer_id=NEW.customer_id AND s.user_id=NEW.user_id)))
            ) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Signature evidence does not match document purpose or signer\'; END IF;
        END');
        DB::unprepared('CREATE TRIGGER `vita_signature_update` BEFORE UPDATE ON `encounter_signatures` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signature evidence is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_signature_delete` BEFORE DELETE ON `encounter_signatures` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signature evidence is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_encounter_insert` BEFORE INSERT ON `encounters` FOR EACH ROW BEGIN IF NEW.status = 2 THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'New encounters require a signature before finalization\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_encounter_update` BEFORE UPDATE ON `encounters` FOR EACH ROW BEGIN
            IF EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = OLD.id) THEN
                IF OLD.status = 2 OR NEW.status <> 2 THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter is immutable\'; END IF;
            ELSEIF NEW.status = 2 AND OLD.status <> 2 THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Finalization requires signature\'; END IF;
        END');
        DB::unprepared('CREATE TRIGGER `vita_flow_billing_agreements_terms` BEFORE UPDATE ON `flow_billing_agreements` FOR EACH ROW BEGIN IF NOT (NEW.`customer_subscription_id` <=> OLD.`customer_subscription_id`) OR NOT (NEW.`flow_billing_customer_id` <=> OLD.`flow_billing_customer_id`) OR NOT (NEW.`plan_id` <=> OLD.`plan_id`) OR NOT (NEW.`quote` <=> OLD.`quote`) OR NOT (NEW.`starts_on` <=> OLD.`starts_on`) OR NOT (NEW.`initiated_by` <=> OLD.`initiated_by`) OR NOT (NEW.`accepted_at` <=> OLD.`accepted_at`) OR NOT (NEW.`consent_version` <=> OLD.`consent_version`) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Gateway contractual evidence is immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_flow_billing_agreements_delete` BEFORE DELETE ON `flow_billing_agreements` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Gateway contract history must be retained\'');
        DB::unprepared('CREATE TRIGGER `vita_flow_billing_receipt_update` BEFORE UPDATE ON `flow_billing_receipts` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Verified payment receipts are immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_flow_billing_receipt_delete` BEFORE DELETE ON `flow_billing_receipts` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Verified payment receipts are immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_flow_checkout_orders_terms` BEFORE UPDATE ON `flow_checkout_orders` FOR EACH ROW BEGIN IF NOT (NEW.`customer_subscription_id` <=> OLD.`customer_subscription_id`) OR NOT (NEW.`environment` <=> OLD.`environment`) OR NOT (NEW.`account_hash` <=> OLD.`account_hash`) OR NOT (NEW.`commerce_order` <=> OLD.`commerce_order`) OR NOT (NEW.`quote` <=> OLD.`quote`) OR NOT (NEW.`period_start` <=> OLD.`period_start`) OR NOT (NEW.`initiated_by` <=> OLD.`initiated_by`) OR NOT (NEW.`attempt` <=> OLD.`attempt`) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Gateway contractual evidence is immutable\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_flow_checkout_orders_delete` BEFORE DELETE ON `flow_checkout_orders` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Gateway contract history must be retained\'');
        DB::unprepared('CREATE TRIGGER `vita_flow_observation_update` BEFORE UPDATE ON `flow_payment_observations` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Flow observation history is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_flow_observation_delete` BEFORE DELETE ON `flow_payment_observations` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Flow observation history is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_document_insert` BEFORE INSERT ON `patient_documents` FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = NEW.encounter_id) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter content requires a signed addendum\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_document_update` BEFORE UPDATE ON `patient_documents` FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = NEW.encounter_id) OR EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = OLD.encounter_id) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter content requires a signed addendum\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_document_delete` BEFORE DELETE ON `patient_documents` FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM encounter_signatures WHERE encounter_id = OLD.encounter_id) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter content requires a signed addendum\'; END IF; END');
        DB::unprepared('CREATE TRIGGER `vita_fee_rate_update` BEFORE UPDATE ON `payment_fee_rates` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Fee rate history is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_fee_rate_delete` BEFORE DELETE ON `payment_fee_rates` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Fee rate history is immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_signed_specialty_delete` BEFORE DELETE ON `specialties` FOR EACH ROW BEGIN
                IF EXISTS(SELECT 1 FROM encounters e JOIN encounter_signatures s ON s.encounter_id=e.id WHERE e.specialty_id=OLD.id)
                THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Signed encounter reference must be retained\'; END IF;
            END');
        DB::unprepared('CREATE TRIGGER `vita_subscription_events_update` BEFORE UPDATE ON `subscription_events` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Subscription financial history is append only\'');
        DB::unprepared('CREATE TRIGGER `vita_subscription_events_delete` BEFORE DELETE ON `subscription_events` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Subscription financial history is append only\'');
        DB::unprepared('CREATE TRIGGER `vita_subscription_payment_insert` BEFORE INSERT ON `subscription_payments` FOR EACH ROW BEGIN
            DECLARE owner_customer BIGINT;
            SELECT customer_id INTO owner_customer FROM customer_subscriptions WHERE id=NEW.customer_subscription_id FOR UPDATE;
            IF owner_customer IS NULL OR owner_customer<>NEW.customer_id OR NEW.period_end<=NEW.period_start OR NEW.currency<>\'CLP\'
            THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Invalid subscription payment ownership or period\'; END IF;
        END');
        DB::unprepared('CREATE TRIGGER `vita_subscription_payments_update` BEFORE UPDATE ON `subscription_payments` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Subscription financial history is append only\'');
        DB::unprepared('CREATE TRIGGER `vita_subscription_payments_delete` BEFORE DELETE ON `subscription_payments` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Subscription financial history is append only\'');
        DB::unprepared('CREATE TRIGGER `vita_policy_update` BEFORE UPDATE ON `subscription_policy_versions` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Policy versions are immutable\'');
        DB::unprepared('CREATE TRIGGER `vita_policy_delete` BEFORE DELETE ON `subscription_policy_versions` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Policy versions are immutable\'');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_signed_appointment_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_signed_appointment_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_audit_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_audit_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_features_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_features_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_features_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_limits_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_limits_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_limits_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_prices_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_prices_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_prices_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_quotas_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_quotas_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_quotas_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_version_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_version_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_billing_version_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_directory_audit_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_directory_audit_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_version_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_version_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_version_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_observation_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_observation_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_observation_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_signature_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_signature_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_signature_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_encounter_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_encounter_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_billing_agreements_terms`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_billing_agreements_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_billing_receipt_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_billing_receipt_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_checkout_orders_terms`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_checkout_orders_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_observation_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_flow_observation_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_document_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_document_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_document_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_fee_rate_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_fee_rate_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_signed_specialty_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_subscription_events_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_subscription_events_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_subscription_payment_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_subscription_payments_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_subscription_payments_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_policy_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `vita_policy_delete`');
    }
};
