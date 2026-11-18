<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VitaBannersTest extends TestCase
{
    public function test_clinic_banner_overlay_can_be_disabled_and_enabled(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        $customer = \App\Models\Customer::where('status', 1)->first();
        if (!$user || !$customer) $this->markTestSkipped('Requires a superadministrator and an active clinic.');
        $root = 'http://' . $customer->domain . '.' . config('app.domain');
        DB::beginTransaction();
        try {
            $banner = \App\Models\WebsiteBanner::create(['customer_id' => $customer->id, 'title' => 'Filtro de prueba', 'image_path' => 'overlay-test.png', 'position' => 1, 'status' => true]);
            $this->actingAs($user)->get($root . '/banners/' . $banner->id . '/edit?_vita_content=1')->assertOk()->assertSee('Imagen normal (sin filtro)');
            $data = ['title' => 'Filtro de prueba', 'status' => 1, 'dark_overlay' => 0];
            $this->put($root . '/banners/' . $banner->id, $data)->assertSessionHasNoErrors();
            $this->assertFalse($banner->fresh()->dark_overlay);
            $html = view('customer.web.banners', ['publicBanners' => collect([$banner->fresh()])])->render();
            $this->assertStringNotContainsString('data-banner-dark-overlay', $html);
            $this->put($root . '/banners/' . $banner->id, array_replace($data, ['dark_overlay' => 1]))->assertSessionHasNoErrors();
            $this->assertTrue($banner->fresh()->dark_overlay);
            $html = view('customer.web.banners', ['publicBanners' => collect([$banner->fresh()])])->render();
            $this->assertStringContainsString('data-banner-dark-overlay', $html);
            $this->put($root . '/banners/' . $banner->id, array_replace($data, ['text_alignment' => 'left', 'button_alignment' => 'right', 'button_text' => 'Contacto', 'button_destination' => '#contacto']))->assertSessionHasNoErrors();
            $this->assertSame('left', $banner->fresh()->text_alignment);
            $this->assertSame('right', $banner->fresh()->button_alignment);
            $html = view('customer.web.banners', ['publicBanners' => collect([$banner->fresh()])])->render();
            $this->assertStringContainsString('class="banner-text" data-align="left"', $html);
            $this->assertStringContainsString('class="banner-button" data-align="right"', $html);
            $this->putJson($root . '/banners/' . $banner->id, array_replace($data, ['text_alignment' => 'invalid']))->assertUnprocessable();
            $this->put($root . '/banners/' . $banner->id, array_replace($data, ['image_only' => 1, 'title' => '']))->assertSessionHasNoErrors();
            $this->assertTrue($banner->fresh()->image_only);
            $html = view('customer.web.banners', ['publicBanners' => collect([$banner->fresh()])])->render();
            $this->assertStringNotContainsString('<div class="carousel-caption', $html);
            $this->assertStringContainsString('overlay-test.png', $html);
            $this->putJson($root . '/banners/' . $banner->id, array_replace($data, ['image_only' => 0, 'title' => '']))->assertUnprocessable();
            $this->putJson($root . '/banners/' . $banner->id, array_replace($data, ['dark_overlay' => 'invalid']))->assertUnprocessable();
        } finally {
            DB::rollBack();
        }
    }

    public function test_global_banners_support_crud_order_status_limits_and_safe_links(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        Storage::fake('public');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => array_replace(config('vita_website'), ['banners' => []])]);
            $this->actingAs($user)->get($root . '/website/banners' . '?_vita_content=1')->assertOk()->assertSee('Agregar banner')->assertSee('5 banners');
            $make = fn ($title, $status = 1, $url = '#contacto') => $this->postJson($root . '/website/banners', ['title' => $title, 'status' => $status, 'button_text' => 'Conocer más', 'button_destination' => $url, 'image' => UploadedFile::fake()->image('banner.png')]);
            $make('Banner principal')->assertRedirect();
            $first = VitaWebsiteSetting::banners()[0];
            Storage::disk('public')->assertExists($first['image_path']);
            $this->assertTrue($first['dark_overlay']);
            $this->putJson($root . '/website/banners/' . $first['id'], ['title' => 'Banner principal', 'status' => 1, 'dark_overlay' => 0])->assertRedirect();
            $this->assertFalse(VitaWebsiteSetting::banners()[0]['dark_overlay']);
            $this->get($root . '/')->assertOk()->assertSee('--banner-overlay:transparent', false);
            $this->putJson($root . '/website/banners/' . $first['id'], ['title' => 'Banner principal', 'status' => 1, 'dark_overlay' => 1])->assertRedirect();
            $this->get($root . '/')->assertOk()->assertDontSee('--banner-overlay:transparent', false);
            $this->putJson($root . '/website/banners/' . $first['id'], ['title' => '', 'status' => 1, 'image_only' => 1])->assertRedirect();
            $this->assertTrue(VitaWebsiteSetting::banners()[0]['image_only']);
            $this->get($root . '/')->assertOk()->assertDontSee('<div class="vita-banner-copy">', false)->assertSee($first['image_path']);
            $this->putJson($root . '/website/banners/' . $first['id'], ['title' => 'Banner principal', 'status' => 1, 'image_only' => 0])->assertRedirect();
            $this->putJson($root . '/website/banners/' . $first['id'], ['title' => 'Banner principal', 'status' => 1, 'text_alignment' => 'right', 'button_alignment' => 'left', 'button_text' => 'Contacto', 'button_destination' => '#contacto'])->assertRedirect();
            $this->get($root . '/')->assertOk()->assertSee('class="vita-banner-text" data-align="right"', false)->assertSee('class="vita-banner-button" data-align="left"', false);
            $this->assertSame('right', VitaWebsiteSetting::banners()[0]['text_alignment']);
            $this->putJson($root . '/website/banners/' . $first['id'], ['title' => 'Banner principal', 'status' => 1, 'button_alignment' => 'invalid'])->assertUnprocessable();
            $this->get($root . '/website/banners/' . $first['id'] . '/edit' . '?_vita_content=1')->assertOk()->assertSee('Banner principal');
            $make('Banner alternativo', 0)->assertRedirect();
            $second = VitaWebsiteSetting::banners()[1];
            $this->get($root . '/')->assertOk()->assertSee('vita-banner-carousel')->assertSee('Banner principal')->assertDontSee('Banner alternativo');
            $this->patchJson($root . '/website/banners/' . $second['id'] . '/toggle-status')->assertOk()->assertJsonPath('status', true);
            $this->patchJson($root . '/website/banners/reorder', ['items' => [$second['id'], $first['id']]])->assertOk();
            $this->assertSame($second['id'], VitaWebsiteSetting::banners()[0]['id']);
            $this->get($root . '/')->assertOk()->assertSeeInOrder(['Banner alternativo', 'Banner principal']);
            $this->patchJson($root . '/website/banners/reorder', ['items' => [$first['id']]])->assertUnprocessable();
            $this->putJson($root . '/website/banners/' . $first['id'], ['title' => 'Banner actualizado', 'status' => 1, 'button_destination' => '', 'button_text' => ''])->assertRedirect();
            $this->assertSame($first['image_path'], collect(VitaWebsiteSetting::banners())->firstWhere('id', $first['id'])['image_path']);
            $make('Enlace inseguro', 1, 'javascript:alert(1)')->assertUnprocessable();
            $make('Enlace externo relativo', 1, '//another-host.test')->assertUnprocessable();
            $this->assertCount(2, VitaWebsiteSetting::banners());
            $make('Tercero')->assertRedirect(); $make('Cuarto')->assertRedirect(); $make('Quinto')->assertRedirect();
            $make('Sexto')->assertUnprocessable();
            $this->assertCount(5, VitaWebsiteSetting::banners());
            $this->delete($root . '/website/banners/' . $first['id'])->assertRedirect();
            Storage::disk('public')->assertMissing($first['image_path']);
            $this->assertCount(4, VitaWebsiteSetting::banners());
            $this->get($root . '/website/banners/99999/edit' . '?_vita_content=1')->assertNotFound();
            $this->assertSame(config('vita_website.brand'), VitaWebsiteSetting::content()['brand']);
        } finally {
            DB::rollBack();
        }
    }
}
