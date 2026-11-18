<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Customer,User,Role,CustomerDirectoryProfile,WebsitePage,Logo};
use App\Services\CustomerDirectoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class CustomerDirectoryTest extends TestCase
{
    private $customer; private $admin; private $root;
    protected function setUp(): void {
        parent::setUp();DB::beginTransaction();$key=Str::lower(Str::random(12));
        $this->customer=Customer::create(['name'=>'Directory clinic','legal_name'=>'PRIVATE LEGAL','holder'=>'PRIVATE HOLDER','domain'=>'dir-'.$key,'rut'=>$key,'email'=>'private@example.test','phone'=>'123','status'=>1]);
        $branch=$this->customer->branches()->create(['name'=>'Main','status'=>1]);
        $this->admin=User::create(['customer_id'=>$this->customer->id,'name'=>'Admin','rut'=>$key.'a','email'=>$key.'@test.test','password'=>bcrypt('password'),'phone'=>'123','status'=>1]);
        $role=Role::create(['customer_id'=>$this->customer->id,'name'=>'Administrador']);
        $this->admin->branches()->attach($branch->id,['status'=>1]);
        $this->admin->branchRoles()->attach($role->id,['customer_id'=>$this->customer->id,'clinic_branch_id'=>$branch->id]);
        WebsitePage::create(['customer_id'=>$this->customer->id,'slug'=>'inicio','title'=>'Inicio','status'=>1]);
        $this->actingAs($this->admin);session(['active_branch_id'=>$branch->id,'active_branch_customer_id'=>$this->customer->id]);
        $this->root='http://'.$this->customer->domain.'.'.config('app.domain');
    }
    protected function tearDown(): void {DB::rollBack();parent::tearDown();}
    private function data(array $extra=[]): array {return array_merge(['description'=>'Atención pública de prueba','revision'=>0,'publication_enabled'=>0],$extra);}
    public function test_deleted_inactive_and_unconsented_profiles_are_not_public_and_search_parameters_are_safe(): void {
        $id=$this->publishForSpecialtySearch();$p=$this->customer->directoryProfile()->sole();
        $base='http://'.config('app.domain');$url=$base.'/clinicas/'.$p->slug;
        $this->customer->update(['status'=>0]);$this->get($url)->assertNotFound();
        $this->customer->update(['status'=>1]);$this->customer->delete();$this->get($url)->assertNotFound();
        $this->customer->restore();$p->update(['authorized_at'=>null]);$this->get($url)->assertNotFound();
        $p->update(['authorized_at'=>now()]);
        $this->get($base.'/?'.http_build_query(['buscar'=>"%' OR 1=1 --",'especialidad'=>$id]))->assertOk()
            ->assertDontSee('PRIVATE LEGAL')->assertDontSee('private@example.test')->assertDontSee('Directory clinic');
        $this->getJson($base.'/?'.http_build_query(['especialidad'=>'1001 OR 1=1']))->assertUnprocessable();
        $names=collect(config('specialties.catalog'))->map(fn($name)=>mb_strtolower(Str::ascii(trim($name))))->all();
        $this->assertCount(count($names),array_unique($names),'Master specialty names must be unique after normalization.');
    }
    public function test_public_seo_fields_escape_untrusted_clinic_content(): void {
        $this->customer->update(['name'=>'Clinic </title><script>alert(7)</script>']);
        app(CustomerDirectoryService::class)->save($this->admin,$this->customer,$this->data([
            'description'=>'"><script>alert(8)</script>', 'publication_enabled'=>1,'consent'=>1]));
        $profile=$this->customer->directoryProfile()->sole();
        \App\Models\VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['published'=>true]]);
        auth()->logout();
        $this->get('http://'.config('app.domain').'/clinicas/'.$profile->slug)->assertOk()
            ->assertDontSee('<script>alert(7)</script>',false)->assertDontSee('<script>alert(8)</script>',false)
            ->assertSee('&lt;script&gt;alert(7)&lt;/script&gt;',false)->assertSee('Saltar al contenido');
    }
    private function offerPediatrics($customer = null, $user = null, $branch = null): void {
        $customer ??= $this->customer;$user ??= $this->admin;$branch ??= $customer->branches()->where('status',1)->firstOrFail();
        $role=Role::firstOrCreate(['customer_id'=>$customer->id,'name'=>'Doctor']);
        if (!$user->branchRoles()->wherePivot('clinic_branch_id',$branch->id)->whereKey($role->id)->exists())
            $user->branchRoles()->attach($role->id,['customer_id'=>$customer->id,'clinic_branch_id'=>$branch->id]);
        $specialty=\App\Models\Specialty::create(['customer_id'=>$customer->id,'name'=>'PEDIATRIA','catalog_id'=>array_search('Pediatría',config('specialties.catalog'),true),'status'=>1,'public_visible'=>1]);
        $user->specialties()->attach($specialty->id,['customer_id'=>$customer->id,'clinic_branch_id'=>$branch->id]);
    }
    private function publicDirectoryClinic(string $name, float $latitude = -32.88): Customer {
        $key=Str::lower(Str::random(12));
        $customer=Customer::create(['name'=>$name,'legal_name'=>'Private','holder'=>'Private','domain'=>'dir-'.$key,'rut'=>$key,'email'=>$key.'@test.test','phone'=>'123','status'=>1]);
        $branch=$customer->branches()->create(['name'=>'Private branch','status'=>1]);
        $user=User::create(['customer_id'=>$customer->id,'name'=>'PRIVATE PROVIDER','rut'=>$key,'email'=>'private-'.$key.'@test.test','password'=>'password','phone'=>'123','status'=>1]);
        $user->branches()->attach($branch->id,['status'=>1]);
        WebsitePage::create(['customer_id'=>$customer->id,'slug'=>'inicio','title'=>'Inicio','status'=>1]);
        CustomerDirectoryProfile::create(['customer_id'=>$customer->id,'slug'=>$key,'description'=>'Presentación pública','publication_enabled'=>true,'authorized_by'=>$user->id,'authorized_at'=>now(),'authorization_version'=>'directory-v1','city'=>'Quillota','latitude'=>$latitude,'longitude'=>-71.25]);
        $this->offerPediatrics($customer,$user,$branch);
        return $customer;
    }
    private function publishForSpecialtySearch(): int {
        $this->offerPediatrics();
        app(CustomerDirectoryService::class)->save($this->admin,$this->customer,$this->data(['publication_enabled'=>1,'consent'=>1,'commune'=>'Quillota','latitude'=>-32.88,'longitude'=>-71.25]));
        \App\Models\VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['published'=>true]]);
        auth()->logout();
        return (int)array_search('Pediatría',config('specialties.catalog'),true);
    }

    public function test_specialty_search_combines_location_deduplicates_clinics_and_highlights_verified_detail(): void {
        $id=$this->publishForSpecialtySearch();$this->offerPediatrics();
        $other=$this->publicDirectoryClinic('Second pediatric clinic');
        $base='http://'.config('app.domain');
        $response=$this->get($base.'/?especialidad='.$id)->assertOk()->assertSee('Directory clinic')->assertSee('Second pediatric clinic')->assertSee('Ofrece Pediatría')
            ->assertSee('class="vita-specialty-menu"',false)->assertSee('Buscar clínicas por especialidad')
            ->assertSee(route('vita.directory.index',['especialidad'=>$id]));
        $this->assertSame(2,substr_count($response->getContent(),'<article class="directory-card">'));
        $this->assertSame(1,substr_count($response->getContent(),'value="'.$id.'" selected>Pediatría</option>'));
        $this->get($base.'/?especialidad='.$id.'&buscar=Directory')->assertOk()->assertSee('Directory clinic')->assertDontSee('Second pediatric clinic');
        $this->get($base.'/?especialidad='.$id.'&buscar=Quillota')->assertOk()->assertSee('Directory clinic')->assertSee('Second pediatric clinic');
        $profile=$this->customer->directoryProfile()->sole();
        $this->assertStringContainsString('/clinicas/'.$profile->slug.'?especialidad='.$id,$response->getContent());
        $detail=$this->get($base.'/clinicas/'.$profile->slug.'?especialidad='.$id)->assertOk()->assertSee('Esta clínica ofrece Pediatría');
        $this->assertStringContainsString('<link rel="canonical" href="'.$base.'/clinicas/'.$profile->slug.'">',$detail->getContent());
        $this->admin->update(['status'=>0]);
        $this->get($base.'/clinicas/'.$profile->slug.'?especialidad='.$id)->assertOk()->assertDontSee('Esta clínica ofrece Pediatría');
        $this->get($base.'/?especialidad='.$id)->assertOk()->assertDontSee('Directory clinic')->assertSee('Second pediatric clinic')->assertDontSee('PRIVATE PROVIDER');
    }

    public function test_options_and_results_disappear_on_visibility_membership_branch_and_consent_changes(): void {
        $id=$this->publishForSpecialtySearch();$service=app(CustomerDirectoryService::class);$branch=$this->customer->branches()->sole();
        $this->assertTrue($service->specialtyOptions()->contains('id',$id));
        $branch->update(['status'=>0]);$this->assertFalse($service->specialtyOptions()->contains('id',$id));
        $branch->update(['status'=>1]);
        $this->admin->branches()->updateExistingPivot($branch->id,['status'=>0]);$this->assertFalse($service->specialtyOptions()->contains('id',$id));
        $this->admin->branches()->updateExistingPivot($branch->id,['status'=>1]);
        \App\Models\Specialty::where('customer_id',$this->customer->id)->update(['public_visible'=>0]);$this->assertFalse($service->specialtyOptions()->contains('id',$id));
        \App\Models\Specialty::where('customer_id',$this->customer->id)->update(['public_visible'=>1]);
        $this->customer->directoryProfile()->update(['publication_enabled'=>false]);$this->assertFalse($service->specialtyOptions()->contains('id',$id));
        $base='http://'.config('app.domain');
        $this->get($base.'/?especialidad='.$id)->assertOk()->assertSee('No encontramos clínicas')->assertDontSee('Directory clinic');
        $this->getJson($base.'/?especialidad=999999')->assertUnprocessable();
        $this->getJson($base.'/?especialidad[]=1')->assertUnprocessable();
        $this->getJson($base.'/clinicas/missing?especialidad=999999')->assertUnprocessable();
    }

    public function test_specialty_search_preserves_pagination_nearby_order_and_has_no_query_per_card(): void {
        $id=$this->publishForSpecialtySearch();$base='http://'.config('app.domain');
        DB::enableQueryLog();DB::flushQueryLog();
        try {
            $this->get($base.'/?especialidad='.$id)->assertOk();$small=count(DB::getQueryLog());
            DB::disableQueryLog();
            for($i=0;$i<13;$i++)$this->publicDirectoryClinic('Clinic '.$i,-33.0-$i/10);
            DB::enableQueryLog();DB::flushQueryLog();
            $response=$this->get($base.'/?especialidad='.$id.'&buscar=clinic&cercanas=1');
            // No location yet: the filter is preserved but requires location consent.
            $response->assertOk();$large=count(DB::getQueryLog());
            $this->assertLessThanOrEqual($small+2,$large);
            $response=$this->withSession(['directory_location'=>['latitude'=>-32.88,'longitude'=>-71.25,'expires_at'=>time()+1800]])
                ->get($base.'/?especialidad='.$id.'&buscar=clinic&cercanas=1')->assertOk();
            $this->assertSame(12,substr_count($response->getContent(),'<article class="directory-card">'));
            $this->assertStringContainsString('especialidad='.$id.'&amp;buscar=clinic&amp;cercanas=1&amp;page=2',$response->getContent());
            $this->assertLessThan(strpos($response->getContent(),'Clinic 0'),strpos($response->getContent(),'Directory clinic'));
            $this->get($base.'/?especialidad='.$id.'&buscar=clinic&cercanas=1&page=2')->assertOk()->assertSee('Página 2');
        } finally {DB::disableQueryLog();DB::flushQueryLog();}
    }
    public function test_detail_shows_only_authorized_public_data_and_safe_links_and_revokes_immediately(): void {
        $service=app(CustomerDirectoryService::class);$branch=$this->customer->branches()->sole();
        $branch->update(['address'=>'Calle Pública 123']);
        $branch->forceFill(['directory_publication_enabled'=>true,'directory_commune'=>'Quillota'])->save();
        $hidden=$this->customer->branches()->create(['name'=>'HIDDEN BRANCH','status'=>1]);
        $inactive=$this->customer->branches()->create(['name'=>'INACTIVE BRANCH','status'=>0]);
        $inactive->forceFill(['directory_publication_enabled'=>true])->save();
        \App\Models\WebsiteTopbar::create(['customer_id'=>$this->customer->id,'enabled'=>true,'phone'=>'+56 9 1234 5678','email'=>'public-contact@example.test']);
        \App\Models\WebsiteSocialSetting::create(['customer_id'=>$this->customer->id,'social_links'=>[
            ['network'=>'instagram','url'=>'https://instagram.com/public-clinic'],
            ['network'=>'facebook','url'=>'javascript:alert(1)'],
            ['network'=>'youtube','url'=>'https://secret@example.com/channel'],
            ['network'=>'unknown','url'=>'https://example.com/unknown']]]);
        $service->save($this->admin,$this->customer,$this->data(['publication_enabled'=>1,'consent'=>1,'show_public_contact'=>1,'show_public_social_links'=>1]));
        $profile=$this->customer->directoryProfile()->sole();
        \App\Models\VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['published'=>true]]);auth()->logout();
        $url='http://'.config('app.domain').'/clinicas/'.$profile->slug;
        $response=$this->get($url)->assertOk()->assertSee('Calle Pública 123')->assertSee('Ver mapa')
            ->assertSee('public-contact@example.test')->assertSee('https://instagram.com/public-clinic')
            ->assertDontSee('private@example.test')->assertDontSee('HIDDEN BRANCH')->assertDontSee('INACTIVE BRANCH')
            ->assertDontSee('javascript:')->assertDontSee('secret@example.com')->assertDontSee('example.com/unknown');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $profile->update(['show_public_contact'=>false,'show_public_social_links'=>false]);
        $branch->update(['status'=>false]);
        $this->get($url)->assertOk()->assertDontSee('public-contact@example.test')->assertDontSee('instagram.com/public-clinic')
            ->assertDontSee('Nuestras sedes')->assertDontSee('id="clinic-contact"',false)->assertDontSee('Ver mapa');
        $profile->update(['suspended_at'=>now()]);$this->get($url)->assertNotFound();
    }

    public function test_detail_uses_normalized_operational_specialties_without_duplicates_or_tenant_leaks(): void {
        $branch=$this->customer->branches()->sole();
        $role=Role::create(['customer_id'=>$this->customer->id,'name'=>'Doctor']);
        $this->admin->branchRoles()->attach($role->id,['customer_id'=>$this->customer->id,'clinic_branch_id'=>$branch->id]);
        $catalog=(int)array_search('Pediatría',config('specialties.catalog'),true);
        foreach (['PEDIATRIA','Pediatra'] as $name) {
            $s=\App\Models\Specialty::create(['customer_id'=>$this->customer->id,'name'=>$name,'catalog_id'=>$catalog,'status'=>1,'public_visible'=>1]);
            $this->admin->specialties()->attach($s->id,['customer_id'=>$this->customer->id,'clinic_branch_id'=>$branch->id]);
        }
        app(CustomerDirectoryService::class)->save($this->admin,$this->customer,$this->data());
        $profile=$this->customer->directoryProfile()->sole();$service=app(CustomerDirectoryService::class);
        $this->assertSame(['Pediatría'],$service->detail($profile)['specialties']);
        $this->admin->update(['status'=>0]);$this->assertSame([],$service->detail($profile)['specialties']);
        $this->admin->update(['status'=>1]);$branch->update(['status'=>0]);$this->assertSame([],$service->detail($profile)['specialties']);
        $branch->update(['status'=>1]);$this->admin->branches()->updateExistingPivot($branch->id,['status'=>0]);
        $this->assertSame([],$service->detail($profile)['specialties']);
        $other=Customer::create(['name'=>'Other detail','legal_name'=>'Other','holder'=>'Other','rut'=>Str::random(12),'email'=>'other@test.test','phone'=>'123']);
        $foreign=$other->branches()->create(['name'=>'FOREIGN PUBLIC BRANCH','status'=>1]);
        $foreign->forceFill(['directory_publication_enabled'=>true])->save();
        \App\Models\WebsiteTopbar::create(['customer_id'=>$other->id,'enabled'=>true,'email'=>'foreign-contact@example.test']);
        $profile->update(['show_public_contact'=>true]);
        $this->assertSame([],$service->detail($profile)['branches']);$this->assertSame([],$service->detail($profile)['contact']);
    }

    public function test_directory_cover_links_to_detail_and_detail_does_not_publish_disabled_contact_or_empty_maps(): void {
        $service=app(CustomerDirectoryService::class);
        $branch=$this->customer->branches()->sole();$branch->forceFill(['directory_publication_enabled'=>true])->save();
        \App\Models\WebsiteTopbar::create(['customer_id'=>$this->customer->id,'enabled'=>false,'phone'=>'123456789','email'=>'disabled-public@example.test']);
        $service->save($this->admin,$this->customer,$this->data(['publication_enabled'=>1,'consent'=>1,'show_public_contact'=>1]));
        $profile=$this->customer->directoryProfile()->sole();
        \App\Models\VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['published'=>true]]);auth()->logout();
        $base='http://'.config('app.domain');
        $this->get($base.'/')->assertOk()->assertSee('class="directory-cover-link"',false)->assertSee(route('vita.directory.show',$profile->slug));
        $this->get($base.'/clinicas/'.$profile->slug)->assertOk()->assertSee('Nuestras sedes')
            ->assertDontSee('Ver mapa')->assertDontSee('disabled-public@example.test')->assertDontSee('Especialidades disponibles')
            ->assertSee('property="og:title"',false)->assertSee('rel="canonical"',false)
            ->assertSee($service->websiteUrl($this->customer));
    }

    public function test_detail_additional_queries_remain_bounded_with_many_public_branches(): void {
        $service=app(CustomerDirectoryService::class);
        $service->save($this->admin,$this->customer,$this->data(['publication_enabled'=>1,'consent'=>1,'show_public_contact'=>1,'show_public_social_links'=>1]));
        for($i=0;$i<20;$i++) {
            $branch=$this->customer->branches()->create(['name'=>'Sede '.$i,'status'=>1]);
            $branch->forceFill(['directory_publication_enabled'=>true])->save();
        }
        $profile=$service->eligibleQuery()->where('customer_id',$this->customer->id)->firstOrFail();
        DB::enableQueryLog();DB::flushQueryLog();
        try {
            $detail=$service->detail($profile);
            $this->assertCount(20,$detail['branches']);
            $this->assertLessThanOrEqual(5,count(DB::getQueryLog()),'Public sections must not issue a query per branch or professional.');
        } finally {DB::disableQueryLog();DB::flushQueryLog();}
    }
    public function test_new_public_authorizations_default_to_disabled_and_configuration_reuses_public_sources(): void {
        \App\Models\WebsiteTopbar::create(['customer_id'=>$this->customer->id,'enabled'=>true,'phone'=>'PUBLIC PHONE','email'=>'public@example.test']);
        $this->put($this->root.'/directory-profile',$this->data())->assertRedirect()->assertSessionHasNoErrors();
        $profile=$this->customer->directoryProfile()->firstOrFail();
        $this->assertFalse($profile->show_public_contact);
        $this->assertFalse($profile->show_public_social_links);
        $this->assertFalse($this->customer->branches()->sole()->directory_publication_enabled);
        $this->get($this->root.'/directory-profile?_vita_content=1')->assertOk()
            ->assertSee('Contacto público y redes sociales')->assertSee('Sedes públicas')
            ->assertSee('PUBLIC PHONE')->assertSee('public@example.test')->assertDontSee('private@example.test');
    }

    public function test_authorizations_and_branch_locations_are_saved_revoked_and_audited_together(): void {
        $branch=$this->customer->branches()->sole();
        $payload=$this->data(['show_public_contact'=>1,'show_public_social_links'=>1,'commune'=>'Quillota',
            'branches'=>[['id'=>$branch->id,'publication_enabled'=>1,'commune'=>'Quillota','region'=>'Valparaíso']]]);
        $this->put($this->root.'/directory-profile',$payload)->assertRedirect()->assertSessionHasNoErrors();
        $p=$this->customer->directoryProfile()->sole();
        $this->assertTrue($p->show_public_contact);$this->assertTrue($p->show_public_social_links);
        $this->assertSame('Quillota',$p->commune);
        $this->assertTrue($branch->fresh()->directory_publication_enabled);
        $audit=json_decode(DB::table('customer_directory_events')->where('customer_id',$this->customer->id)->latest('id')->value('details'),true);
        $this->assertSame($branch->id,$audit['branch_changes'][0]['branch_id']);
        $this->assertFalse($audit['branch_changes'][0]['before']['directory_publication_enabled']);
        $this->assertTrue($audit['branch_changes'][0]['after']['directory_publication_enabled']);
        $this->assertSame($this->admin->id,(int)DB::table('customer_directory_events')->where('customer_id',$this->customer->id)->value('actor_id'));
        $this->put($this->root.'/directory-profile',$this->data(['revision'=>1,'show_public_contact'=>0,'show_public_social_links'=>0,
            'branches'=>[['id'=>$branch->id,'publication_enabled'=>0]]]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($p->fresh()->show_public_contact);$this->assertFalse($branch->fresh()->directory_publication_enabled);
        $this->assertSame(2,DB::table('customer_directory_events')->where('customer_id',$this->customer->id)->count());
    }

    public function test_foreign_inactive_deleted_and_duplicate_branches_cannot_be_published(): void {
        $foreign=Customer::create(['name'=>'Foreign','legal_name'=>'Foreign','holder'=>'Holder','rut'=>Str::random(12),'email'=>'foreign@test.test','phone'=>'123']);
        $foreignBranch=$foreign->branches()->create(['name'=>'Private foreign branch','status'=>1]);
        $inactive=$this->customer->branches()->create(['name'=>'Inactive','status'=>0]);
        $deleted=$this->customer->branches()->create(['name'=>'Deleted','status'=>1]);$deleted->delete();
        foreach ([$foreignBranch->id,$inactive->id,$deleted->id] as $id) {
            $this->putJson($this->root.'/directory-profile',$this->data(['branches'=>[['id'=>$id,'publication_enabled'=>1]]]))
                ->assertUnprocessable()->assertJsonValidationErrors('branches');
        }
        $branch=$this->customer->branches()->where('status',true)->sole();
        $this->putJson($this->root.'/directory-profile',$this->data(['branches'=>[
            ['id'=>$branch->id,'publication_enabled'=>1],['id'=>$branch->id,'publication_enabled'=>0]]]))->assertUnprocessable();
        $this->assertDatabaseMissing('customer_directory_profiles',['customer_id'=>$this->customer->id]);
        $this->assertFalse($foreignBranch->fresh()->directory_publication_enabled);
        $this->get($this->root.'/directory-profile?_vita_content=1')->assertOk()->assertDontSee('Private foreign branch')->assertDontSee('Inactive');
    }

    public function test_stale_revision_and_missing_consent_roll_back_branch_changes(): void {
        $branch=$this->customer->branches()->sole();
        $this->put($this->root.'/directory-profile',$this->data())->assertRedirect();
        $changes=['show_public_contact'=>1,'branches'=>[['id'=>$branch->id,'publication_enabled'=>1]]];
        $this->putJson($this->root.'/directory-profile',$this->data($changes))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->putJson($this->root.'/directory-profile',$this->data($changes+['revision'=>1,'publication_enabled'=>1]))->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->assertFalse($branch->fresh()->directory_publication_enabled);
        $this->assertFalse($this->customer->directoryProfile()->sole()->show_public_contact);
        $this->assertSame(1,DB::table('customer_directory_events')->where('customer_id',$this->customer->id)->count());
    }

    public function test_unauthorized_user_cannot_change_new_public_authorizations(): void {
        $branch=$this->customer->branches()->sole();$this->admin->branchRoles()->detach();
        $this->putJson($this->root.'/directory-profile',$this->data(['show_public_contact'=>1,
            'branches'=>[['id'=>$branch->id,'publication_enabled'=>1]]]))->assertForbidden();
        $this->assertFalse($branch->fresh()->directory_publication_enabled);
        $this->assertDatabaseMissing('customer_directory_profiles',['customer_id'=>$this->customer->id]);
    }

    public function test_audit_failure_rolls_back_public_authorizations_and_branch_locations(): void {
        $branch=$this->customer->branches()->sole();$fail=true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_contains($query->sql,'insert into `customer_directory_events`')) throw new \RuntimeException('Audit failure');
        });
        try {
            app(CustomerDirectoryService::class)->save($this->admin,$this->customer,$this->data([
                'show_public_contact'=>1,'branches'=>[['id'=>$branch->id,'publication_enabled'=>1,'commune'=>'Quillota']]]));
            $this->fail('The entire change must roll back.');
        } catch (\RuntimeException $e) { $this->assertSame('Audit failure',$e->getMessage()); }
        finally { $fail=false; }
        $this->assertFalse($branch->fresh()->directory_publication_enabled);
        $this->assertNull($branch->fresh()->directory_commune);
        $this->assertDatabaseMissing('customer_directory_profiles',['customer_id'=>$this->customer->id]);
        $this->assertSame(0,DB::table('customer_directory_events')->where('customer_id',$this->customer->id)->count());
    }
    public function test_explicit_consent_optional_images_location_and_withdrawal(): void {
        $url=$this->root.'/directory-profile';
        $this->put($url,$this->data())->assertRedirect()->assertSessionHasNoErrors();
        $p=CustomerDirectoryProfile::where('customer_id',$this->customer->id)->firstOrFail();
        $this->assertFalse($p->publication_enabled);
        $this->putJson($url,$this->data(['revision'=>1,'publication_enabled'=>1]))->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->put($url,$this->data(['revision'=>1,'publication_enabled'=>1,'consent'=>1]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($p->fresh()->publication_enabled);$this->assertNotNull($p->fresh()->authorized_at);
        $this->assertNull($p->fresh()->public_address);$this->assertNull($p->fresh()->website_banner_id);
        $this->put($url,$this->data(['revision'=>2]))->assertRedirect();
        $this->assertNotNull($p->fresh()->withdrawn_at);
        $this->assertSame(3,DB::table('customer_directory_events')->where('customer_id',$this->customer->id)->count());
    }
    public function test_foreign_resources_stale_revision_and_non_admin_are_rejected(): void {
        $other=Customer::create(['name'=>'Other','legal_name'=>'Other','holder'=>'Holder','rut'=>Str::random(12),'email'=>'other@test.test','phone'=>'123']);
        $logo=Logo::create(['customer_id'=>$other->id,'name'=>'Other logo','url'=>'other.png','status'=>1]);
        $this->putJson($this->root.'/directory-profile',$this->data(['logo_id'=>$logo->id]))->assertUnprocessable()->assertJsonValidationErrors('logo_id');
        $this->assertDatabaseMissing('customer_directory_profiles',['customer_id'=>$this->customer->id]);
        $this->putJson($this->root.'/directory-profile',$this->data(['revision'=>5]))->assertUnprocessable();
        $this->admin->branchRoles()->detach();
        $this->get($this->root.'/directory-profile?_vita_content=1')->assertForbidden();
    }
    public function test_public_pages_search_privacy_eligibility_and_sitemap(): void {
        app(CustomerDirectoryService::class)->save($this->admin,$this->customer,$this->data(['publication_enabled'=>1,'consent'=>1]));
        $p=CustomerDirectoryProfile::where('customer_id',$this->customer->id)->firstOrFail();
        \App\Models\VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['published'=>true]]);
        auth()->logout();
        $base='http://'.config('app.domain');
        $this->get($base.'/')->assertOk()->assertSee('Directory clinic')->assertDontSee('PRIVATE LEGAL')->assertDontSee('private@example.test')->assertSee($this->customer->domain.'.'.config('app.domain'));
        $this->get($base.'/?buscar=missing')->assertOk()->assertDontSee('Directory clinic');
        $this->get($base.'/clinicas?buscar=missing')->assertStatus(301)->assertRedirect($base.'?buscar=missing');
        $this->get($base.'/clinicas/'.$p->slug)->assertOk()->assertSee('Atención pública de prueba')->assertDontSee('PRIVATE HOLDER');
        $this->get($base.'/directory-sitemap.xml')->assertOk()->assertSee($p->slug);
        \App\Models\WebsiteMaintenance::create(['customer_id'=>$this->customer->id,'enabled'=>true]);
        $this->get($base.'/clinicas/'.$p->slug)->assertNotFound();
        $this->get($base.'/directory-sitemap.xml')->assertDontSee($p->slug);
        \App\Models\WebsiteMaintenance::where('customer_id',$this->customer->id)->delete();
        $p->update(['suspended_at'=>now()]);
        $this->get($base.'/clinicas/'.$p->slug)->assertNotFound();
        $p->update(['suspended_at'=>null,'publication_enabled'=>false]);
        $this->get($base.'/clinicas/'.$p->slug)->assertNotFound();
        $this->assertSame(0,\App\Models\CustomerSubscription::where('customer_id',$this->customer->id)->count());
    }
    public function test_moderation_cannot_authorize_and_retains_consent_history(): void {
        app(CustomerDirectoryService::class)->save($this->admin,$this->customer,$this->data());
        $p=CustomerDirectoryProfile::where('customer_id',$this->customer->id)->firstOrFail();
        $global=User::create(['name'=>'Global','rut'=>Str::random(12),'email'=>Str::random(12).'@test.test','password'=>bcrypt('password'),'phone'=>'123','status'=>1]);
        $role=Role::firstOrCreate(['customer_id'=>null,'name'=>'Super Administrador']);$global->roles()->attach($role->id);
        try {app(CustomerDirectoryService::class)->save($global,$this->customer,$this->data(['revision'=>1,'publication_enabled'=>1,'consent'=>1]));$this->fail('No impersonation');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $this->actingAs($global);
        $url='http://'.config('app.domain').'/customer-directory/'.$p->id;
        $this->put($url,['suspend'=>1,'revision'=>1,'reason'=>'Review test'])->assertRedirect();
        $this->assertNotNull($p->fresh()->suspended_at);$this->assertFalse($p->fresh()->publication_enabled);
        $this->putJson($url,['suspend'=>0,'revision'=>1,'reason'=>'Stale'])->assertStatus(409);
        $this->put($url,['suspend'=>0,'revision'=>2,'reason'=>'Reviewed'])->assertRedirect();
        $this->assertFalse($p->fresh()->publication_enabled);$this->assertNull($p->fresh()->authorized_at);
        try {DB::table('customer_directory_events')->where('customer_id',$this->customer->id)->delete();$this->fail('Audit must be immutable');}
        catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('immutable',$e->getMessage());}
    }
    public function test_public_website_logo_is_reused_automatically_without_cropping(): void {
        \Illuminate\Support\Facades\Storage::fake('public');
        $path='logos/customer_'.$this->customer->id.'/logo.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aLesAAAAASUVORK5CYII='));
        $logo=Logo::create(['customer_id'=>$this->customer->id,'name'=>'Website logo','url'=>$path,'status'=>1]);
        \App\Models\LogoLocation::create(['customer_id'=>$this->customer->id,'logo_id'=>$logo->id,'location'=>'header']);
        $service=app(CustomerDirectoryService::class);
        $service->save($this->admin,$this->customer,$this->data());
        $profile=CustomerDirectoryProfile::where('customer_id',$this->customer->id)->firstOrFail();
        $this->assertStringContainsString($path,$service->card($profile)['logo']);
        $logo->update(['status'=>0]);
        $this->assertNull($service->card($profile->fresh())['logo']);
    }
    public function test_cover_upload_and_brand_preferences_are_saved_and_invalid_uploads_rejected(): void {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file=\Illuminate\Http\UploadedFile::fake()->image('cover.jpg',800,400);
        $this->post($this->root.'/directory-profile',$this->data(['_method'=>'PUT','cover'=>$file,'brand_display'=>'logo']))->assertRedirect()->assertSessionHasNoErrors();
        $p=CustomerDirectoryProfile::where('customer_id',$this->customer->id)->firstOrFail();
        $this->assertSame('logo',$p->brand_display);$this->assertSame('uploaded',$p->cover_source);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($p->cover_path);
        $card=app(CustomerDirectoryService::class)->card($p);
        $this->assertNotNull($card['cover']);$this->assertNull($card['logo']);
        $this->postJson($this->root.'/directory-profile',$this->data(['_method'=>'PUT','revision'=>1,'brand_display'=>'invalid']))->assertUnprocessable();
        $this->postJson($this->root.'/directory-profile',$this->data(['_method'=>'PUT','revision'=>1,'cover'=>\Illuminate\Http\UploadedFile::fake()->create('bad.svg',1,'image/svg+xml')]))->assertUnprocessable();
        $this->post($this->root.'/directory-profile',$this->data(['_method'=>'PUT','revision'=>0,'cover'=>\Illuminate\Http\UploadedFile::fake()->image('stale.png')]))->assertSessionHasErrors('revision');
        $this->assertCount(1,\Illuminate\Support\Facades\Storage::disk('public')->allFiles('directory/customer_'.$this->customer->id));
        $this->assertSame($p->cover_path,$p->fresh()->cover_path);
    }
    public function test_location_search_and_nearest_order_use_only_consented_public_profiles(): void {
        $service=app(CustomerDirectoryService::class);
        $service->save($this->admin,$this->customer,$this->data(['publication_enabled'=>1,'consent'=>1,'city'=>'Quillota','latitude'=>-32.88,'longitude'=>-71.25]));
        $other=Customer::create(['name'=>'Distant center','legal_name'=>'Other','holder'=>'Holder','status'=>1,'domain'=>'far-'.Str::lower(Str::random(8)),'rut'=>Str::random(12),'email'=>'far@test.test','phone'=>'123']);
        WebsitePage::create(['customer_id'=>$other->id,'slug'=>'inicio','title'=>'Inicio','status'=>1]);
        CustomerDirectoryProfile::create(['customer_id'=>$other->id,'slug'=>'far-'.Str::random(8),'description'=>'Far center','publication_enabled'=>1,'authorized_at'=>now(),'authorized_by'=>$this->admin->id,'authorization_version'=>'directory-v1','latitude'=>-40,'longitude'=>-72]);
        \App\Models\VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['published'=>true]]);
        auth()->logout();$base='http://'.config('app.domain');
        $this->get($base.'/?buscar=Quillota')->assertOk()->assertSee('Directory clinic')->assertDontSee('Distant center');
        $this->postJson($base.'/directory-location',['latitude'=>-32.881234,'longitude'=>-71.251234])->assertOk()->assertSessionHas('directory_location.latitude',-32.881);
        $this->get($base.'/?cercanas=1')->assertOk()->assertSeeInOrder(['Directory clinic','Distant center'])->assertSee('km aproximadamente');
        $this->postJson($base.'/directory-location',['latitude'=>91,'longitude'=>0])->assertUnprocessable();
        $this->withSession(['directory_location'=>['latitude'=>0,'longitude'=>0,'expires_at'=>time()-1]])->get($base.'/?cercanas=1')->assertOk()->assertSee('permite el acceso a tu ubicación');
    }
}
