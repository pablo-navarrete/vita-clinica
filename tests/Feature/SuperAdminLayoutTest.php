<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class SuperAdminLayoutTest extends TestCase
{
    public function test_modules_and_form_redirects_keep_the_persistent_layout_separate(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $this->actingAs($user)->get($root . '/superadmin/customers')->assertOk()
                ->assertSee('id="superadmin-sidebar"', false)->assertSee('id="vita-module-slot"', false)->assertDontSee('id="customer-table"', false);
            $this->get($root . '/superadmin/customers?_vita_content=1')->assertOk()
                ->assertSee('data-vita-content="true"', false)->assertSee('id="customer-table"', false)
                ->assertDontSee('id="superadmin-sidebar"', false)->assertDontSee('id="vita-module-slot"', false);
            $this->post($root . '/website/topbar?_vita_content=1', ['_method' => 'PUT', 'enabled' => 1, 'show_social_links' => 0, 'email' => 'navigation@example.test'])
                ->assertRedirect($root . '/website/topbar?_vita_content=1')->assertSessionHas('success', 'Barra superior actualizada.');
            $this->get($root . '/website/topbar?_vita_content=1')->assertOk()->assertSee('Barra superior actualizada.')->assertSee('navigation@example.test')
                ->assertDontSee('id="superadmin-sidebar"', false);
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function test_global_panel_renders_matching_navigation_without_a_clinic_context(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $this->app->instance('customer', null);
        $this->app->instance('logos_by_location', []);
        $this->actingAs($user);
        $request = Request::create('/dashboard');
        $request->setLaravelSession(app('session.store'));
        $route = new Route('GET', 'dashboard', fn () => null);
        $route->name('dashboard.index')->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        $html = view('layouts.app', ['slot' => new HtmlString('<div>Contenido del módulo</div>'), 'header' => new HtmlString('Panel')])->render();
        foreach (['superadmin-sidebar', 'superadmin-sidebar-preview', 'Compactar menú', 'Panel', 'Clientes', 'vita-content-current'] as $label) $this->assertStringContainsString($label, $html);
        $this->assertStringNotContainsString('<div>Contenido del módulo</div>', $html);
        $this->assertStringContainsString(route('superadmin.customer'), $html);
        $this->assertStringContainsString(route('superadmin.profile.index'), $html);
        $this->assertStringContainsString('action="' . route('logout') . '"', $html);
        $this->assertStringContainsString('data-viti-nav-toggle', $html);
        $this->assertStringNotContainsString('clinic-branch-switcher', $html);
        $request->query->set('_vita_content', 1);
        $content = view('layouts.app', ['slot' => new HtmlString('<div>Contenido del módulo</div>'), 'header' => new HtmlString('Panel')])->render();
        $this->assertStringContainsString('<div>Contenido del módulo</div>', $content);
        $this->assertStringContainsString('data-vita-content="true"', $content);
        $this->assertStringNotContainsString('id="superadmin-sidebar"', $content);
        $this->assertStringNotContainsString('id="vita-module-slot"', $content);
    }
}
