<?php

namespace App\Models;

use App\Models\Traits\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlashSale extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'start_time', 'end_time',
        'status', 'is_active', 'banner_image',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'flash_sale_products')
            ->withPivot(['discount_type', 'discount_value', 'max_qty', 'sold_qty'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('start_time')->orWhere('start_time', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>=', now()));
    }
}
