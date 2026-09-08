<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'guest_session_id',
        'guest_name',
        'subject',
        'last_message_at',
        'is_open',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_open' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function unreadCustomerMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)
            ->where('sender_type', 'customer')
            ->whereNull('read_at');
    }

    public function customerName(): string
    {
        return $this->user?->name
            ?? $this->guest_name
            ?? 'Guest #'.substr($this->guest_session_id ?? '0', 0, 6);
    }

    public function getCustomerLabelAttribute(): string
    {
        return $this->customerName();
    }
}
