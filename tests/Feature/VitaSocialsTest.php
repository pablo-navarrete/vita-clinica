<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitaWebsiteSetting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VitaSocialsTest extends TestCase
{
    public function test_social_links_support_clinic_networks_and_both_public_locations(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => array_replace(config('vita_website'), [
                'social_instagram' => 'https://instagram.com/legacy-vita-test',
                'topbar_settings' => ['enabled' => true, 'show_social_links' => true, 'phone' => null, 'email' => null],
            ])]);
            $this->actingAs($user)->get($root . '/website/socials' . '?_vita_content=1')->assertOk()->assertSee('Enlaces de redes sociales')->assertSee('Agregar red')->assertSee('https://instagram.com/legacy-vita-test')->assertSee('TikTok')->assertSee('WhatsApp');
            $links = [['network' => 'x', 'url' => 'https://x.com/vita-social-test'], ['network' => 'tiktok', 'url' => 'https://www.tiktok.com/@vita-social-test']];
            $this->put($root . '/website/socials', ['social_links' => $links])->assertRedirect($root . '/website/socials')->assertSessionHasNoErrors();
            $this->assertEquals($links, VitaWebsiteSetting::socialLinks());
            $page = $this->get($root . '/')->assertOk()->assertSee($links[0]['url'])->assertSee($links[1]['url'])->assertDontSee('https://instagram.com/legacy-vita-test');
            $this->assertSame(2, substr_count($page->getContent(), 'href="' . $links[0]['url'] . '"'));
            $this->putJson($root . '/website/socials', ['social_links' => [$links[0], $links[0]]])->assertUnprocessable();
            $this->putJson($root . '/website/socials', ['social_links' => [['network' => 'unknown', 'url' => 'https://example.test']]])->assertUnprocessable();
            $this->putJson($root . '/website/socials', ['social_links' => [['network' => 'facebook', 'url' => 'javascript:alert(1)']]])->assertUnprocessable();
            $this->assertEquals($links, VitaWebsiteSetting::socialLinks());
            $this->put($root . '/website/socials', [])->assertSessionHasNoErrors();
            $this->assertSame([], VitaWebsiteSetting::socialLinks());
            $this->get($root . '/')->assertOk()->assertDontSee($links[0]['url'])->assertDontSee('https://instagram.com/legacy-vita-test');
            $this->assertTrue(VitaWebsiteSetting::topbar()['show_social_links']);
            $this->assertSame(config('vita_website.brand'), VitaWebsiteSetting::content()['brand']);
        } finally {
            DB::rollBack();
        }
    }
}

