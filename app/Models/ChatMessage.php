<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'body', 'reply_to_id', 'forwarded_from_message_id'];

    protected $casts = ['body' => 'encrypted'];

    public function conversation(): BelongsTo { return $this->belongsTo(ChatConversation::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function replyTo(): BelongsTo { return $this->belongsTo(self::class, 'reply_to_id'); }
    public function forwardedFrom(): BelongsTo { return $this->belongsTo(self::class, 'forwarded_from_message_id'); }
    public function reactions(): HasMany { return $this->hasMany(ChatMessageReaction::class, 'message_id'); }
    public function userDeletions(): HasMany { return $this->hasMany(ChatMessageUserDeletion::class, 'message_id'); }
}
