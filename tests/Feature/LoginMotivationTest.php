<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Support\LoginMotivation;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginMotivationTest extends TestCase
{
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
