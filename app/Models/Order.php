<?php

namespace App\Models;

use App\Models\Traits\RecordsActivity;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[UseFactory(OrderFactory::class)]
class Order extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'user_id', 'shift_id', 'order_number', 'status', 'subtotal', 'shipping_cost', 'total',
        'payment_method', 'payment_status', 'notes', 'admin_notes',
        'address_id', 'shipping_courier', 'shipping_tracking_number',
        'shipped_at', 'delivered_at', 'cancelled_at',
        'coupon_id', 'discount',
        'guest_name', 'guest_email', 'guest_phone', 'guest_token',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class)->latest('event_time');
    }

    public function orderDownloads(): HasMany
    {
        return $this->hasMany(OrderDownload::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null && ! empty($this->guest_token);
    }

    public function getCustomerNameAttribute(): ?string
    {
        return $this->guest_name ?? $this->user?->name;
    }

    public function getCustomerEmailAttribute(): ?string
    {
        return $this->guest_email ?? $this->user?->email;
    }

    public function getCustomerPhoneAttribute(): ?string
    {
        return $this->guest_phone ?? $this->address?->phone;
    }
}
