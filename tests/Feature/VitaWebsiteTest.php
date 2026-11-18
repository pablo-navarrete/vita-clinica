<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitaWebsiteSetting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VitaWebsiteTest extends TestCase
{
    public function test_separate_sections_save_independently_and_render_on_the_public_site(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) {
                $user = User::factory()->create(['customer_id' => null, 'status' => 1]);
                $role = \App\Models\Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
                $user->roles()->attach($role->id);
            }
        $root = 'http://' . config('app.domain');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $this->actingAs($user);
            foreach (config('vita_website_sections') as $section => $definition) {
                $title = match ($section) { 'menus' => 'Menú del sitio web', 'socials' => 'Enlaces de redes sociales', default => $definition['title'] };
                $this->get($root . '/website/' . $section . '?_vita_content=1')->assertOk()->assertSee($title);
            }
            $this->put($root . '/website/logo', ['brand' => 'VITA Prueba', 'tagline' => 'Equipo de salud'])->assertSessionHasNoErrors();
            $this->assertSame(config('vita_website.hero_title'), VitaWebsiteSetting::content()['hero_title']);
            $this->put($root . '/website/topbar', ['enabled' => 1, 'show_social_links' => 0, 'phone' => '+56 9 8765 4321', 'email' => 'contacto@example.test'])->assertSessionHasNoErrors();
            $this->put($root . '/website/socials', ['social_links' => [['network' => 'instagram', 'url' => 'javascript:alert(1)']]])->assertSessionHasErrors('social_links.0.url');
            $this->put($root . '/website/topbar', ['enabled' => 1, 'show_social_links' => 0, 'email' => 'invalid'])->assertSessionHasErrors('email');
            $this->get($root . '/website/blog' . '?_vita_content=1')->assertNotFound();
            $this->put($root . '/website/blog', [])->assertNotFound();
            $this->get($root . '/vita')->assertOk()->assertSee('VITA Prueba')->assertSee('contacto@example.test')->assertDontSee('id="blog"', false);
            $this->get($root . '/blog/articulo-publico')->assertNotFound();
            $this->assertSame('VITA Prueba', VitaWebsiteSetting::content()['brand']);
            $this->get($root . '/website/nonexistent' . '?_vita_content=1')->assertNotFound();
        } finally {
            DB::rollBack();
        }
    }

    public function test_main_site_and_editor_are_separate_and_guarded(): void
    {
        $root = 'http://' . config('app.domain');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $this->get($root . '/vita')->assertOk()->assertSee('Más tiempo para cuidar.')->assertSee('Iniciar sesión');
            $this->get($root . '/website' . '?_vita_content=1')->assertRedirect();
            $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
            if (!$user) {
                $user = User::factory()->create(['customer_id' => null, 'status' => 1]);
                $role = \App\Models\Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
                $user->roles()->attach($role->id);
            }
            $this->actingAs($user)->get($root . '/website' . '?_vita_content=1')->assertOk()->assertSee('Web de VITA')->assertSee('Guardar cambios');
            $data = config('vita_website');
            $data['hero_title'] = 'Contenido de prueba <script>alert(1)</script>';
            unset($data['logo_path']);
            $this->put($root . '/website', $data)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($data['hero_title'], VitaWebsiteSetting::content()['hero_title']);
            $this->get($root . '/vita')->assertOk()->assertSee($data['hero_title'])->assertDontSee('<script>alert(1)</script>', false);
            $data['published'] = false;
            $this->put($root . '/website', $data)->assertSessionHasNoErrors();
            $this->get($root . '/vita')->assertStatus(503)->assertSee('Iniciar sesión');
            $this->get($root . '/website/preview' . '?_vita_content=1')->assertOk()->assertSee('Vista previa');
            $data['contact_email'] = 'invalid-email';
            $this->put($root . '/website', $data)->assertSessionHasErrors('contact_email');
            $clinicUser = User::whereNotNull('customer_id')->where('status', 1)->first();
            if ($clinicUser) $this->actingAs($clinicUser)->get($root . '/website' . '?_vita_content=1')->assertForbidden();
        } finally {
            DB::rollBack();
        }
    }
}
