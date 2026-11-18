<?php

namespace Tests\Feature;

use App\Http\Controllers\customer\ChatController;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChatSuperAdminReadOnlyTest extends TestCase
{
    public function test_superadmin_can_read_but_all_chat_mutations_are_forbidden(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        $clinic = Customer::first();
        if (!$user || !$clinic) $this->markTestSkipped('Requires a global administrator and clinic fixture.');
        $this->app->instance('customer', $clinic);
        $this->actingAs($user);
        $request = Request::create('/chat', 'POST');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $route = new \Illuminate\Routing\Route('POST', 'chat', fn () => null);
        $route->bind($request);
        $route->setParameter('customer', $clinic->domain);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        $controller = new ChatController();
        $permission = new \ReflectionMethod($controller, 'hasChatPermission');
        $this->assertTrue($permission->invoke($controller, $user, $clinic->id, 'chat.view'));
        $this->assertFalse($permission->invoke($controller, $user, $clinic->id, 'chat.send'));
        $this->assertFalse($permission->invoke($controller, $user, $clinic->id, 'chat.manage'));
        foreach ([
            ['store', [$request]],
            ['send', [$request, $clinic->domain, 0]],
            ['forward', [$request, $clinic->domain]],
            ['toggleReaction', [$request, $clinic->domain, 0, 0]],
            ['hideConversation', [$request, $clinic->domain, 0]],
            ['deleteMessage', [$request, $clinic->domain, 0, 0]],
            ['addParticipant', [$request, $clinic->domain, 0]],
            ['removeParticipant', [$request, $clinic->domain, 0, 0]],
        ] as [$method, $arguments]) {
            try {
                $controller->$method(...$arguments);
                $this->fail($method . ' must deny writes.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode(), $method);
            }
        }
        $this->assertSame(200, $controller->summary()->getStatusCode());
        $conversation = ChatConversation::where('customer_id', $clinic->id)->first();
        if ($conversation) {
            $response = $controller->messages($request, $clinic->domain, $conversation->id);
            $this->assertSame(200, $response->getStatusCode());
            $payload = $response->getData(true)['conversation'];
            foreach (['can_send', 'can_manage', 'can_delete'] as $capability) $this->assertFalse($payload[$capability]);
        }
    }
}
