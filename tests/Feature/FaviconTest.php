<?php

namespace Tests\Feature;

use App\Models\{Customer, Logo, LogoLocation, VitaWebsiteSetting};
use App\Services\FaviconService;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); DB::beginTransaction(); Storage::fake('public');
        $this->app->forgetInstance('customer');
        VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['logos'=>[],'logo_path'=>null]]);
    }
    protected function tearDown(): void { DB::rollBack(); parent::tearDown(); }
    private function customer(): Customer
    {
        $key=Str::lower(Str::random(12));
        return Customer::create(['name'=>'Favicon fixture','legal_name'=>'Fixture','holder'=>'Fixture',
            'domain'=>'icon-'.$key,'rut'=>$key,'email'=>$key.'@example.test','phone'=>'123','status'=>1]);
    }
    private function image(string $path): void
    {
        Storage::disk('public')->put($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jr1sAAAAASUVORK5CYII='));
    }
    public function test_clinic_uses_its_active_panel_logo_and_html_includes_one_favicon(): void
    {
        $clinic=$this->customer();$path='logos/customer_'.$clinic->id.'/brand.png';$this->image($path);
        $logo=Logo::create(['customer_id'=>$clinic->id,'name'=>'Brand','status'=>1,'url'=>$path]);
        LogoLocation::create(['customer_id'=>$clinic->id,'logo_id'=>$logo->id,'location'=>'panel']);
        $this->app->instance('customer',$clinic);
        $icon=app(FaviconService::class)->current();
        $this->assertSame('image/png',$icon['type']);$this->assertStringContainsString('/storage/'.$path.'?v=',$icon['url']);
        $html=view('components.favicon')->render();
        $this->assertSame(1,substr_count($html,'rel="icon"'));
        $this->assertStringContainsString($path,$html);
    }
    public function test_missing_or_inactive_clinic_logo_falls_back_to_vita_not_another_clinic(): void
    {
        $clinic=$this->customer();$other=$this->customer();
        $foreign='logos/customer_'.$other->id.'/foreign.png';$this->image($foreign);
        Logo::create(['customer_id'=>$other->id,'name'=>'Foreign','status'=>1,'url'=>$foreign]);
        Logo::create(['customer_id'=>$clinic->id,'name'=>'Wrong path','status'=>1,'url'=>$foreign]);
        $inactive='logos/customer_'.$clinic->id.'/inactive.png';$this->image($inactive);
        Logo::create(['customer_id'=>$clinic->id,'name'=>'Inactive','status'=>0,'url'=>$inactive]);
        $this->image('vita/website/logos/vita.png');
        VitaWebsiteSetting::updateOrCreate(['id'=>1],['content'=>['logos'=>[['id'=>'vita','status'=>1,'url'=>'vita/website/logos/vita.png','locations'=>['header']]]]]);
        $this->app->instance('customer',$clinic);
        $this->assertStringContainsString('/storage/vita/website/logos/vita.png?',app(FaviconService::class)->current()['url']);
    }
    public function test_main_domain_and_portal_use_vita_and_default_icon_exists_without_uploaded_logos(): void
    {
        $icon=app(FaviconService::class)->current();
        $this->assertSame('image/svg+xml',$icon['type']);
        $this->assertStringContainsString('/favicon-vita.svg?v=',$icon['url']);
        $this->image('admin/logos/logo-vita-3.png');
        $this->assertStringContainsString('/storage/admin/logos/logo-vita-3.png?',app(FaviconService::class)->current()['url']);
    }
}
