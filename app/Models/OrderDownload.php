<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDownload extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'user_id', 'downloaded_at', 'download_count',
    ];

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
            'download_count' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canDownload(): bool
    {
        return $this->download_count < 5;
    }

    public function recordDownload(): void
    {
        $this->increment('download_count');
        $this->update(['downloaded_at' => now()]);
    }
}
