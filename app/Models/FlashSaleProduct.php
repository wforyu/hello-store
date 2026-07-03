<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleProduct extends Model
{
    protected $fillable = [
        'flash_sale_id', 'product_id',
        'discount_type', 'discount_value',
        'max_qty', 'sold_qty',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_qty' => 'integer',
            'sold_qty' => 'integer',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
