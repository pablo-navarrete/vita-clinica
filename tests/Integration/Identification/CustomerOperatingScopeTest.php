<?php

namespace Tests\Integration\Identification;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerOperatingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class CustomerOperatingScopeTest extends TestCase
{
    private bool $started = false;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || ! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', DB::connection()->getDatabaseName())) {
            $this->markTestSkipped('Isolated schema only.');
        }
        config(['identification' => IdentifierStorageKeys::config() + ['application_key' => config('app.key')]]);
        DB::beginTransaction();
        $this->started = true;
        $this->actor = User::factory()->create(['customer_id' => null, 'status' => 1]);
        $role = Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
        $this->actor->roles()->attach($role->id);
        $this->actingAs($this->actor);
    }

    protected function tearDown(): void
    {
        if ($this->started) DB::rollBack();
        parent::tearDown();
    }

    private function url(): string { return 'http://'.config('app.domain').'/customers'; }

    private function data(string $country = 'CL'): array
    {
        $key = Str::lower(Str::random(12));
        return ['name' => 'Scope'.$key, 'legal_name' => 'Scope', 'holder' => 'Holder', 'status' => 1,
            'phone' => '123', 'email' => $key.'@test.test', 'operating_country_code' => 'CL', 'operating_declaration_accepted' => true,
            'identification_country' => $country, 'identification_type' => 'tax_id', 'identification_holder' => 'legal_entity',
            'identification_number' => $country === 'CL' ? '123456785' : '000FOREIGN',
            'admin_name' => 'Admin', 'admin_email' => $key.'@admin.test', 'admin_phone' => '123',
            'admin_password' => 'test-password', 'admin_password_confirmation' => 'test-password',
            'admin_identification_country' => 'AR', 'admin_identification_type' => 'passport', 'admin_identification_number' => '000PERSON'];
    }

    public static function holders(): array { return [['CL'], ['AR']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('holders')]
    public function test_chile_operation_is_independent_of_document_country(string $country): void
    {
        $data = $this->data($country);
        $before = now('UTC')->subSecond();
        $response = $this->postJson($this->url(), $data)->assertOk();
        $customer = Customer::findOrFail($response->json('data.id'));
        $declaration = $customer->operatingDeclaration;
        $this->assertSame('CL', $customer->operating_country_code);
        $this->assertSame($country === 'CL' ? '12.345.678-5' : null, $customer->rut);
        $this->assertSame('CL', $customer->branches->first()->operating_country_code);
        $this->assertSame(CustomerOperatingScope::VERSION, $declaration->version);
        $this->assertSame(CustomerOperatingScope::DECLARATION, $declaration->declaration_text);
        $this->assertTrue($declaration->accepted);
        $this->assertSame($this->actor->id, $declaration->accepted_by);
        $this->assertTrue($declaration->accepted_at->greaterThanOrEqualTo($before));
        $this->assertSame('pending', $customer->operatingReview->status);
        $this->assertSame($country, DB::table('customer_identifiers')->where('customer_id', $customer->id)->value('issuing_country_code'));
    }

    public function test_creation_requires_explicit_acceptance_and_rejects_country_manipulation(): void
    {
        $data = $this->data();
        unset($data['operating_declaration_accepted']);
        $this->postJson($this->url(), $data)->assertUnprocessable()->assertJsonValidationErrors('operating_declaration_accepted');
        $data['operating_declaration_accepted'] = true;
        foreach (['AR', 'cl', '', ['CL']] as $country) {
            $this->postJson($this->url(), array_merge($data, ['operating_country_code' => $country]))->assertUnprocessable()->assertJsonValidationErrors('operating_country_code');
        }
        $this->assertFalse(Customer::where('name', $data['name'])->exists());
    }

    public function test_existing_client_remains_unchanged_without_invented_acceptance(): void
    {
        $key = Str::lower(Str::random(12));
        $customer = Customer::create(['name' => 'Legacy'.$key, 'legal_name' => 'Legacy', 'holder' => 'H', 'rut' => 'historical',
            'domain' => $key, 'email' => $key.'@test.test', 'phone' => '123', 'status' => 1]);
        $data = $customer->only(['name', 'legal_name', 'holder', 'rut', 'phone', 'email', 'status']);
        $this->putJson($this->url().'/'.$customer->id, $data)->assertOk();
        $this->assertNull($customer->fresh()->operatingDeclaration);
        $this->assertNull($customer->fresh()->operatingReview);
        $this->assertSame('historical', $customer->fresh()->rut);
        $this->assertSame(1, $customer->fresh()->status);
        $this->putJson($this->url().'/'.$customer->id, $data + ['operating_country_code' => 'US'])->assertUnprocessable();
        $this->assertSame('CL', $customer->fresh()->operating_country_code);
    }

    public function test_reviews_and_declarations_are_append_only_and_idempotent(): void
    {
        $response = $this->postJson($this->url(), $this->data())->assertOk();
        $customer = Customer::findOrFail($response->json('data.id'));
        $declaration = $customer->operatingDeclaration;
        $frozen = $declaration->getRawOriginal();
        $data = $customer->only(['name', 'legal_name', 'holder', 'phone', 'email', 'status']);
        $data += ['operating_declaration_accepted' => true, 'operating_review_status' => 'reviewed', 'operating_review_note' => 'Administrative review only'];
        $this->putJson($this->url().'/'.$customer->id, $data)->assertOk();
        $this->putJson($this->url().'/'.$customer->id, $data)->assertOk();
        $this->assertSame(2, DB::table('customer_operating_reviews')->where('customer_id', $customer->id)->count());
        $data['operating_review_status'] = 'needs_information';
        $this->putJson($this->url().'/'.$customer->id, $data)->assertOk();
        $this->assertSame(3, DB::table('customer_operating_reviews')->where('customer_id', $customer->id)->count());
        $this->assertSame($frozen, $declaration->fresh()->getRawOriginal());
        foreach (['customer_operating_declarations', 'customer_operating_reviews'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    $query = DB::table($table)->where('customer_id', $customer->id);
                    $operation === 'delete' ? $query->delete() : $query->update(['customer_id' => $customer->id]);
                    $this->fail('Evidence must remain immutable.');
                } catch (\Illuminate\Database\QueryException $error) { $this->assertNotEmpty($error->errorInfo); }
            }
        }
        try {
            DB::table('customers')->where('id', $customer->id)->update(['operating_country_code' => 'AR']);
            $this->fail('SQL must reject another country.');
        } catch (\Illuminate\Database\QueryException $error) { $this->assertNotEmpty($error->errorInfo); }
        $migration = require database_path('migrations/2026_11_06_000100_add_customer_chile_operation.php');
        try { $migration->down(); $this->fail('Do not discard accepted declarations.'); }
        catch (\LogicException $error) { $this->assertStringContainsString('retained', $error->getMessage()); }
    }

    public function test_clinic_admin_cannot_record_superadmin_reviews_or_cross_tenant_data(): void
    {
        $response = $this->postJson($this->url(), $this->data())->assertOk();
        $customer = Customer::findOrFail($response->json('data.id'));
        $admin = User::where('customer_id', $customer->id)->firstOrFail();
        $this->actingAs($admin)->putJson($this->url().'/'.$customer->id, ['operating_review_status' => 'reviewed'])->assertForbidden();
        $this->assertSame('pending', $customer->fresh()->operatingReview->status);
        $other = Customer::create(['name' => 'Other'.Str::random(8), 'legal_name' => 'Other', 'holder' => 'Other', 'phone' => '123', 'email' => 'other@test.test']);
        try {
            DB::table('customer_operating_reviews')->insert(['customer_id' => $other->id, 'declaration_id' => $customer->operatingDeclaration->id,
                'status' => 'reviewed', 'reviewed_by' => $this->actor->id, 'reviewed_at' => now()]);
            $this->fail('Cross-tenant declaration must be rejected.');
        } catch (\Illuminate\Database\QueryException $error) { $this->assertNotEmpty($error->errorInfo); }
    }

    public function test_branches_inherit_scope_and_reject_independent_country_configuration(): void
    {
        $response = $this->postJson($this->url(), $this->data())->assertOk();
        $customer = Customer::findOrFail($response->json('data.id'));
        $url = $this->url().'/'.$customer->id.'/branches';
        $this->postJson($url, ['name' => 'Second', 'operating_country_code' => 'AR'])->assertUnprocessable();
        $this->post($url, ['name' => 'Second'])->assertRedirect()->assertSessionHasNoErrors();
        $branch = $customer->branches()->where('name', 'Second')->firstOrFail();
        $this->assertSame('CL', $branch->operating_country_code);
        $this->putJson($url.'/'.$branch->id, ['name' => 'Changed', 'operating_country_code' => 'US'])->assertUnprocessable();
        $this->assertSame('Second', $branch->fresh()->name);
        $other = Customer::create(['name' => 'Other'.Str::random(8), 'legal_name' => 'Other', 'holder' => 'Other', 'phone' => '123', 'email' => 'other@test.test']);
        $this->putJson($this->url().'/'.$other->id.'/branches/'.$branch->id, ['name' => 'Wrong owner'])->assertNotFound();
        $this->assertSame($customer->id, $branch->fresh()->customer_id);
        $this->get('http://'.config('app.domain').'/superadmin/customers?_vita_content=1')->assertOk()
            ->assertSee('País de operación')->assertSee('Chile (CL)')->assertSee('País emisor');
    }
}
