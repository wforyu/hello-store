<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductAttribute extends Model
{
    protected $fillable = [
        'product_id', 'type', 'value', 'label', 'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_attributes');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}
