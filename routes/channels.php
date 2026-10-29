<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{customerId}.{conversationId}', function ($user, $customerId, $conversationId) {
    if (! app()->bound('customer') || (int) app('customer')->id !== (int) $customerId) {
        return false;
    }

    $isSuperAdmin = $user->customer_id === null
        && $user->roles()->where('roles.name', 'Super Administrador')->exists();

    if (!$isSuperAdmin && ((int) $user->customer_id !== (int) $customerId || (int) $user->status !== 1)) {
        return false;
    }

    $conversationQuery = \App\Models\ChatConversation::query()
        ->where('id', $conversationId)
        ->where('customer_id', $customerId);

    if (!$isSuperAdmin) {
        $conversationQuery->whereHas('participants', fn ($query) => $query->where('users.id', $user->id)->whereNull('chat_participants.left_at'));
    }

    return $conversationQuery->exists();
});
