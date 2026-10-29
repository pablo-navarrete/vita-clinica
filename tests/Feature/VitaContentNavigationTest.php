<?php
namespace Tests\Feature;

use App\Http\Middleware\PersistVitaContentNavigation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class VitaContentNavigationTest extends TestCase
{
    public function test_content_form_redirect_preserves_query_and_anchor(): void
    {
        $request = Request::create('https://clinic.test/save', 'POST', ['_vita_content' => 1]);
        $response = (new PersistVitaContentNavigation)->handle($request, fn () => new RedirectResponse('https://clinic.test/patients?encounter_id=10#attention'));
        $this->assertSame('https://clinic.test/patients?encounter_id=10&_vita_content=1#attention', $response->getTargetUrl());
    }

    public function test_signed_and_external_redirects_are_not_modified(): void
    {
        foreach (['https://other.test/patients', 'https://clinic.test/telemedicine/10/patient?signature=abc', 'https://clinic.test/login'] as $target) {
            $request = Request::create('https://clinic.test/save', 'POST', ['_vita_content' => 1]);
            $response = (new PersistVitaContentNavigation)->handle($request, fn () => new RedirectResponse($target));
            $this->assertSame($target, $response->getTargetUrl());
        }
    }

    public function test_programmatic_iframe_navigation_stays_in_content_layout(): void
    {
        $request = Request::create('https://clinic.test/appointments');
        $request->headers->set('Sec-Fetch-Dest', 'iframe');
        $request->headers->set('Referer', 'https://clinic.test/patients?_vita_content=1');
        (new PersistVitaContentNavigation)->handle($request, function ($request) {
            $this->assertTrue($request->boolean('_vita_content'));
            return response('ok');
        });
    }

    public function test_external_referer_cannot_select_content_layout(): void
    {
        $request = Request::create('https://clinic.test/appointments');
        $request->headers->set('Sec-Fetch-Dest', 'iframe');
        $request->headers->set('Referer', 'https://other.test/patients?_vita_content=1');
        (new PersistVitaContentNavigation)->handle($request, function ($request) {
            $this->assertFalse($request->boolean('_vita_content'));
            return response('ok');
        });
    }
    public function test_explicit_branch_context_reloads_the_menu_even_from_a_content_referer(): void
    {
        $request = Request::create('https://clinic.test/dashboard?_vita_frame=1&_vita_content=0');
        $request->headers->set('Sec-Fetch-Dest', 'iframe');
        $request->headers->set('Referer', 'https://clinic.test/patients?_vita_content=1');
        (new PersistVitaContentNavigation)->handle($request, function ($request) {
            $this->assertFalse($request->boolean('_vita_content'));
            $this->assertTrue($request->boolean('_vita_frame'));
            return response('ok');
        });
    }

}
