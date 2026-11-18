<?php

namespace Tests\Integration\Identification;

use App\Models\{ClinicBranch, Patient, Permission, Role};
use App\Services\Identification\PatientIdentification;
use App\Services\Identification\Storage\{IdentifierRepositoryFactory, IdentifierSubject};
use App\Services\Signatures\EncounterSignatureVerifier;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class PatientIdentificationIntegrationTest extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as signingSetUp; tearDown as signingTearDown; }
    private bool $started = false;

    protected function setUp(): void
    {
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || ! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', getenv('DB_DATABASE') ?: '')) $this->markTestSkipped('Isolated MySQL only.');
        $this->signingSetUp();
        $this->started = true;
        config(['patient_registration.enabled' => true]);
        config(['identification' => IdentifierStorageKeys::config() + ['application_key' => config('app.key')]]);
    }

    protected function tearDown(): void
    {
        if ($this->started) $this->signingTearDown();
    }

    private function data(string $country = 'AR', string $number = '000FOREIGNLONG'): array
    {
        return ['name'=>'International patient','gender'=>'Masculino','birth_date'=>'1990-01-01','phone'=>'123',
            'email'=>'patient@example.test','address'=>'Chile','identification_enabled'=>true,
            'identification_country'=>$country,'identification_type'=>$country==='CL'?'national_id':'passport',
            'identification_number'=>$number,'identification_expected_primary'=>''];
    }

    /** Simulation only: production has no switch that enables uncoordinated writers. */
    private function simulateCoordinatedWriters(): void
    {
        config(['patient_registration.enabled' => true]);
    }

    private function administrator(): void { $this->actingAs($this->user('Administrador')); }

    private function adoptForeignHistory(): void
    {
        $this->patient->update(['rut'=>'000FOREIGNLONG']);
        $this->administrator();
        $this->putJson($this->root.'/patient/'.$this->patient->id,$this->data())->assertOk();
    }

    public function test_disabled_registration_gate_fails_closed_until_rollout(): void
    {
        config(['patient_registration.enabled' => false]);
        $this->administrator();
        $before = Patient::count();
        foreach ([$this->data(),$this->data('CL','123456785')] as $data) $this->postJson($this->root.'/patient',$data)->assertStatus(503);
        $this->assertSame($before, Patient::count());
    }

    public function test_safe_explicit_adoption_preserves_original_legacy_bytes_and_identity_is_not_verified(): void
    {
        $this->patient->update(['rut'=>'12345678-5']);
        $this->administrator();
        $this->putJson($this->root.'/patient/'.$this->patient->id,$this->data('CL','12.345.678-5'))->assertOk();
        $this->assertSame('12345678-5',$this->patient->fresh()->rut);
        $form=$this->getJson($this->root.'/patient/'.$this->patient->id.'/identification')->assertOk()->json();
        $this->assertSame('12.345.678-5',$form['identification_display']);
        $this->assertFalse($form['identity_verified']);
        $this->putJson($this->root.'/patient/'.$this->patient->id,$this->data('CL','12345678-5')+['identification_expected_primary'=>$form['identification_expected_primary']])->assertUnprocessable();
    }

    public function test_foreign_history_can_be_classified_without_changing_legacy_or_losing_zeroes(): void
    {
        $this->adoptForeignHistory();
        $this->assertSame('000FOREIGNLONG',$this->patient->fresh()->rut);
        $form=$this->getJson($this->root.'/patient/'.$this->patient->id.'/identification')->assertOk()->json();
        $this->assertSame('000FOREIGNLONG',$form['identification_number']);
        $raw=DB::table('patient_identifiers')->where('patient_id',$this->patient->id)->first();
        $this->assertStringNotContainsString('000FOREIGNLONG',$raw->encrypted_payload);
        $this->assertSame('AR',$raw->issuing_country_code);
        $this->assertStringNotContainsString('000FOREIGNLONG',json_encode(DB::table('patient_audit_logs')->where('patient_id',$this->patient->id)->get()));
    }

    public function test_patient_table_displays_authorized_document_but_chat_remains_masked(): void
    {
        $this->adoptForeignHistory();
        $response=$this->postJson($this->root.'/patient-data/search',['document_country'=>'AR','document_type'=>'passport','document_number'=>'000FOREIGNLONG'])->assertOk();
        $this->assertSame([$this->patient->id],array_column($response->json('data'),'id'));
        $this->assertSame('000FOREIGNLONG', $response->json('data.0.identification_label'));
        $this->assertArrayNotHasKey('rut',$response->json('data.0'));
        $this->postJson($this->root.'/patient-data/search',['document_country'=>'PE','document_type'=>'passport','document_number'=>'000FOREIGNLONG'])->assertOk()->assertJsonCount(0,'data');
        $this->getJson($this->root.'/chat/patients/search?q=International')->assertOk()->assertDontSee('000FOREIGNLONG');
    }

    public function test_patient_v4_is_frozen_and_a_changed_identity_invalidates_confirmation(): void
    {
        $this->adoptForeignHistory();
        $this->actingAs($this->doctor);
        $op=$this->prepare();
        $this->patient->update(['name'=>'Administratively corrected patient']);
        $this->sign($op)->assertUnprocessable();
        $this->sign($this->prepare())->assertOk();
        $signature=$this->encounter->fresh()->signature;
        $before=$signature->documentVersion->getRawOriginal();
        $snapshot=app(EncounterSignatureVerifier::class)->verify($signature);
        $this->assertSame(4,$snapshot['schema_version']);
        $this->assertSame('000FOREIGNLONG',$snapshot['patient']['document']['normalized']);
        $this->patient->update(['name'=>'Current profile changed']);
        $this->assertSame($before,$signature->documentVersion->fresh()->getRawOriginal());
        $html=view('customer.patient.record-workspace.pdf-signature',['signature'=>$signature])->render();
        $this->assertStringContainsString('Administratively corrected patient',$html);
        $this->assertStringNotContainsString('Current profile changed',$html);
        $this->get($this->root.'/patients/'.$this->patient->id.'/medical-record/export/pdfa')->assertOk();
        $this->get($this->root.'/patients/'.$this->patient->id.'/medical-record/export/fhir-r4')->assertOk()->assertSee('000FOREIGNLONG');
    }

    public function test_initial_creation_requires_create_permission_without_granting_update(): void
    {
        $this->simulateCoordinatedWriters();
        $creator=$this->user('Patient creator only');
        $role=$creator->branchRoles()->first();$role->update(['permissions_configured'=>true]);
        $permission=Permission::firstOrCreate(['slug'=>'patients.create'],['name'=>'Create patients','module'=>'patients']);
        $role->permissions()->sync([$permission->id]);
        $response=$this->actingAs($creator)->postJson($this->root.'/patient',$this->data())->assertOk();
        $id=$response->json('data.id');
        $this->assertNull(Patient::findOrFail($id)->rut);
        $this->assertTrue(Patient::findOrFail($id)->medicalRecord()->exists());
        $this->putJson($this->root.'/patient/'.$id,$this->data())->assertForbidden();
        $this->getJson($this->root.'/patient/'.$id.'/identification')->assertForbidden();
    }

    public function test_simulated_coordinated_registration_rejects_duplicate_claims_and_retired_documents(): void
    {
        $this->simulateCoordinatedWriters();$this->administrator();
        $id=$this->postJson($this->root.'/patient',$this->data())->assertOk()->json('data.id');
        $this->postJson($this->root.'/patient',$this->data())->assertUnprocessable();
        $patient=Patient::findOrFail($id);
        $subject=new IdentifierSubject('patient',$id,$this->clinic->id);
        $repo=app(IdentifierRepositoryFactory::class)->make();
        $reference=$repo->primary($subject,auth()->user())['reference'];
        $repo->retire($subject,auth()->user(),$reference,'Administrative correction');
        $this->postJson($this->root.'/patient',$this->data())->assertUnprocessable();
        $this->assertFalse($repo->findExact($subject,auth()->user(),'AR','passport','000FOREIGNLONG')!==[]);
    }

    public function test_failing_medical_record_rolls_back_patient_identifier_and_audits_in_isolated_mysql(): void
    {
        $this->simulateCoordinatedWriters();$this->administrator();
        DB::commit();
        $before=[Patient::count(),DB::table('patient_identifiers')->count(),DB::table('patient_audit_logs')->count()];
        DB::unprepared("CREATE TRIGGER stage4_fail_record BEFORE INSERT ON medical_records FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic record failure'");
        try {
            $this->withoutExceptionHandling();
            try { $this->postJson($this->root.'/patient',$this->data());$this->fail('Expected record failure.'); }
            catch (\RuntimeException $error) {$this->assertStringNotContainsString('insert into', $error->getMessage());}
            $this->assertSame($before,[Patient::count(),DB::table('patient_identifiers')->count(),DB::table('patient_audit_logs')->count()]);
        } finally {DB::unprepared('DROP TRIGGER stage4_fail_record');}
    }

    public function test_other_branch_and_other_tenant_cannot_read_full_identity(): void
    {
        $this->adoptForeignHistory();
        $branch=ClinicBranch::create(['customer_id'=>$this->clinic->id,'name'=>'Other branch','status'=>1]);
        $reader=$this->user('Branch reader');$reader->branches()->attach($branch->id,['status'=>1]);
        $role=Role::where('customer_id',$this->clinic->id)->where('name','Branch reader')->first();$role->update(['permissions_configured'=>true]);
        $permission=Permission::firstOrCreate(['slug'=>'patients.view'],['name'=>'View patients','module'=>'patients']);$role->permissions()->sync([$permission->id]);
        $reader->branchRoles()->attach($role->id,['customer_id'=>$this->clinic->id,'clinic_branch_id'=>$branch->id]);
        $this->actingAs($reader)->withSession(['active_branch_id'=>$branch->id])->getJson($this->root.'/patient/'.$this->patient->id.'/identification')->assertForbidden();
        $this->postJson($this->root.'/patient-data/search')->assertOk()->assertDontSee('000FOREIGNLONG');
        $this->withSession(['active_branch_id'=>$this->branch->id])->getJson($this->root.'/patient/999999999/identification')->assertNotFound();
    }

    public function test_route_names_in_payload_cannot_select_a_legacy_writer(): void
    {
        $this->administrator();
        $data = $this->data('CL','123456785');
        $this->postJson($this->root.'/patient', $data+['route_name'=>'customers.assistant.ask','legacy'=>true])->assertOk();
        $this->postJson($this->root.'/patient', $data+['route_name'=>'customers.assistant.ask','legacy'=>true])->assertUnprocessable();
    }

    public function test_nullable_migration_refuses_rollback_when_patient_has_no_legacy_rut(): void
    {
        $this->patient->update(['rut' => null]);
        $migration = require database_path('migrations/2026_11_06_000200_allow_absent_legacy_patient_rut.php');
        $this->expectException(\LogicException::class);
        $migration->down();
    }

    public function test_patient_form_has_searchable_country_without_obsolete_block_notice(): void
    {
        $this->administrator();
        $this->get($this->root.'/patient?_vita_content=1')->assertOk()->assertSee('Filtrar países')->assertDontSee('El alta de identificaciones nuevas');
    }

    public function test_datatable_searches_names_and_equivalent_ruts_with_correct_counts(): void
    {
        $this->patient->update(['rut' => '12345678-5']);
        $this->administrator();
        $this->putJson($this->root.'/patient/'.$this->patient->id, $this->data('CL', '12.345.678-5'))->assertOk();
        Patient::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
            'name' => 'Unrelated patient', 'rut' => 'unclassified-other', 'birth_date' => '1990-01-01', 'gender' => 'Masculino']);
        foreach (['International', '123456785', '12345678-5', '12.345.678-5'] as $term) {
            $response = $this->postJson($this->root.'/patient-data/search', ['draw' => 7, 'start' => 0, 'length' => 1,
                'search' => ['value' => $term, 'regex' => false]])->assertOk();
            $response->assertJsonPath('draw', 7)->assertJsonPath('recordsTotal', 2)->assertJsonPath('recordsFiltered', 1);
            $this->assertSame([$this->patient->id], array_column($response->json('data'), 'id'));
            $this->assertSame('12.345.678-5', $response->json('data.0.identification_label'));
        }
        $this->postJson($this->root.'/patient-data/search', ['search' => ['value' => '123456784']])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_datatable_foreign_search_requires_scope_and_exact_hmac_match_without_cross_tenant_results(): void
    {
        $this->adoptForeignHistory();
        $other = $this->clinic->replicate();
        $other->domain = 'other-'.$this->clinic->domain;
        $other->save();
        Patient::create(['customer_id' => $other->id, 'name' => 'International patient', 'rut' => '000FOREIGNLONG',
            'birth_date' => '1990-01-01', 'gender' => 'Masculino']);
        $payload = ['search' => ['value' => '000FOREIGNLONG'], 'document_country' => 'AR', 'document_type' => 'passport'];
        $response = $this->postJson($this->root.'/patient-data/search', $payload)->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($this->patient->id, $response->json('data.0.id'));
        $this->assertSame('000FOREIGNLONG', $response->json('data.0.identification_label'));
        $this->postJson($this->root.'/patient-data/search', ['search' => $payload['search']])->assertOk()->assertJsonCount(0, 'data');
        $payload['document_country'] = 'PE';
        $this->postJson($this->root.'/patient-data/search', $payload)->assertOk()->assertJsonCount(0, 'data');
        $payload['document_country'] = 'AR'; $payload['search']['value'] = '000FOREIGN';
        $this->postJson($this->root.'/patient-data/search', $payload)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->root.'/patient-data/search', ['search' => ['value' => 'International']])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_patient_search_ui_has_one_number_input_owned_by_datatables(): void
    {
        $this->administrator();
        $this->get($this->root.'/patient?_vita_content=1')->assertOk()
            ->assertDontSee('id="patient-document-search"', false)->assertDontSee('Buscar documento')
            ->assertDontSee('patient-document-scope-template')->assertSee('Nombre o RUT');
        $this->postJson($this->root.'/patient-data/search', ['search' => ['value' => str_repeat('a', 129)]])->assertUnprocessable();
        $this->postJson($this->root.'/patient-data/search', ['search' => ['value' => 'x'], 'document_country' => ['AR'], 'document_type' => 'passport'])->assertUnprocessable();
    }

    public function test_patient_table_formats_valid_legacy_rut_without_changing_storage(): void
    {
        $this->patient->update(['rut' => '123456785']);
        $this->administrator();
        $this->postJson($this->root.'/patient-data/search')->assertOk()->assertJsonPath('data.0.identification_label', '12.345.678-5');
        $this->assertSame('123456785', $this->patient->fresh()->rut);
    }

    public function test_datatable_finds_invalid_historical_rut_without_validating_or_rewriting_it(): void
    {
        $this->patient->update(['rut' => '12345678']);
        $this->administrator();
        foreach (['12345678', '12.345.678'] as $term) {
            $response = $this->postJson($this->root.'/patient-data/search', ['search' => ['value' => $term]])->assertOk();
            $this->assertSame([$this->patient->id], array_column($response->json('data'), 'id'));
        }
        $this->assertSame('12345678', $this->patient->fresh()->rut);
        $this->assertSame(0, DB::table('patient_identifiers')->where('patient_id', $this->patient->id)->count());
    }

    public function test_clinical_record_displays_complete_current_identification(): void
    {
        $this->patient->update(['rut' => '123456785']);
        $this->get($this->root.'/patients/'.$this->patient->id.'/medical-record?_vita_content=1')
            ->assertOk()->assertSee('12.345.678-5')->assertDontSee('Documento histórico:');
        $this->assertSame('123456785', $this->patient->fresh()->rut);
        $this->adoptForeignHistory();
        $this->actingAs($this->doctor);
        $this->get($this->root.'/patients/'.$this->patient->id.'/medical-record?_vita_content=1')
            ->assertOk()->assertSee('000FOREIGNLONG')->assertDontSee('••••LONG');
    }

    public function test_agenda_permission_does_not_grant_complete_clinical_identification(): void
    {
        $reader = $this->user('Agenda only');
        $role = $reader->branchRoles()->first();
        $role->update(['permissions_configured' => true]);
        $permission = Permission::firstOrCreate(['slug' => 'appointments.view'], ['name' => 'View appointments', 'module' => 'appointments']);
        $role->permissions()->sync([$permission->id]);
        $this->actingAs($reader);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(PatientIdentification::class)->clinicalNumber($this->patient);
    }

    public function test_delete_empty_patient_removes_only_empty_registration_and_keeps_activity_audit(): void
    {
        $this->administrator();
        $patient = Patient::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
            'name' => 'Empty registration', 'rut' => 'empty-registration', 'gender' => 'Masculino', 'birth_date' => '1990-01-01']);
        $patient->medicalRecord()->create(['customer_id' => $this->clinic->id]);
        \App\Support\PatientAuditLogger::record($patient, 'created', 'Ficha clínica', [], ['ficha' => 'Inicial']);
        $this->deleteJson($this->root.'/patient/'.$patient->id)->assertOk()->assertJsonPath('success', true);
        $this->assertNull(Patient::find($patient->id));
        $this->assertTrue(DB::table('clinic_activity_logs')->where('customer_id', $this->clinic->id)
            ->where('subject_type', Patient::class)->where('subject_id', $patient->id)->where('action', 'deleted')->exists());
    }

    public function test_delete_preserves_open_encounters_and_signed_content(): void
    {
        $this->administrator();
        $this->deleteJson($this->root.'/patient/'.$this->patient->id)->assertStatus(409);
        $this->assertNotNull($this->encounter->fresh());
        $this->actingAs($this->doctor);
        $this->sign($this->prepare())->assertOk();
        $signature = $this->encounter->fresh()->signature;
        $original = $signature->getRawOriginal();
        $this->administrator();
        $this->deleteJson($this->root.'/patient/'.$this->patient->id)->assertStatus(409);
        $this->assertSame($original, $signature->fresh()->getRawOriginal());
        $this->assertNotNull($this->patient->fresh());
    }

    public function test_delete_preserves_identifier_reservations_even_without_clinical_content(): void
    {
        $this->administrator();
        $patient = Patient::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
            'name' => 'Identified registration', 'rut' => null, 'gender' => 'Masculino', 'birth_date' => '1990-01-01']);
        $reference = app(IdentifierRepositoryFactory::class)->make()->create(
            new IdentifierSubject('patient', $patient->id, $this->clinic->id), auth()->user(), 'AR', 'passport', '000DELETE', 'natural_person', true);
        $this->deleteJson($this->root.'/patient/'.$patient->id)->assertStatus(409);
        $this->assertTrue(DB::table('patient_identifiers')->where('record_uuid', $reference)->exists());
        $this->assertNotNull($patient->fresh());
    }

    public function test_delete_requires_permission_and_cannot_target_another_clinic(): void
    {
        $reader = $this->user('Patient reader only');
        $role = $reader->branchRoles()->first(); $role->update(['permissions_configured' => true]);
        $permission = Permission::firstOrCreate(['slug' => 'patients.view'], ['name' => 'View patients', 'module' => 'patients']);
        $role->permissions()->sync([$permission->id]);
        $this->actingAs($reader)->deleteJson($this->root.'/patient/'.$this->patient->id)->assertForbidden();
        $other = $this->clinic->replicate(); $other->domain = 'delete-other-'.$this->clinic->domain; $other->save();
        $patient = Patient::create(['customer_id' => $other->id, 'name' => 'Other clinic patient', 'rut' => 'other-delete',
            'gender' => 'Masculino', 'birth_date' => '1990-01-01']);
        $this->administrator();
        $this->deleteJson($this->root.'/patient/'.$patient->id)->assertNotFound();
        $this->assertNotNull($patient->fresh());
    }

}
