<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    protected $fillable = [
        'order_id', 'location', 'status', 'description', 'event_time',
    ];

    protected $appends = ['status_label'];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu',
            'picked_up' => 'Dijemput Kurir',
            'in_transit' => 'Dalam Perjalanan',
            'sorting' => 'Disortir',
            'out_for_delivery' => 'Diantar Kurir',
            'delivered' => 'Telah Sampai',
            'failed' => 'Gagal Kirim',
            default => $this->status,
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
