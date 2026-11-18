<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class FreshDatabaseSchemaTest extends TestCase
{
    public function test_consolidated_schema_has_final_fields_and_no_retired_catalog_tables(): void
    {
        foreach (['directory_specialties', 'directory_specialty_events', 'directory_specialty_registry',
            'directory_specialty_aliases', 'customer_directory_specialties', 'directory_specialty_resolution_events',
            'specialty_catalog_releases'] as $table) $this->assertFalse(Schema::hasTable($table), $table);
        $this->assertTrue(Schema::hasColumns('specialties', ['catalog_id', 'public_visible']));
        $this->assertTrue(Schema::hasColumns('specialty_user', ['customer_id', 'clinic_branch_id']));
        $this->assertTrue(Schema::hasColumns('encounter_document_versions', ['kind', 'parent_version_id']));
        $this->assertTrue(Schema::hasColumns('customer_subscriptions', ['payment_mode', 'contract_status']));
        $this->assertTrue(Schema::hasColumns('flow_checkout_orders', ['open_subscription']));
        $this->assertTrue(Schema::hasColumns('subscription_changes', ['pending_subscription']));
        $triggers = collect(DB::select('SHOW TRIGGERS'))->pluck('Trigger');
        $this->assertCount(53, $triggers);
        foreach (['vita_signature_insert', 'vita_encounter_update', 'vita_version_insert'] as $trigger) $this->assertContains($trigger, $triggers);
        $files = glob(database_path('migrations/*.php'));
        $this->assertCount(83, $files);
        foreach ($files as $file) $this->assertDoesNotMatchRegularExpression('/_(add|repair|recover|backfill|extend)_/', basename($file));
    }
}
