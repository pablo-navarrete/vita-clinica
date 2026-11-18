<?php

namespace Tests\Feature;

use App\Models\{Appointment, CustomerDirectoryProfile, DirectoryReview, PortalAccount, VitaWebsiteSetting, WebsitePage};
use App\Services\{CustomerDirectoryService, DirectoryReviewService};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectoryReviewTest extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as private signingSetUp; }

    private PortalAccount $account;
    private CustomerDirectoryProfile $profile;
    private string $portal;

    protected function setUp(): void
    {
        $this->signingSetUp();
        $this->account = new PortalAccount(['name'=>'Private patient name', 'email'=>Str::uuid().'@example.test', 'password'=>bcrypt('SafePassword123!')]);
        $this->account->forceFill(['email_verified_at'=>now(), 'active'=>true, 'session_version'=>1])->save();
        WebsitePage::create(['customer_id'=>$this->clinic->id, 'slug'=>'inicio', 'title'=>'Inicio', 'status'=>1]);
        $this->profile = CustomerDirectoryProfile::create(['customer_id'=>$this->clinic->id,'slug'=>'review-'.Str::lower(Str::random(12)),
            'description'=>'Public clinic', 'publication_enabled'=>true, 'authorized_at'=>now(), 'authorized_by'=>$this->doctor->id, 'authorization_version'=>'directory-v1']);
        VitaWebsiteSetting::updateOrCreate(['id'=>1], ['content'=>['published'=>true]]);
        $this->portal = 'http://'.config('app.domain').'/mi-salud/resenas/'.$this->profile->slug;
    }

    private function bookingEvidence(): void
    {
        $appointment = Appointment::create(['customer_id'=>$this->clinic->id, 'clinic_branch_id'=>$this->branch->id,
            'patient_id'=>$this->patient->id, 'professional_id'=>$this->doctor->id, 'start_at'=>now(), 'end_at'=>now()->addMinutes(30)]);
        $this->encounter->update(['appointment_id'=>$appointment->id]);
        $operation = (string)Str::uuid();
        DB::table('patient_registration_attempts')->insert(['operation_id'=>$operation,'customer_id'=>$this->clinic->id,
            'clinic_branch_id'=>$this->branch->id,'channel'=>'public_booking','status'=>'completed',
            'lookup_key_version'=>'test','document_hash'=>str_repeat('a',64),'request_hash'=>str_repeat('b',64),
            'patient_id'=>$this->patient->id,'appointment_id'=>$appointment->id,'contact_verified_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('portal_booking_authorizations')->insert(['id'=>(string)Str::uuid(), 'customer_id'=>$this->clinic->id,
            'portal_account_id'=>$this->account->id,'patient_id'=>$this->patient->id,'registration_operation'=>$operation,
            'session_hash'=>str_repeat('c',64),'encrypted_payload'=>'synthetic-test-evidence','expires_at'=>now()->subDay(),
            'authorized_at'=>now()->subDay(),'redeemed_at'=>now()->subDay(),'completed_at'=>now()->subDay(),'created_at'=>now(),'updated_at'=>now()]);
    }

    private function finishEncounter(): void
    {
        $this->sign($this->prepare())->assertOk();
    }

    private function asPatient(): void
    {
        $this->actingAs($this->account, 'mi_salud')->withSession(['portal_session_version'=>1]);
    }

    private function data(array $extra=[]): array
    {
        return $extra + ['encounter_id'=>$this->encounter->id,'target'=>'professional','rating'=>4,'public_name'=>'Paciente A.',
            'comment'=>'Muy buena comunicación y puntualidad.', 'consent'=>1];
    }

    public function test_only_completed_authorized_bookings_can_be_reviewed_and_duplicates_are_rejected(): void
    {
        $this->bookingEvidence();
        $this->asPatient();
        $this->post($this->portal, $this->data())->assertNotFound();
        Auth::shouldUse('web'); $this->actingAs($this->doctor); $this->app->instance('customer',$this->clinic);
        $this->finishEncounter(); $this->asPatient();
        $this->post($this->portal, $this->data())->assertRedirect($this->portal)->assertSessionHasNoErrors();
        $this->assertSame(1, DirectoryReview::where('customer_id',$this->clinic->id)->count());
        $this->post($this->portal, $this->data())->assertSessionHasErrors('review');
        $this->post($this->portal, $this->data(['rating'=>6]))->assertSessionHasErrors('rating');
        $this->post($this->portal, $this->data(['consent'=>0]))->assertSessionHasErrors('consent');
        $this->get($this->portal)->assertOk()->assertSee('Tu opinión publicada');
    }

    public function test_document_match_and_foreign_account_are_not_booking_authorization(): void
    {
        $this->finishEncounter(); $this->asPatient();
        $this->post($this->portal,$this->data())->assertNotFound();
        $this->assertFalse(app(DirectoryReviewService::class)->eligibleEncounters($this->account,$this->clinic->id)->exists());
    }

    public function test_a_completed_booking_from_another_account_does_not_allow_publication(): void
    {
        $this->bookingEvidence(); $this->finishEncounter();
        $other=new PortalAccount(['name'=>'Other','email'=>Str::uuid().'@example.test','password'=>bcrypt('SafePassword123!')]);
        $other->forceFill(['email_verified_at'=>now(),'active'=>true,'session_version'=>1])->save();
        $this->actingAs($other,'mi_salud')->withSession(['portal_session_version'=>1]);
        $this->post($this->portal,$this->data(['portal_account_id'=>$this->account->id]))->assertNotFound();
        $this->assertSame(0,DirectoryReview::where('customer_id',$this->clinic->id)->count());
    }

    public function test_review_destination_survives_login_and_panel_allows_withdrawal(): void
    {
        $this->review();
        $this->get($this->portal)->assertRedirect(route('mi-salud.login'));
        $this->post('http://'.config('app.domain').'/mi-salud/ingresar',[
            'email'=>$this->account->email,'password'=>'SafePassword123!',
        ])->assertRedirect($this->portal);
        $this->get('http://'.config('app.domain').'/mi-salud/panel')->assertOk()->assertSee('Mis opiniones públicas');
        $this->get($this->portal)->assertOk()->assertSee('Tu opinión publicada');
    }

    private function review(array $extra=[]): DirectoryReview
    {
        return DirectoryReview::create($extra + ['customer_id'=>$this->clinic->id,'portal_account_id'=>$this->account->id,
            'encounter_id'=>$this->encounter->id,'professional_id'=>$this->doctor->id,'public_name'=>'Public alias','rating'=>5,
            'comment'=>'<script>privateAttack()</script> Excellent attention.','publication_consented_at'=>now(),'consent_version'=>'directory-review-v1']);
    }

    public function test_public_card_average_and_comments_escape_text_and_do_not_expose_private_identity(): void
    {
        $review = $this->review();
        Auth::guard('web')->logout();
        $this->app->forgetInstance('customer');
        $this->get('http://'.config('app.domain').'/')->assertOk()->assertSee('5,0')->assertSee('1 opinión');
        $this->get('http://'.config('app.domain').'/clinicas/'.$this->profile->slug)->assertOk()
            ->assertSee('Public alias')->assertSee('&lt;script&gt;privateAttack()',false)
            ->assertDontSee('<script>privateAttack()',false)->assertDontSee($this->account->email)
            ->assertDontSee('Private patient name')->assertDontSee($this->patient->name)->assertDontSee($this->encounter->chief_complaint);
        $review->update(['withdrawn_at'=>now()]);
        $card=app(CustomerDirectoryService::class)->card(app(CustomerDirectoryService::class)->eligibleQuery()->whereKey($this->profile->id)->firstOrFail());
        $this->assertSame(0,$card['review_count']);
        $this->get('http://'.config('app.domain').'/clinicas/'.$this->profile->slug)->assertDontSee('Public alias');
    }

    public function test_responsible_professional_and_branch_admin_can_reply_but_other_professionals_cannot(): void
    {
        $review=$this->review(); $service=app(DirectoryReviewService::class);
        $this->assertTrue($service->canReply($this->doctor,$review));
        $other=$this->user();
        $this->assertFalse($service->canReply($other,$review));
        $this->actingAs($other)->post($this->root.'/directory-reviews/'.$review->id.'/reply',['body'=>'Thank you very much','consent'=>1])->assertForbidden();
        $this->actingAs($this->doctor)->post($this->root.'/directory-reviews/'.$review->id.'/reply',['body'=>'Thank you very much','consent'=>1])->assertRedirect();
        $this->assertSame(1,$review->replies()->count());
        $this->post($this->root.'/directory-reviews/'.$review->id.'/reply',['body'=>'Duplicate response','consent'=>1])->assertSessionHasErrors('body');
        $this->get($this->root.'/directory-reviews?_vita_frame=1&_vita_content=1')->assertOk()->assertSee('Public alias');
        $admin=$this->user('Administrador');
        $this->actingAs($admin)->post($this->root.'/directory-reviews/'.$review->id.'/reply',['body'=>'We appreciate your opinion','consent'=>1])->assertRedirect();
        $this->assertSame('clinic',$review->replies()->where('user_id',$admin->id)->firstOrFail()->author_role);
    }

    public function test_withdrawal_requires_ownership_and_works_after_clinic_unpublication(): void
    {
        $review=$this->review(); $this->asPatient();
        $this->profile->update(['publication_enabled'=>false]);
        $this->delete($this->portal)->assertRedirect(route('mi-salud.panel'));
        $this->assertNotNull($review->fresh()->withdrawn_at);
        $this->assertFalse(app(DirectoryReviewService::class)->canReply($this->doctor,$review->fresh()));
    }

    public function test_another_patient_cannot_withdraw_a_review_and_cross_tenant_reply_is_denied(): void
    {
        $review=$this->review();
        $other=new PortalAccount(['name'=>'Other','email'=>Str::uuid().'@example.test','password'=>bcrypt('SafePassword123!')]);
        $other->forceFill(['email_verified_at'=>now(),'active'=>true,'session_version'=>1])->save();
        $this->actingAs($other,'mi_salud')->withSession(['portal_session_version'=>1]);
        $this->delete($this->portal)->assertNotFound();
        $this->assertNull($review->fresh()->withdrawn_at);
        $foreign=$this->user(); $foreign->customer_id=$this->clinic->id+100000;
        $this->assertFalse(app(DirectoryReviewService::class)->canReply($foreign,$review));
    }

    public function test_guests_and_unverified_accounts_cannot_publish(): void
    {
        $this->get($this->portal)->assertRedirect(route('mi-salud.login'));
        $this->account->forceFill(['email_verified_at'=>null])->save();
        $this->asPatient();
        $this->post($this->portal,$this->data())->assertRedirect(route('mi-salud.verify.notice'));
        $this->assertSame(0,DirectoryReview::where('customer_id',$this->clinic->id)->count());
    }
}
