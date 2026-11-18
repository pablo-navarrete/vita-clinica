<?php
namespace Tests\Feature;

use App\Models\{BillingPlan, BillingPlanVersion, BillingFeature, BillingCatalogEvent, Role, User};
use App\Services\Billing\{PlanCatalogService, PlanVersionService, BillingCapabilities};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingCatalogTest extends TestCase
{
    private User $admin;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp(); DB::beginTransaction();
        $id = Str::random(15);
        $this->admin = User::create(['name'=>'Billing test','email'=>$id.'@example.test','password'=>'test-password','rut'=>$id,'phone'=>'123','status'=>1]);
        $role = Role::firstOrCreate(['customer_id'=>null,'name'=>'Super Administrador']);
        $this->admin->roles()->attach($role->id);
        $this->root = 'http://' . config('app.domain') . '/billing';
        $this->actingAs($this->admin);
    }
    protected function tearDown(): void { DB::rollBack(); parent::tearDown(); }
    public function test_plan_status_can_be_changed_without_altering_published_conditions(): void
    {
        $version=$this->version();
        app(PlanVersionService::class)->publish($this->admin,$version);
        $before=$version->fresh()->getAttributes();
        $plan=$version->plan;
        $url=$this->root.'/plans/'.$plan->id.'/status';
        $this->patchJson($url,['active'=>false])->assertOk()->assertJsonPath('message','Estado del plan actualizado.');
        $this->assertFalse((bool)$plan->fresh()->active);
        $this->assertSame($before,$version->fresh()->getAttributes());
        $this->assertSame(1,BillingCatalogEvent::where('billing_plan_id',$plan->id)->where('action','plan_status_changed')->count());
        $this->patchJson($url,['active'=>false])->assertOk();
        $this->assertSame(1,BillingCatalogEvent::where('billing_plan_id',$plan->id)->where('action','plan_status_changed')->count());
        $this->patchJson($url,['active'=>true])->assertOk();
        $this->assertTrue((bool)$plan->fresh()->active);
        $this->patchJson($url,['active'=>'invalid'])->assertUnprocessable();
        $this->admin->roles()->detach();
        $this->patchJson($url,['active'=>false])->assertForbidden();
        $this->assertTrue((bool)$plan->fresh()->active);
    }
    private function plan(): BillingPlan
    {
        return app(PlanCatalogService::class)->save($this->admin, ['code'=>'plan_'.Str::lower(Str::random(10)), 'name'=>'Flexible plan', 'description'=>'Description','active'=>1,'position'=>3]);
    }
    private function definition(): array
    {
        return ['included_branches'=>2,'trial_days'=>9,'grace_days'=>4,'terms'=>'Final CLP prices. Contractual test conditions.',
            'prices'=>['month'=>['enabled'=>1,'amount'=>12345,'additional_branch_amount'=>321], 'year'=>['enabled'=>1,'amount'=>98765,'additional_branch_amount'=>4567]],
            'limits'=>['users'=>['unlimited'=>0,'value'=>12,'scope'=>'customer'], 'professionals'=>['unlimited'=>1,'value'=>null,'scope'=>'branch'], 'branches'=>['unlimited'=>0,'value'=>5,'scope'=>'customer']],
            'features'=>['appointments','telemedicine'],
            'quotas'=>['ai_tokens'=>['enabled'=>1,'unlimited'=>0,'quantity'=>0,'reset_period'=>'month','overage_policy'=>'notify'],
                'storage_bytes'=>['enabled'=>1,'unlimited'=>1,'quantity'=>null,'reset_period'=>'none','overage_policy'=>'block']]];
    }
    private function version(): BillingPlanVersion
    {
        $version = app(PlanVersionService::class)->create($this->admin,$this->plan());
        app(PlanVersionService::class)->save($this->admin,$version,$this->definition());
        return $version->fresh();
    }
    public function test_full_http_catalog_workflow_and_templates(): void
    {
        $this->get($this->root.'/plans'.'?_vita_content=1')->assertOk()->assertSee('Crear plan');
        $this->get($this->root.'/plans/create'.'?_vita_content=1')->assertOk();
        $code = 'dynamic_'.Str::lower(Str::random(8));
        $this->post($this->root.'/plans',['code'=>$code,'name'=>'Custom product','description'=>'My plan','active'=>1,'position'=>8])->assertRedirect()->assertSessionHasNoErrors();
        $plan = BillingPlan::where('code',$code)->firstOrFail();
        $this->get($this->root.'/plans/'.$plan->id.'?_vita_content=1')->assertOk()->assertSee('Condiciones del plan')->assertDontSee('Nuevo borrador');
        $this->get($this->root.'/plans/'.$plan->id.'/edit'.'?_vita_content=1')->assertOk();
        $this->post($this->root.'/plans/'.$plan->id.'/versions')->assertRedirect();
        $v=$plan->versions()->firstOrFail();
        $this->get($this->root.'/versions/'.$v->id.'/edit'.'?_vita_content=1')->assertOk()->assertSee('Precios finales')->assertSee('Cuotas de consumo');
        $this->put($this->root.'/versions/'.$v->id,$this->definition())->assertRedirect()->assertSessionHasNoErrors();
        $this->get($this->root.'/versions/'.$v->id.'?_vita_content=1')->assertOk()->assertSee('12.345')->assertSee('98.765');
        $this->post($this->root.'/versions/'.$v->id.'/publish',['confirmation'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->get($this->root.'/versions/'.$v->id.'?_vita_content=1')->assertOk()->assertSee('Solo consulta');
        $this->get($this->root.'/versions/'.$v->id.'/edit'.'?_vita_content=1')->assertStatus(409);
        $this->putJson($this->root.'/versions/'.$v->id,$this->definition())->assertStatus(409);
        $this->postJson($this->root.'/versions/'.$v->id.'/publish',['confirmation'=>1])->assertStatus(409);
        $this->assertSame(2,$v->fresh()->prices()->count());
    }
    public function test_simple_edit_updates_conditions_and_retains_immutable_internal_history(): void
    {
        $code='simple_'.Str::lower(Str::random(8));
        $data=['code'=>$code,'name'=>'Simple plan','active'=>1,'position'=>1,'revision'=>0,'definition'=>$this->definition()];
        $this->postJson($this->root.'/plans',$data)->assertOk();
        $plan=BillingPlan::where('code',$code)->firstOrFail();
        $first=$plan->currentVersion; $this->assertSame('published',$first->status);
        $before=$first->getAttributes();
        $data['revision']=$first->number; $data['definition']['prices']['month']['amount']=76543;
        $this->putJson($this->root.'/plans/'.$plan->id,$data)->assertOk();
        $this->assertSame($before,$first->fresh()->getAttributes());
        $this->assertSame(2,$plan->versions()->where('status','published')->count());
        $this->assertSame(0,$plan->versions()->where('status','draft')->count());
        $this->assertSame(76543,(int)$plan->fresh()->currentVersion->prices()->where('interval','month')->value('amount'));
        $this->putJson($this->root.'/plans/'.$plan->id,$data)->assertUnprocessable()->assertJsonValidationErrors('plan');
        $response=$this->getJson($this->root.'/plans-data?search[value]='.$code.'&length=10')->assertOk();
        $this->assertSame(76543,(int)$response->json('data.0.commercial.definition.prices.month.amount'));
        $this->assertSame(2,$response->json('data.0.commercial.revision'));
        $html=$this->getJson($this->root.'/plans/'.$plan->id)->assertOk()->json('html');
        $this->assertStringNotContainsString('Nuevo borrador',$html);
        $this->assertStringNotContainsString('Copiar a nuevo borrador',$html);
        $this->assertStringContainsString('76.543',$html);
    }
    public function test_internal_preparation_reuses_one_working_version(): void
    {
        $plan=$this->plan(); $service=app(PlanVersionService::class);
        $a=$service->create($this->admin,$plan); $b=$service->create($this->admin,$plan);
        $this->assertSame($a->id,$b->id);
        $this->assertSame(1,$plan->versions()->where('status','draft')->count());
    }
    public function test_modal_endpoints_return_partials_and_save_validation_without_redirects(): void
    {
        $create=$this->getJson($this->root.'/plans/create')->assertOk()->assertJsonPath('title','Crear plan')->json('html');
        $this->assertStringContainsString('data-plan-form',$create);
        $this->assertStringNotContainsString('<!DOCTYPE',$create);
        $code='modal_'.Str::lower(Str::random(8));
        $this->postJson($this->root.'/plans',['name'=>'Modal plan','code'=>$code,'active'=>1,'position'=>1])->assertOk()->assertJsonStructure(['id','message']);
        $plan=BillingPlan::where('code',$code)->firstOrFail();
        $edit=$this->getJson($this->root.'/plans/'.$plan->id.'/edit')->assertOk()->assertJsonPath('title','Editar plan')->json('html');
        $this->assertStringContainsString('Modal plan',$edit);
        $this->assertStringNotContainsString('<!DOCTYPE',$edit);
        $this->putJson($this->root.'/plans/'.$plan->id,['code'=>$code,'name'=>'','active'=>1,'position'=>1])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson($this->root.'/plans/'.$plan->id,['code'=>$code,'name'=>'Updated modal','active'=>0,'position'=>2])->assertOk();
        $show=$this->getJson($this->root.'/plans/'.$plan->id)->assertOk()->assertJsonPath('title','Ver plan: Updated modal')->json('html');
        $this->assertStringContainsString('Condiciones del plan',$show);
        $this->assertStringNotContainsString('<!DOCTYPE',$show);
    }
    public function test_deleting_plan_hides_catalog_entry_but_preserves_published_history(): void
    {
        $version=$this->version(); app(PlanVersionService::class)->publish($this->admin,$version);
        $before=$version->fresh()->getAttributes(); $plan=$version->plan;
        $this->deleteJson($this->root.'/plans/'.$plan->id)->assertOk();
        $this->assertNull(BillingPlan::find($plan->id));
        $this->assertTrue(BillingPlan::withTrashed()->findOrFail($plan->id)->trashed());
        $this->assertSame($before,$version->fresh()->getAttributes());
        $this->assertSame($plan->id,$version->fresh()->plan->id);
        $this->getJson($this->root.'/plans-data?length=10&search[value]='.$plan->code)->assertOk()->assertJsonPath('recordsFiltered',0);
        $this->assertTrue(BillingCatalogEvent::where('billing_plan_id',$plan->id)->where('action','plan_deleted')->exists());
        $this->assertSame(2,$version->prices()->count());
    }
    public function test_datatable_endpoint_paginates_filters_and_orders_plans(): void
    {
        $plan=$this->plan();
        $other=$this->plan();
        $service=app(PlanVersionService::class); $service->create($this->admin,$plan);
        $request=['draw'=>4,'start'=>0,'length'=>1,'search'=>['value'=>$plan->code],
            'columns'=>[['data'=>'id','name'=>'id','searchable'=>'false','orderable'=>'true']],
            'order'=>[['column'=>0,'dir'=>'desc']]];
        $response=$this->getJson($this->root.'/plans-data?'.http_build_query($request))->assertOk()
            ->assertJsonPath('draw',4)->assertJsonPath('recordsFiltered',1)->assertJsonCount(1,'data')
            ->assertJsonPath('data.0.code',$plan->code)->assertJsonPath('data.0.versions_count',1);
        $this->assertStringContainsString('/billing/plans/'.$plan->id,$response->json('data.0.show_url'));
        $request['search']['value']='nonexistent_'.Str::random(10);
        $this->getJson($this->root.'/plans-data?'.http_build_query($request))->assertOk()->assertJsonPath('recordsFiltered',0)->assertJsonCount(0,'data');
        $this->getJson($this->root.'/plans-data?length=-1')->assertUnprocessable();
        $this->get($this->root.'/plans?_vita_content=1')->assertOk()->assertSee('id="billing-plans-table"',false);
    }
    public function test_legacy_global_admin_with_null_status_can_open_modules_but_disabled_admin_cannot(): void
    {
        $this->admin->update(['status'=>null]);
        $this->get($this->root.'/plans?_vita_content=1')->assertOk()->assertSee('Crear plan')
            ->assertSee('data-vita-content="true"', false);
        $plan=$this->plan();
        $version=app(PlanVersionService::class)->create($this->admin,$plan);
        $this->get($this->root.'/plans/'.$plan->id.'?_vita_content=1')->assertOk();
        $this->get($this->root.'/versions/'.$version->id.'/edit?_vita_content=1')->assertOk();
        $this->admin->update(['status'=>0]);
        $this->getJson($this->root.'/plans?_vita_content=1')->assertForbidden();
    }
    public function test_dynamic_plans_copy_history_and_no_clinical_side_effects(): void
    {
        $customers = DB::table('customers')->orderBy('id')->get()->toJson();
        $v=$this->version(); $service=app(PlanVersionService::class);
        $service->publish($this->admin,$v); $original=$v->fresh()->getAttributes();
        $copy=$service->create($this->admin,$v->plan,$v);
        $data=$this->definition(); $data['prices']['month']['amount']=77777; $data['trial_days']=0;
        $service->save($this->admin,$copy,$data); $service->publish($this->admin,$copy);
        $this->assertSame($original,$v->fresh()->getAttributes());
        $this->assertSame(12345,(int)$v->prices()->where('interval','month')->value('amount'));
        $this->assertSame(77777,(int)$copy->prices()->where('interval','month')->value('amount'));
        app(PlanCatalogService::class)->save($this->admin,['code'=>$v->plan->code,'name'=>'Renamed','description'=>'New presentation','active'=>0,'position'=>9],$v->plan);
        $this->assertFalse($v->plan->fresh()->active);
        $this->assertSame('Flexible plan',json_decode($v->fresh()->snapshot,true)['plan_name']);
        $this->assertSame($customers,DB::table('customers')->orderBy('id')->get()->toJson());
        foreach(range(1,5) as $i) $this->assertNotNull($this->plan());
        $this->assertTrue(BillingCatalogEvent::where('billing_plan_id',$v->billing_plan_id)->count() >= 7);
    }
    public function test_publication_rejects_incomplete_values_and_unknown_capabilities(): void
    {
        $v=app(PlanVersionService::class)->create($this->admin,$this->plan());
        $this->postJson($this->root.'/versions/'.$v->id.'/publish',['confirmation'=>1])->assertUnprocessable();
        $data=$this->definition(); $data['features'][]='imaginary_function';
        $this->putJson($this->root.'/versions/'.$v->id,$data)->assertUnprocessable();
        $data=$this->definition(); $data['prices']['month']['amount']=-1;
        $this->putJson($this->root.'/versions/'.$v->id,$data)->assertUnprocessable();
        $data=$this->definition(); $data['prices']['year']['amount']=1.5;
        $this->putJson($this->root.'/versions/'.$v->id,$data)->assertUnprocessable();
        $data=$this->definition(); $data['prices']['month']['enabled']=0; $data['prices']['year']['enabled']=0;
        $this->putJson($this->root.'/versions/'.$v->id,$data)->assertUnprocessable();
        $data=$this->definition(); $data['limits']['users']['value']=null;
        $this->putJson($this->root.'/versions/'.$v->id,$data)->assertUnprocessable();
        $data=$this->definition(); $data['quotas']['storage_bytes']['reset_period']='month';
        $this->putJson($this->root.'/versions/'.$v->id,$data)->assertUnprocessable();
        $this->assertSame('draft',$v->fresh()->status);
    }
    public function test_zero_and_unlimited_are_explicit_and_snapshot_integrity_is_stable(): void
    {
        $v=$this->version();
        $this->assertSame(0,(int)$v->quotas()->where('metric','ai_tokens')->value('quantity'));
        $this->assertNull($v->quotas()->where('metric','storage_bytes')->value('quantity'));
        $this->assertNull($v->limits()->where('code','professionals')->value('value'));
        app(PlanVersionService::class)->publish($this->admin,$v); $v->refresh();
        $this->assertSame(hash('sha256',$v->snapshot),$v->sha256);
        $this->assertSame(array_keys(BillingCapabilities::FEATURES),BillingFeature::orderBy('id')->pluck('code')->all());
    }
    public function test_published_terms_reject_model_and_raw_sql_mutations(): void
    {
        $v=$this->version(); app(PlanVersionService::class)->publish($this->admin,$v); $v->refresh();
        $copy=app(PlanVersionService::class)->create($this->admin,$v->plan);
        $actions=[fn()=> $v->update(['terms'=>'changed']), fn()=> $v->delete(),
            fn()=>DB::table('billing_plan_versions')->where('id',$v->id)->update(['status'=>'draft']),
            fn()=>DB::table('billing_plan_versions')->where('id',$v->id)->delete()];
        foreach(['prices','limits','features','quotas'] as $relation) {
            $row=$v->$relation()->firstOrFail(); $table=$row->getTable();
            $actions[]=fn()=> $row->delete();
            $field = ['prices'=>'amount','limits'=>'value','features'=>'billing_feature_id','quotas'=>'quantity'][$relation];
            $replacement = $relation === 'features' ? BillingFeature::where('code','viti')->value('id') : 999;
            $actions[]=fn()=> $row->update([$field=>$replacement]);
            $actions[]=fn()=> DB::table($table)->where('id',$row->id)->update([$field=>$replacement]);
            $actions[]=fn()=> DB::table($table)->where('id',$row->id)->update(['billing_plan_version_id'=>$copy->id]);
            $actions[]=fn()=> DB::table($table)->where('id',$row->id)->delete();
            $values=collect($row->getAttributes())->except('id')->all();
            if($relation==='prices')$values['interval']='invalid';
            if($relation==='limits')$values['code']='new_limit';
            if($relation==='quotas')$values['metric']='new_metric';
            if($relation==='features')$values['billing_feature_id']=BillingFeature::where('code','viti')->value('id');
            $actions[]=fn()=> DB::table($table)->insert($values);
        }
        foreach($actions as $action) { try { $action(); $this->fail('Immutable mutation succeeded'); } catch(\LogicException|\Illuminate\Database\QueryException $e) { $this->assertNotEmpty($e->getMessage()); } }
        $this->assertSame('published',$v->fresh()->status);
        $this->assertSame(hash('sha256',$v->fresh()->snapshot),$v->fresh()->sha256);
    }
    public function test_plan_changes_are_audited_and_failed_draft_save_rolls_back_all_relations(): void
    {
        $v=$this->version(); $before=app(PlanVersionService::class)->definition($v);
        $data=$this->definition(); $data['prices']['month']['amount']=23456;
        BillingCatalogEvent::creating(fn()=>throw new \RuntimeException('Simulated audit failure'));
        try { app(PlanVersionService::class)->save($this->admin,$v,$data); $this->fail('Must roll back'); }
        catch(\RuntimeException $e) { $this->assertSame('Simulated audit failure',$e->getMessage()); }
        finally { BillingCatalogEvent::flushEventListeners(); }
        $this->assertSame($before,app(PlanVersionService::class)->definition($v->fresh()));
        $this->put($this->root.'/plans/'.$v->plan->id,['code'=>$v->plan->code,'name'=>'Edited','active'=>0,'position'=>6])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($v->plan->fresh()->active);
        $event=BillingCatalogEvent::where('billing_plan_id',$v->billing_plan_id)->orderByDesc('id')->firstOrFail();
        try { DB::table('billing_catalog_events')->where('id',$event->id)->delete(); $this->fail('Must retain audit'); }
        catch(\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('append only',$e->getMessage()); }
    }
    public function test_non_superadministrator_cannot_access_or_use_services(): void
    {
        $v=$this->version();
        $other=User::create(['name'=>'No admin','email'=>Str::random(12).'@example.test','password'=>'password','rut'=>Str::random(12),'phone'=>'123','status'=>1]);
        $this->actingAs($other)->getJson($this->root.'/plans')->assertForbidden();
        $this->actingAs($other)->putJson($this->root.'/versions/'.$v->id,$this->definition())->assertForbidden();
        $this->actingAs($other)->postJson($this->root.'/versions/'.$v->id.'/publish',['confirmation'=>1])->assertForbidden();
        try { app(PlanVersionService::class)->publish($other,$v); $this->fail('Must deny'); }
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
    }
}
