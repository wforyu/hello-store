<?php

namespace App\Models;

use App\Models\Traits\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBundle extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'bundle_price',
        'total_original_price', 'is_active', 'start_time',
        'end_time', 'image',
    ];

    protected function casts(): array
    {
        return [
            'bundle_price' => 'decimal:2',
            'total_original_price' => 'decimal:2',
            'is_active' => 'boolean',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'bundle_products')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function getCalculatedOriginalPrice(): float
    {
        if ((float) $this->total_original_price > 0) {
            return (float) $this->total_original_price;
        }

        $total = 0;
        foreach ($this->products as $product) {
            $qty = (int) ($product->pivot->quantity ?? 1);
            $total += (float) $product->price * $qty;
        }

        return $total;
    }
}
