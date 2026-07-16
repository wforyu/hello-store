<?php

namespace App\Models;

use App\Services\PushNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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
        $notification = static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'link_url' => $linkUrl,
        ]);

        self::sendPush($userId, $title, $body, $type, $linkUrl);

        return $notification;
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

    private static function sendPush(int $userId, string $title, ?string $body, string $type, ?string $linkUrl): void
    {
        try {
            if (! class_exists(PushNotificationService::class)) {
                return;
            }

            $data = [
                'type' => $type,
                'link_url' => $linkUrl ?? '',
            ];

            if ($linkUrl) {
                $data['screen'] = self::resolveScreenFromLink($linkUrl);
                $data['params'] = self::resolveParamsFromLink($linkUrl);
            }

            PushNotificationService::sendToUser($userId, $title, $body ?? '', $data);
        } catch (\Exception $e) {
            Log::warning('Push notification failed: '.$e->getMessage());
        }
    }

    private static function resolveScreenFromLink(string $linkUrl): string
    {
        if (str_contains($linkUrl, '/orders/')) {
            return 'OrderDetail';
        }

        return 'Notifications';
    }

    private static function resolveParamsFromLink(string $linkUrl): array
    {
        if (preg_match('/\/orders\/(\d+)/', $linkUrl, $matches)) {
            return ['orderId' => (int) $matches[1]];
        }

        return [];
    }
}
