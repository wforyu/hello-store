<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBundle extends Model
{
    use SoftDeletes;

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
}
