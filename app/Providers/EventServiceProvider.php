<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, function ($event) {
            if ($event->channel !== 'database') return;
            $data = $event->response?->data ?? [];
            if (!empty($data['customer_id'])) {
                \App\Support\UserRealtime::notify((int) $data['customer_id'], (int) $event->notifiable->getKey(), 'system');
            }
        });
        foreach ([\App\Events\ChatMessageSent::class, \App\Events\ChatMessageDeleted::class, \App\Events\ChatMessageReactionChanged::class] as $class) {
            Event::listen($class, function ($event) {
                $conversationId = $event instanceof \App\Events\ChatMessageSent ? (int) $event->message->conversation_id : $event->conversationId;
                \App\Support\UserRealtime::chat($event->customerId, $conversationId);
            });
        }
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
