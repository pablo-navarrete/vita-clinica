<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitaWebsiteSetting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VitaTopbarTest extends TestCase
{
    public function test_global_topbar_matches_clinic_options_and_saves_independently(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => array_replace(config('vita_website'), [
                'social_instagram' => 'https://instagram.com/vita-topbar-test',
                'footer_settings' => array_replace(VitaWebsiteSetting::footer(config('vita_website')), ['show_social_links' => false, 'email' => 'footer@example.test']),
            ])]);
            $this->actingAs($user)->get($root . '/website/topbar' . '?_vita_content=1')->assertOk()->assertSee('Contenido de la franja superior')->assertSee('Mostrar redes sociales configuradas')->assertSee('footer@example.test')->assertDontSee('correos automáticos');
            $data = ['enabled' => 1, 'show_social_links' => 1, 'phone' => '+56 9 8765 4321', 'email' => 'superior@example.test'];
            $this->put($root . '/website/topbar', $data)->assertRedirect($root . '/website/topbar')->assertSessionHasNoErrors();
            $this->get($root . '/')->assertOk()->assertSee('class="vita-public-topbar"', false)->assertSee('mailto:superior@example.test', false)->assertSee('tel:+56987654321', false)->assertSee('https://instagram.com/vita-topbar-test');
            $this->assertSame('footer@example.test', VitaWebsiteSetting::footer()['email']);
            $this->assertSame(config('vita_website.contact_email'), VitaWebsiteSetting::content()['contact_email']);
            $this->put($root . '/website/topbar', array_replace($data, ['show_social_links' => 0]))->assertSessionHasNoErrors();
            $this->get($root . '/')->assertOk()->assertSee('superior@example.test')->assertDontSee('https://instagram.com/vita-topbar-test');
            $this->putJson($root . '/website/topbar', array_replace($data, ['email' => 'invalid']))->assertUnprocessable();
            $this->putJson($root . '/website/topbar', array_replace($data, ['enabled' => 'invalid']))->assertUnprocessable();
            $this->put($root . '/website/topbar', array_replace($data, ['enabled' => 0]))->assertSessionHasNoErrors();
            $this->get($root . '/')->assertOk()->assertDontSee('class="vita-public-topbar"', false)->assertDontSee('superior@example.test')->assertSee('footer@example.test');
            $this->put($root . '/website/topbar', ['enabled' => 1, 'show_social_links' => 0, 'phone' => '', 'email' => ''])->assertSessionHasNoErrors();
            $this->assertNull(VitaWebsiteSetting::topbar()['email']);
            $this->get($root . '/')->assertOk();
        } finally {
            DB::rollBack();
        }
    }
}
