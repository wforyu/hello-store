<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'icon', 'link_url', 'is_read', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public static function createForUser($userId, string $type, string $title, ?string $body = null, ?string $icon = null, ?string $linkUrl = null): self
    {
        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'link_url' => $linkUrl,
        ]);
    }

    public static function createForAdmins(string $type, string $title, ?string $body = null, ?string $icon = null, ?string $linkUrl = null): void
    {
        $adminIds = User::whereIn('role', ['admin'])->pluck('id');
        foreach ($adminIds as $userId) {
            static::createForUser($userId, $type, $title, $body, $icon, $linkUrl);
        }
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }
}
