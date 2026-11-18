<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Logo;
use App\Models\LogoLocation;
use App\Models\User;
use App\Models\WebsiteNavigationButton;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicHeaderBrandTest extends TestCase
{
    public function test_clinic_can_choose_logo_name_or_both_without_changing_navigation_settings(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        $customer = Customer::where('status', 1)->first();
        if (!$user || !$customer) $this->markTestSkipped('Requires a superadministrator and an active clinic.');
        $root = 'http://' . $customer->domain . '.' . config('app.domain');
        DB::beginTransaction();
        try {
            Logo::where('customer_id', $customer->id)->update(['status' => 0]);
            $logo = Logo::create(['customer_id' => $customer->id, 'name' => 'Header test', 'url' => 'header-brand-test.png', 'status' => 1]);
            LogoLocation::create(['customer_id' => $customer->id, 'logo_id' => $logo->id, 'location' => 'header']);
            $settings = WebsiteNavigationButton::updateOrCreate(['customer_id' => $customer->id], ['enabled' => false, 'label' => 'Botón existente', 'destination' => 'contact', 'brand_display' => 'both']);
            $this->actingAs($user)->get($root . '/menus?_vita_frame=1&_vita_content=1')->assertOk()->assertSee('Logo y nombre en el menú público');
            $brand = function () use ($root) {
                $html = $this->get($root . '/?_vita_frame=1&_vita_content=1')->assertOk()->getContent();
                preg_match('/<a[^>]*class="navbar-brand[^>]*>(.*?)<\/a>/s', $html, $matches);
                $this->assertNotEmpty($matches);
                return $matches[1];
            };
            $this->assertStringContainsString('public-clinic-logo', $brand());
            $this->assertStringContainsString('public-clinic-name', $brand());
            $this->put($root . '/menus/brand', ['brand_display' => 'logo'])->assertRedirect($root . '/menus')->assertSessionHasNoErrors();
            $this->assertStringContainsString('public-clinic-logo', $brand());
            $this->assertStringNotContainsString('public-clinic-name', $brand());
            $this->put($root . '/menus/brand', ['brand_display' => 'title'])->assertSessionHasNoErrors();
            $this->assertStringContainsString('public-clinic-name', $brand());
            $this->assertStringNotContainsString('public-clinic-logo', $brand());
            $this->putJson($root . '/menus/brand', ['brand_display' => 'invalid'])->assertUnprocessable();
            $this->assertSame('title', $settings->fresh()->brand_display);
            $this->assertSame('Botón existente', $settings->fresh()->label);
            $this->assertFalse($settings->fresh()->enabled);
            $this->put($root . '/menus/brand', ['brand_display' => 'logo'])->assertSessionHasNoErrors();
            $logo->update(['status' => 0]);
            $this->assertStringContainsString('public-clinic-name', $brand());
        } finally {
            DB::rollBack();
        }
    }
}
