<?php

namespace Tests\Feature;

use App\Events\UserRealtimeUpdated;
use App\Models\User;
use App\Models\ChatConversation;
use App\Notifications\AppointmentNotification;
use App\Support\UserRealtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserRealtimeTest extends TestCase
{
    public function test_database_notifications_emit_only_system_updates_and_reads_are_tenant_scoped(): void
    {
        $user = User::whereNotNull('customer_id')->where('status', 1)->whereHas('customer', fn ($q) => $q->where('status', 1))
            ->whereHas('roles', fn ($q) => $q->where('name', 'Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires an active clinic user.');
        $customer = $user->customer;
        $root = 'http://' . $customer->domain . '.' . config('app.domain');
        Event::fake([UserRealtimeUpdated::class]);
        $id = (string) Str::uuid(); $foreignId = (string) Str::uuid();
        $memberships = DB::table('chat_participants')->where('user_id', $user->id)->get(['conversation_id', 'last_read_at', 'updated_at']);
        try {
            $notification = new AppointmentNotification(['customer_id' => $customer->id, 'title' => 'Sistema realtime test', 'message' => 'Aviso de prueba']);
            $notification->id = $id;
            $user->notify($notification);
            Event::assertDispatched(UserRealtimeUpdated::class, fn ($e) => $e->area === 'system' && $e->customerId === (int) $customer->id && $e->userIds === [(int) $user->id]);
            DB::table('notifications')->insert(['id' => $foreignId, 'type' => AppointmentNotification::class, 'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id, 'data' => json_encode(['customer_id' => 999999, 'title' => 'Foreign realtime test']), 'created_at' => now(), 'updated_at' => now()]);
            $response = $this->actingAs($user)->getJson($root . '/notifications/summary')->assertOk();
            $this->assertStringContainsString('Sistema realtime test', $response->json('html'));
            $this->assertStringNotContainsString('Foreign realtime test', $response->json('html'));
            $unread = $response->json('unread');
            $this->patchJson($root . '/notifications/' . $id . '/read')->assertOk();
            $this->getJson($root . '/notifications/summary')->assertJsonPath('unread', $unread - 1);
            $this->patchJson($root . '/notifications/' . $foreignId . '/read')->assertNotFound();
            $this->get($root . '/notifications/' . $foreignId . '/open')->assertRedirect();
            $this->assertNull(DB::table('notifications')->where('id', $foreignId)->value('read_at'));
            $channel = 'private-clinic-user.' . $customer->id . '.' . $user->id;
            $this->postJson($root . '/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1'])->assertOk();
            $this->postJson($root . '/broadcasting/auth', ['channel_name' => 'private-clinic-user.999999.' . $user->id, 'socket_id' => '1.1'])->assertForbidden();
            $this->postJson($root . '/broadcasting/auth', ['channel_name' => 'private-clinic-user.' . $customer->id . '.999999', 'socket_id' => '1.1'])->assertForbidden();
            foreach (['/chat?_vita_frame=1', '/chat?_vita_frame=1&_vita_content=1', '/notifications?_vita_frame=1&_vita_content=1'] as $path) {
                $html = $this->get($root . $path)->assertOk()->getContent();
                preg_match_all('/<script>(.*?)<\/script>/s', $html, $scripts);
                $check = new \Symfony\Component\Process\Process(['node', '--check']);
                $check->setInput(implode("\n", $scripts[1]));
                $check->run();
                $this->assertTrue($check->isSuccessful(), $check->getErrorOutput());
            }
        } finally {
            DB::table('notifications')->whereIn('id', [$id, $foreignId])->delete();
            foreach ($memberships as $membership) {
                DB::table('chat_participants')->where('user_id', $user->id)->where('conversation_id', $membership->conversation_id)
                    ->update(['last_read_at' => $membership->last_read_at, 'updated_at' => $membership->updated_at]);
            }
        }
    }

    public function test_chat_updates_target_active_participants_and_wait_for_commit(): void
    {
        $users = User::whereNotNull('customer_id')->where('status', 1)->get()->groupBy('customer_id')->first(fn ($items) => $items->count() >= 2);
        if (!$users) $this->markTestSkipped('Requires two clinic users.');
        $user = $users[0]; $other = $users[1]; $conversation = null;
        Event::fake([UserRealtimeUpdated::class]);
        try {
            DB::transaction(function () use ($user, $other, &$conversation) {
                $conversation = ChatConversation::create(['customer_id' => $user->customer_id, 'created_by' => $user->id, 'type' => 'group', 'title' => 'Realtime test']);
                $conversation->participants()->attach([$user->id => ['left_at' => null], $other->id => ['left_at' => now()]]);
                UserRealtime::chat((int) $user->customer_id, $conversation->id);
                Event::assertNotDispatched(UserRealtimeUpdated::class);
            });
            Event::assertDispatched(UserRealtimeUpdated::class, fn ($e) => $e->area === 'chat' && $e->userIds === [(int) $user->id]);
            $event = new UserRealtimeUpdated((int) $user->customer_id, [(int) $user->id], 'chat', $conversation->id);
            $this->assertSame(['area' => 'chat', 'conversation_id' => $conversation->id], $event->broadcastWith());
            $this->assertSame('private-clinic-user.' . $user->customer_id . '.' . $user->id, $event->broadcastOn()[0]->name);
        } finally {
            if ($conversation) { $conversation->participants()->detach(); $conversation->delete(); }
        }
    }
}
