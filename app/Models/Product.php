<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (Product $product) {
            if ($product->wasRecentlyCreated || $product->wasChanged('images')) {
                $product->productImages()->delete();
                $images = $product->images ?? [];
                foreach ($images as $i => $img) {
                    if (is_string($img) && $img !== '') {
                        $product->productImages()->create([
                            'image' => $img,
                            'sort_order' => $i,
                        ]);
                    }
                }
            }

            if ($product->wasChanged('stock') && ! $product->wasRecentlyCreated) {
                $diff = $product->stock - $product->getOriginal('stock');
                if ($diff !== 0) {
                    $product->recordStockHistory($diff, 'manual', 'Diubah melalui admin');
                }
            }
        });
    }

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'price', 'compare_price',
        'stock', 'sku', 'weight', 'images', 'is_active', 'featured',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'images' => 'array',
            'is_active' => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function stockHistories(): HasMany
    {
        return $this->hasMany(StockHistory::class)->latest();
    }

    public function recordStockHistory(int $quantityChange, string $type, ?string $notes = null, ?string $referenceType = null, ?int $referenceId = null): void
    {
        $before = $this->stock - $quantityChange;

        $this->stockHistories()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity_change' => $quantityChange,
            'stock_before' => $before,
            'stock_after' => $this->stock,
            'notes' => $notes,
        ]);
    }

    public function getMainImageAttribute(): ?string
    {
        return $this->productImages->first()?->url;
    }
}
