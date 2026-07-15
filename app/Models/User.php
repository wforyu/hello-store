<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'points', 'segment', 'total_spent'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'points' => 'integer',
            'segment' => 'string',
            'total_spent' => 'decimal:2',
        ];
    }

    public function getSegmentPointsMultiplier(): float
    {
        return match ($this->segment) {
            'diamond' => 2.5,
            'platinum' => 2.0,
            'gold' => 1.5,
            'silver' => 1.2,
            default => 1.0,
        };
    }

    public function getSegmentDiscountRate(): float
    {
        return match ($this->segment) {
            'diamond' => 0.20,
            'platinum' => 0.15,
            'gold' => 0.10,
            'silver' => 0.05,
            default => 0,
        };
    }

    public function getSegmentLabel(): string
    {
        return match ($this->segment) {
            'diamond' => 'Diamond',
            'platinum' => 'Platinum',
            'gold' => 'Gold',
            'silver' => 'Silver',
            default => 'Bronze',
        };
    }

    public function getSegmentColor(): string
    {
        return match ($this->segment) {
            'diamond' => '#8B5CF6',
            'platinum' => '#10B981',
            'gold' => '#F59E0B',
            'silver' => '#9CA3AF',
            default => '#D97706',
        };
    }

    public static function getSegmentThresholds(): array
    {
        return [
            'bronze' => 0,
            'silver' => 500000,
            'gold' => 2000000,
            'platinum' => 5000000,
            'diamond' => 15000000,
        ];
    }

    public function autoUpgradeSegment(): void
    {
        $thresholds = self::getSegmentThresholds();
        $newSegment = 'bronze';
        foreach (array_reverse($thresholds, true) as $segment => $threshold) {
            if ($this->total_spent >= $threshold) {
                $newSegment = $segment;
                break;
            }
        }
        if ($newSegment !== $this->segment) {
            $oldSegment = $this->segment;
            $this->update(['segment' => $newSegment]);
            Notification::createForUser(
                $this->id,
                'tier',
                'Selamat! Tier Anda naik ke '.ucfirst($newSegment),
                'Tier Anda berubah dari '.ucfirst($oldSegment).' ke '.ucfirst($newSegment).'.',
                null,
                null
            );
        }
    }

    public function getPointsRate(): float
    {
        return (float) (Setting::get('points_rate', '10')) / 100;
    }

    public function getMaxRedeemPercent(): float
    {
        return (float) (Setting::get('points_max_redeem', '50')) / 100;
    }

    public function addPoints(int $points, string $description, ?Model $reference = null): PointTransaction
    {
        $multiplied = (int) round($points * $this->getSegmentPointsMultiplier());
        $this->increment('points', $multiplied);

        return $this->pointTransactions()->create([
            'points' => $multiplied,
            'type' => 'earned',
            'description' => $description,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference ? $reference->id : null,
        ]);
    }

    public function redeemPoints(int $points, string $description, ?Model $reference = null): PointTransaction
    {
        $points = min($points, $this->points);
        if ($points <= 0) {
            throw new \InvalidArgumentException('Poin tidak mencukupi untuk ditukarkan.');
        }

        $this->decrement('points', $points);

        return $this->pointTransactions()->create([
            'points' => -$points,
            'type' => 'redeemed',
            'description' => $description,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference ? $reference->id : null,
        ]);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function socialFollowClaims(): HasMany
    {
        return $this->hasMany(SocialFollowClaim::class);
    }
}
