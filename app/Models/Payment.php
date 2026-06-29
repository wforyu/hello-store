<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'method', 'amount', 'status', 'proof_image',
        'bank_name', 'account_name', 'account_number', 'paid_at', 'notes',
    ];

    protected $appends = ['proof_image_url'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getProofImageUrlAttribute(): ?string
    {
        if (! $this->proof_image) {
            return null;
        }
        if (Str::startsWith($this->proof_image, ['http://', 'https://'])) {
            return $this->proof_image;
        }

        return asset('storage/'.$this->proof_image);
    }
}
