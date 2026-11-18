<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Support\LoginMotivation;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginMotivationTest extends TestCase
{
    public function test_login_renders_tenant_identity_and_validation_in_its_own_layout(): void
    {
        $this->app->instance('customer', (object) ['id' => 0, 'name' => 'Consulta de prueba', 'color' => 'purple']);
        $this->app->instance('logos_by_location', ['login' => (object) ['url' => 'logos/test-clinic.png']]);
        $errors = new \Illuminate\Support\ViewErrorBag();
        $errors->put('default', new \Illuminate\Support\MessageBag(['email' => 'Revisa tu correo.']));
        view()->share('errors', $errors);
        $html = view('auth.login', ['motivation' => config('login_motivation.0')])->render();
        $this->assertStringContainsString('class="vita-login"', $html);
        $this->assertStringContainsString('Consulta de prueba', $html);
        $this->assertStringContainsString('storage/logos/test-clinic.png', $html);
        $this->assertStringContainsString('--accent: #7e22ce', $html);
        $this->assertStringContainsString('Revisa tu correo.', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertStringContainsString('password-toggle', $html);
        $this->assertStringContainsString('¡Bienvenido de nuevo!', $html);
        $this->assertStringNotContainsString('Volver al sitio web', $html);
        $this->assertStringNotContainsString('login-background-botanical.png', $html);
        $this->assertSame('layouts.guest', (new \App\View\Components\GuestLayout())->render()->name());
    }

    public function test_public_site_link_follows_tenant_maintenance_setting(): void
    {
        $clinic = \App\Models\Customer::whereNotNull('domain')->first();
        if (!$clinic) $this->markTestSkipped('Requires a clinic fixture.');
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $this->app->instance('customer', $clinic);
            view()->share('errors', new \Illuminate\Support\ViewErrorBag());
            $maintenance = \App\Models\WebsiteMaintenance::firstOrNew(['customer_id' => $clinic->id]);
            $maintenance->enabled = false;
            $maintenance->save();
            $data = ['motivation' => config('login_motivation.0')];
            $html = view('auth.login', $data)->render();
            $url = action([\App\Http\Controllers\customer\WebController::class, 'index'], ['customer' => $clinic->domain]);
            $this->assertStringContainsString('Volver al sitio web', $html);
            $this->assertStringContainsString('href="' . e($url) . '"', $html);
            $maintenance->enabled = true;
            $maintenance->save();
            $this->assertStringNotContainsString('Volver al sitio web', view('auth.login', $data)->render());
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    public function test_catalog_has_fifty_distinct_complete_messages(): void
    {
        $phrases = config('login_motivation');
        $this->assertCount(50, $phrases);
        $this->assertCount(50, array_unique(array_column($phrases, 'title')));
        foreach ($phrases as $phrase) {
            $this->assertSame(['label', 'title', 'description'], array_keys($phrase));
            $this->assertSame(mb_strtoupper($phrase['label']), $phrase['label']);
            foreach ($phrase as $text) $this->assertNotEmpty(trim($text));
            $this->assertLessThanOrEqual(55, mb_strlen($phrase['title']));
        }
    }

    public function test_login_selects_on_each_request_without_consecutive_repeats(): void
    {
        $request = Request::create('http://clinic-a.test/login');
        $request->setLaravelSession(app('session.store'));
        $controller = new AuthenticatedSessionController();
        $previous = null;
        for ($i = 0; $i < 20; $i++) {
            $view = $controller->create($request);
            $phrase = $view->getData()['motivation'];
            $this->assertNotSame($previous, $phrase);
            $this->assertSame($phrase, $view->getData()['motivation']);
            $previous = $phrase;
        }
    }

    public function test_each_tenant_keeps_its_own_previous_message(): void
    {
        $session = app('session.store');
        $service = new LoginMotivation();
        $first = $service->next($session, 'clinic-a.test');
        $state = $session->get('login_motivation');
        $service->next($session, 'clinic-b.test');
        $this->assertSame($state['previous'][hash('sha256', 'clinic-a.test')], $session->get('login_motivation.previous.' . hash('sha256', 'clinic-a.test')));
        $this->assertNotSame($first, $service->next($session, 'clinic-a.test'));
    }
}
