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

#[Fillable(['name', 'email', 'password', 'role', 'points', 'segment'])]
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
        ];
    }

    public function getSegmentPointsMultiplier(): float
    {
        return match ($this->segment) {
            'platinum' => 2.0,
            'gold' => 1.5,
            'silver' => 1.2,
            default => 1.0,
        };
    }

    public function getSegmentDiscountRate(): float
    {
        return match ($this->segment) {
            'platinum' => 0.15,
            'gold' => 0.10,
            'silver' => 0.05,
            default => 0,
        };
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
}
