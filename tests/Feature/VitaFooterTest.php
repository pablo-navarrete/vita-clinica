<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitaWebsiteSetting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VitaFooterTest extends TestCase
{
    public function test_global_footer_has_clinic_options_and_controls_public_rendering(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => array_replace(config('vita_website'), [
                'social_instagram' => 'https://instagram.com/vita-footer-test',
                'logos' => [['id' => 'footer-test', 'name' => 'Footer', 'url' => 'vita/website/logos/footer-test.png', 'status' => 1, 'locations' => ['footer']]],
            ])]);
            $this->actingAs($user)->get($root . '/website/footer' . '?_vita_content=1')->assertOk()->assertSee('Contenido del footer')->assertSee('Mostrar redes sociales')->assertSee('Solo logo')->assertSee('Guardar footer');
            $data = ['enabled' => 1, 'show_social_links' => 1, 'brand_display' => 'both', 'title' => 'Marca del footer', 'description' => 'Descripción única <script>alert(1)</script>', 'phone' => '+56 9 8765 4321', 'email' => 'contacto@example.test', 'address' => 'Dirección del footer'];
            $this->put($root . '/website/footer', $data)->assertRedirect($root . '/website/footer')->assertSessionHasNoErrors();
            $this->get($root . '/')->assertOk()->assertSee('Marca del footer')->assertSee('Dirección del footer')->assertSee('mailto:contacto@example.test', false)->assertSee('https://instagram.com/vita-footer-test')->assertSee('vita/website/logos/footer-test.png')->assertDontSee('<script>alert(1)</script>', false);
            $this->assertSame(config('vita_website.contact_email'), VitaWebsiteSetting::content()['contact_email']);
            $this->assertSame(config('vita_website.hero_title'), VitaWebsiteSetting::content()['hero_title']);
            $this->put($root . '/website/footer', array_replace($data, ['brand_display' => 'title', 'show_social_links' => 0]))->assertSessionHasNoErrors();
            $this->get($root . '/')->assertOk()->assertSee('Marca del footer')->assertDontSee('vita/website/logos/footer-test.png')->assertDontSee('https://instagram.com/vita-footer-test');
            $this->putJson($root . '/website/footer', array_replace($data, ['email' => 'invalid']))->assertUnprocessable();
            $this->putJson($root . '/website/footer', array_replace($data, ['brand_display' => 'invalid']))->assertUnprocessable();
            $this->put($root . '/website/footer', array_replace($data, ['enabled' => 0]))->assertSessionHasNoErrors();
            $this->get($root . '/')->assertOk()->assertDontSee('class="vita-site-footer"', false)->assertDontSee('Marca del footer')->assertSee('id="contacto"', false);
            $this->put($root . '/website/footer', array_replace($data, ['title' => '', 'brand_display' => 'logo']))->assertSessionHasNoErrors();
            $this->get($root . '/')->assertOk()->assertSee('vita/website/logos/footer-test.png');
        } finally {
            DB::rollBack();
        }
    }
}
