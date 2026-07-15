<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialFollowClaim extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'status',
        'reward_tier',
        'reward_points',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'reward_points' => 'integer',
    ];

    public const PLATFORMS = ['instagram', 'tiktok'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approve(?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'admin_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        $user = $this->user;
        if ($this->reward_tier && $this->reward_tier !== $user->segment) {
            $user->update(['segment' => $this->reward_tier]);
        }
        if ($this->reward_points > 0) {
            $user->addPoints($this->reward_points, 'Reward follow '.$this->platform);
        }
    }

    public function reject(?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'admin_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
