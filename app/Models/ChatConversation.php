<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatConversation extends Model
{
    protected $fillable = ['customer_id', 'clinic_branch_id', 'patient_id', 'created_by', 'type', 'title'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function clinicBranch(): BelongsTo { return $this->belongsTo(ClinicBranch::class, 'clinic_branch_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'conversation_id', 'user_id')
            ->withPivot('last_read_at', 'left_at', 'hidden_at')->withTimestamps();
    }
    public function messages(): HasMany { return $this->hasMany(ChatMessage::class, 'conversation_id'); }
    public function latestMessage(): HasOne
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->one()->latestOfMany();
    }
}
