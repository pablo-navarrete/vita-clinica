<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VitaMenusTest extends TestCase
{
    public function test_global_header_can_display_logo_name_or_both(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => array_replace(config('vita_website'), [
                'logos' => [['id' => 'header-brand', 'name' => 'Header', 'url' => 'vita/website/logos/header-brand.png', 'status' => 1, 'locations' => ['header']]],
            ])]);
            $this->actingAs($user)->get($root . '/website/menus' . '?_vita_content=1')->assertOk()->assertSee('Guardar presentación');
            $header = function () use ($root) {
                $html = $this->get($root . '/')->assertOk()->getContent();
                preg_match('/<a[^>]*class="brand"[^>]*>(.*?)<\/a>/s', $html, $match);
                $this->assertNotEmpty($match);
                return $match[1];
            };
            foreach (['both', 'logo', 'title'] as $mode) {
                $this->put($root . '/website/menus/brand', ['brand_display' => $mode])->assertRedirect($root . '/website/menus')->assertSessionHasNoErrors();
                $html = $header();
                $this->assertSame($mode !== 'title', str_contains($html, '<img'));
                $this->assertSame($mode !== 'logo', str_contains($html, 'brand-mark'));
            }
            $this->putJson($root . '/website/menus/brand', ['brand_display' => 'invalid'])->assertUnprocessable();
            $this->assertSame('title', VitaWebsiteSetting::content()['header_brand_display']);
            $this->assertSame(config('vita_website.brand'), VitaWebsiteSetting::content()['brand']);
            $this->put($root . '/website/menus/brand', ['brand_display' => 'logo'])->assertSessionHasNoErrors();
            $setting = VitaWebsiteSetting::findOrFail(1);
            $content = $setting->content;
            $content['logos'] = [];
            $setting->update(['content' => $content]);
            $this->assertStringContainsString('brand-mark', $header());
        } finally {
            DB::rollBack();
        }
    }

    public function test_global_menus_create_pages_order_toggle_and_render_independently(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        Storage::fake('public');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $this->actingAs($user)->get($root . '/website/menus' . '?_vita_content=1')->assertOk()->assertSee('Crear página en blanco')->assertSee('Boton junto al menu publico')->assertDontSee('Agendamiento publico');
            $this->get($root . '/website/menus/1/edit' . '?_vita_content=1')->assertOk();
            $this->get($root . '/website/pages/1/edit' . '?_vita_content=1')->assertOk();
            $this->post($root . '/website/menus', ['name' => 'Nuestro equipo', 'status' => 1])->assertRedirect();
            $page = collect(VitaWebsiteSetting::menus())->firstWhere('slug', 'nuestro-equipo');
            $id = $page['id'];
            $this->get($root . '/website/pages/' . $id . '/edit' . '?_vita_content=1')->assertOk()->assertSee('Nuestro equipo');
            $sectionId = (string) Str::uuid();
            $this->put($root . '/website/pages/' . $id, ['title' => 'Equipo de VITA', 'sections' => [['id' => $sectionId, 'title' => 'Personas que acompañan', 'body' => 'Un equipo comprometido. <script>alert(1)</script>', 'image' => UploadedFile::fake()->image('equipo.png'), 'image_alt' => 'Nuestro equipo']]])->assertRedirect();
            $page = collect(VitaWebsiteSetting::menus())->firstWhere('id', $id);
            $image = $page['sections'][0]['image_path'];
            Storage::disk('public')->assertExists($image);
            $this->get($root . $page['url'])->assertOk()->assertSee('Equipo de VITA')->assertSee('Personas que acompañan')->assertDontSee('<script>alert(1)</script>', false);
            $this->get($root . '/')->assertOk()->assertSee($page['url']);
            $button = ['enabled' => 1, 'label' => 'Conoce al equipo', 'destination' => 'page', 'menu_id' => $id];
            $this->putJson($root . '/website/menus/navigation-button', $button)->assertRedirect();
            $this->get($root . '/')->assertOk()->assertSee('Conoce al equipo');
            $this->patchJson($root . '/website/menus/' . $id . '/toggle-status')->assertOk()->assertJsonPath('status', false);
            $this->get($root . $page['url'])->assertNotFound();
            $this->get($root . '/')->assertOk()->assertDontSee('Conoce al equipo')->assertDontSee('Equipo de VITA');
            $this->putJson($root . '/website/menus/navigation-button', $button)->assertUnprocessable();
            $this->patchJson($root . '/website/menus/' . $id . '/toggle-status')->assertOk();
            $ids = array_reverse(array_column(VitaWebsiteSetting::menus(), 'id'));
            $this->patchJson($root . '/website/menus/reorder', ['items' => $ids])->assertOk();
            $this->assertSame(1, VitaWebsiteSetting::menus()[0]['id']);
            $this->assertSame($id, VitaWebsiteSetting::menus()[1]['id']);
            $this->patchJson($root . '/website/menus/reorder', ['items' => [1]])->assertUnprocessable();
            $this->patchJson($root . '/website/menus/1/toggle-status')->assertUnprocessable();
            $this->deleteJson($root . '/website/menus/1')->assertUnprocessable();
            $this->putJson($root . '/website/menus/2', ['name' => 'Enlace', 'status' => 1, 'url' => 'javascript:alert(1)'])->assertUnprocessable();
            $this->putJson($root . '/website/menus/navigation-button', ['enabled' => 1, 'label' => 'Enlace', 'destination' => 'custom', 'custom_url' => '//evil.test'])->assertUnprocessable();
            $this->putJson($root . '/website/menus/navigation-button', ['enabled' => 1, 'label' => 'WhatsApp', 'destination' => 'whatsapp', 'whatsapp_phone' => '+56 9 1234 5678'])->assertRedirect();
            $this->get($root . '/')->assertOk()->assertSee('https://wa.me/56912345678');
            $this->delete($root . '/website/menus/' . $id)->assertRedirect();
            Storage::disk('public')->assertMissing($image);
            $this->get($root . $page['url'])->assertNotFound();
            $this->assertSame(config('vita_website.brand'), VitaWebsiteSetting::content()['brand']);
            $this->post($root . '/website/menus', ['name' => 'Nuestro equipo', 'status' => 0])->assertRedirect();
            $this->assertGreaterThan($id, collect(VitaWebsiteSetting::menus())->firstWhere('slug', 'nuestro-equipo')['id']);
        } finally {
            DB::rollBack();
        }
    }
}
