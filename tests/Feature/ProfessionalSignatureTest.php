<?php

namespace Tests\Feature;

use App\Models\ClinicBranch;
use App\Models\Customer;
use App\Models\ProfessionalSignatureAsset;
use App\Models\ProfessionalSignatureProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Signatures\ProfessionalSignatureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfessionalSignatureTest extends TestCase
{
    private Customer $clinic;
    private User $doctor;
    private ClinicBranch $branch;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Storage::fake('professional_signatures');
        $this->clinic = $this->clinic();
        $this->branch = ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Signature test branch', 'status' => 1]);
        $this->doctor = $this->professional($this->clinic, $this->branch);
        $this->root = 'http://' . $this->clinic->domain . '.' . config('app.domain');
        $this->actingAs($this->doctor);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function clinic(): Customer
    {
        $id = Str::lower(Str::random(10));
        return Customer::create(['name' => 'Signature test', 'legal_name' => 'Signature test', 'domain' => 'signature-' . $id,
            'holder' => 'Test', 'rut' => $id, 'phone' => '123456789', 'email' => $id . '@example.test', 'status' => 1]);
    }

    private function professional(Customer $clinic, ClinicBranch $branch, string $role = 'Doctor'): User
    {
        $id = Str::lower(Str::random(10));
        $user = User::create(['customer_id' => $clinic->id, 'name' => 'Signature Doctor', 'rut' => $id,
            'phone' => '123456789', 'email' => $id . '@example.test', 'password' => 'test-password', 'status' => 1]);
        $user->branches()->attach($branch->id, ['status' => 1]);
        $roleModel = Role::firstOrCreate(['customer_id' => $clinic->id, 'name' => $role]);
        $user->branchRoles()->attach($roleModel->id, ['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id]);
        return $user;
    }

    private function save(array $data = [])
    {
        return $this->post($this->root . '/profile/electronic-signature', ['method' => 'fes_vita'] + $data);
    }

    private function imageUrl(ProfessionalSignatureAsset $asset): string
    {
        return $this->root . '/profile/electronic-signature/images/' . $asset->uuid;
    }

    public function test_profile_upload_preview_replacement_and_removal_keep_private_versions(): void
    {
        $this->get($this->root . '/profile?_vita_frame=1&_vita_content=1')->assertOk()
            ->assertDontSee('data-signature-canvas', false);
        $this->get($this->root . '/profile/electronic-signature?_vita_frame=1&_vita_content=1')->assertOk()
            ->assertSee('Firma electrónica')->assertSee('data-signature-canvas', false)->assertSee($this->doctor->rut);
        $this->save(['signature' => UploadedFile::fake()->image('signature.png', 600, 180),
            'stamp' => UploadedFile::fake()->image('stamp.jpg', 300, 300)])->assertRedirect()->assertSessionHasNoErrors();
        $profile = ProfessionalSignatureProfile::where('user_id', $this->doctor->id)->firstOrFail();
        $first = $profile->signatureAsset;
        $stamp = $profile->stampAsset;
        $this->assertSame('image/png', $stamp->mime);
        Storage::disk('professional_signatures')->assertExists($first->path);
        $this->assertSame(hash('sha256', Storage::disk('professional_signatures')->get($first->path)), $first->sha256);
        $this->get($this->imageUrl($first))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $this->get($this->imageUrl($first))->headers->get('Cache-Control'));
        $this->save(['signature' => UploadedFile::fake()->image('new.jpg', 500, 160)])->assertSessionHasNoErrors();
        $second = $profile->fresh()->signatureAsset;
        $this->assertNotSame($first->path, $second->path);
        $this->assertSame($stamp->id, $profile->fresh()->stamp_asset_id);
        Storage::disk('professional_signatures')->assertExists($first->path);
        $this->get($this->imageUrl($first))->assertNotFound();
        $this->save(['remove_signature' => 1, 'remove_stamp' => 1])->assertSessionHasNoErrors();
        $this->assertNull($profile->fresh()->signature_asset_id);
        $this->assertNull($profile->fresh()->stamp_asset_id);
        $this->assertSame(3, ProfessionalSignatureAsset::where('user_id', $this->doctor->id)->count());
        Storage::disk('professional_signatures')->assertExists($second->path);
        Storage::disk('professional_signatures')->assertExists($stamp->path);
        $this->get($this->imageUrl($second))->assertNotFound();
        $this->assertSame(3, DB::table('clinic_activity_logs')->where('subject_type', ProfessionalSignatureProfile::class)->where('subject_id', $profile->id)->count());
    }

    public function test_other_professionals_admins_and_other_clinics_cannot_access_configuration(): void
    {
        $this->save(['signature' => UploadedFile::fake()->image('signature.png', 300, 100)])->assertSessionHasNoErrors();
        $asset = ProfessionalSignatureAsset::where('user_id', $this->doctor->id)->firstOrFail();
        $other = $this->professional($this->clinic, $this->branch);
        $this->actingAs($other)->get($this->imageUrl($asset))->assertNotFound();
        $this->postJson($this->root . '/profile/electronic-signature', ['method' => 'fes_vita', 'user_id' => $this->doctor->id, 'customer_id' => $this->clinic->id])->assertUnprocessable();
        $admin = $this->professional($this->clinic, $this->branch, 'Administrador');
        $this->actingAs($admin);
        $this->save()->assertForbidden();
        $this->get($this->imageUrl($asset))->assertForbidden();
        $global = User::create(['name' => 'Global admin', 'email' => Str::uuid() . '@example.test', 'rut' => Str::uuid(), 'phone' => '123', 'password' => 'test-password', 'status' => 1]);
        $globalRole = Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
        $global->roles()->attach($globalRole->id);
        $this->actingAs($global);
        $this->save()->assertForbidden();
        $this->get($this->imageUrl($asset))->assertForbidden();
        $foreign = $this->clinic();
        $branch = ClinicBranch::create(['customer_id' => $foreign->id, 'name' => 'Foreign', 'status' => 1]);
        $foreignDoctor = $this->professional($foreign, $branch);
        $this->actingAs($foreignDoctor)->get($this->imageUrl($asset))->assertForbidden();
        $this->assertFalse(app(ProfessionalSignatureService::class)->canManage($foreignDoctor, $this->clinic->id));
        $foreignRoot = 'http://' . $foreign->domain . '.' . config('app.domain');
        $this->actingAs($foreignDoctor)->get($foreignRoot . '/profile/electronic-signature/images/' . $asset->uuid)->assertNotFound();
        $this->assertSame(1, ProfessionalSignatureProfile::where('customer_id', $this->clinic->id)->count());
    }

    public function test_configuration_is_shared_between_branches_but_disabled_accounts_are_denied(): void
    {
        $this->save(['signature' => UploadedFile::fake()->image('signature.png', 300, 100)])->assertSessionHasNoErrors();
        $profile = ProfessionalSignatureProfile::where('user_id', $this->doctor->id)->firstOrFail();
        $branch = ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Second', 'status' => 1]);
        $this->doctor->branches()->attach($branch->id, ['status' => 1]);
        $this->doctor->branchRoles()->attach(Role::where('customer_id', $this->clinic->id)->where('name', 'Doctor')->value('id'), ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $branch->id]);
        $this->withSession(['active_branch_customer_id' => $this->clinic->id, 'active_branch_id' => $branch->id]);
        $this->get($this->imageUrl($profile->signatureAsset))->assertOk();
        $this->save(['stamp' => UploadedFile::fake()->image('stamp.png', 200, 200)])->assertSessionHasNoErrors();
        $this->assertSame(1, ProfessionalSignatureProfile::where('user_id', $this->doctor->id)->count());
        $this->assertSame($profile->signature_asset_id, $profile->fresh()->signature_asset_id);
        $this->doctor->update(['status' => 0]);
        $this->assertFalse(app(ProfessionalSignatureService::class)->canManage($this->doctor, $this->clinic->id));
    }

    public function test_invalid_images_and_unavailable_methods_are_rejected_and_png_is_sanitized(): void
    {
        $endpoint = $this->root . '/profile/electronic-signature';
        foreach ([
            UploadedFile::fake()->createWithContent('fake.png', '<?php echo "bad";'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            UploadedFile::fake()->image('wide.png', 2401, 16),
            UploadedFile::fake()->image('small.jpg', 31, 16),
            UploadedFile::fake()->image('big.png', 100, 100)->size(2049),
        ] as $file) {
            $this->postJson($endpoint, ['method' => 'fes_vita', 'signature' => $file])->assertUnprocessable()->assertJsonValidationErrors('signature');
        }
        $this->postJson($endpoint, ['method' => 'fea'])->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->assertSame(0, ProfessionalSignatureAsset::where('user_id', $this->doctor->id)->count());
        $image = UploadedFile::fake()->image('drawn.png', 1000, 320);
        $polyglot = UploadedFile::fake()->createWithContent('drawn.png', file_get_contents($image->getPathname()) . '<?php dangerous_payload(); ?>');
        $this->save(['signature' => $polyglot])->assertSessionHasNoErrors();
        $asset = ProfessionalSignatureAsset::where('user_id', $this->doctor->id)->firstOrFail();
        $stored = Storage::disk('professional_signatures')->get($asset->path);
        $this->assertStringNotContainsString('dangerous_payload', $stored);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($stored)[2]);
        $this->assertSame(1000, $asset->width);
    }

    public function test_failed_multi_image_save_rolls_back_and_only_removes_uncommitted_files(): void
    {
        $this->save(['signature' => UploadedFile::fake()->image('signature.png', 300, 100)])->assertSessionHasNoErrors();
        $profile = ProfessionalSignatureProfile::where('user_id', $this->doctor->id)->firstOrFail();
        $original = $profile->signatureAsset;
        try {
            app(ProfessionalSignatureService::class)->save($this->doctor, $this->clinic->id, [
                'method' => 'fes_vita', 'signature' => UploadedFile::fake()->image('new.png', 300, 100),
                'stamp' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>'),
            ]);
            $this->fail('The invalid stamp must reject the complete operation.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('stamp', $exception->errors());
        }
        $this->assertSame($original->id, $profile->fresh()->signature_asset_id);
        $this->assertSame(1, ProfessionalSignatureAsset::where('user_id', $this->doctor->id)->count());
        $this->assertSame([$original->path], Storage::disk('professional_signatures')->allFiles());
    }
}
