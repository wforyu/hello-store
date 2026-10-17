<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Deterministic POS demo catalog.
 *
 * Everything here is fixed data on purpose. A POS test needs a catalog that
 * is identical on every fresh install, otherwise "the seeded product" is a
 * different product each time and offline/online sync runs stop being
 * reproducible.
 *
 * Prices are whole Rupiah (the column is decimal(12,2) but the app never
 * uses cents). Stock is deliberately uneven -- a few items are out of stock
 * and a few sit at the "low stock" threshold so the cashier warnings and the
 * offline stock-conflict path both have something to bite on.
 */
class PosDummyProductsSeeder extends Seeder
{
    /**
     * @var array<string, array{slug: string, description: string}>
     */
    protected array $categories = [
        'Makanan' => [
            'slug' => 'makanan',
            'description' => 'Makanan instan, packaged, dan beku',
        ],
        'Minuman' => [
            'slug' => 'minuman',
            'description' => 'Air mineral, Teh, Kopi, dan minuman dingin',
        ],
        'Snack' => [
            'slug' => 'snack',
            'description' => 'Camilan dan makanan ringan',
        ],
        'Rumah Tangga' => [
            'slug' => 'rumah-tangga',
            'description' => 'Kebutuhan harian dan perlengkapan rumah',
        ],
        'Kebutuhan Pribadi' => [
            'slug' => 'kebutuhan-pribadi',
            'description' => 'Perawatan diri dan kebersihan',
        ],
        'Elektronik' => [
            'slug' => 'elektronik',
            'description' => 'Aksesori dan perangkat elektronik',
        ],
    ];

    /**
     * product => [category slug, name, price, cost, stock, weight, featured]
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: int, 4: int, 5: float, 6: bool}>
     */
    protected array $products = [
        // Makanan
        ['makanan', 'Indomie Goreng Special', 3500, 2900, 240, 0.08, true],
        ['makanan', 'Indomie Kuah Soto', 3200, 2700, 180, 0.08, false],
        ['makanan', 'Mi Ayam Berbumi', 15000, 12000, 45, 0.4, true],
        ['makanan', 'Beras Premium 5kg', 78000, 69000, 18, 5.0, false],
        ['makanan', 'Minyak Goreng 1L', 18500, 15800, 32, 1.0, false],
        ['makanan', 'Gula Pasir 1kg', 16000, 14200, 40, 1.0, false],
        ['makanan', 'Telur Ayam 1kg', 29000, 25500, 0, 1.0, false],
        ['makanan', 'Kecap Manis 520ml', 13500, 11200, 26, 0.55, false],

        // Minuman
        ['minuman', 'Air Mineral 600ml', 4000, 3000, 300, 0.65, true],
        ['minuman', 'Air Mineral 1.5L', 9500, 8000, 120, 1.6, false],
        ['minuman', 'Teh Botol Sosro 450ml', 5000, 4200, 150, 0.5, true],
        ['minuman', 'Kopi Kapal Api Sachet', 1500, 1100, 400, 0.02, false],
        ['minuman', 'Susu UHT 1L', 18500, 16000, 38, 1.0, false],
        ['minuman', 'Sirup Markisa 630ml', 32000, 27500, 12, 0.7, false],

        // Snack
        ['snack', 'Chitato 68g', 11000, 9000, 90, 0.07, true],
        ['snack', 'Oreo Sandwich', 9500, 7800, 75, 0.07, false],
        ['snack', 'Taro Rings', 8500, 7000, 60, 0.05, false],
        ['snack', 'Cokelat Batang 2s', 6500, 5200, 110, 0.04, false],
        ['snack', 'Keripik Kentang Ori', 12000, 9800, 5, 0.09, false],

        // Rumah Tangga
        ['rumah-tangga', 'Sabun Mandi Cair 450ml', 24000, 20500, 22, 0.5, true],
        ['rumah-tangga', 'Deterjen Bubuk 800g', 33000, 28000, 16, 0.85, false],
        ['rumah-tangga', 'Piring Keramik 24cm', 18500, 15000, 30, 0.6, false],
        ['rumah-tangga', 'Sendok Set Stainless', 27000, 22000, 14, 0.4, false],
        ['rumah-tangga', 'Sapu Ijuk', 12000, 9500, 25, 0.5, false],
        ['rumah-tangga', 'Kantong Sampah 30L', 19500, 16000, 8, 0.8, false],

        // Kebutuhan Pribadi
        ['kebutuhan-pribadi', 'Pasta Gigi 190g', 17500, 14800, 28, 0.2, true],
        ['kebutuhan-pribadi', 'Sabun Cuci Piring 780ml', 14500, 12000, 34, 0.8, false],
        ['kebutuhan-pribadi', 'Bedak Wajah 25g', 22000, 18500, 19, 0.03, false],
        ['kebutuhan-pribadi', 'Sarung Tangan Medis (100)', 45000, 38000, 4, 0.6, false],
        ['kebutuhan-pribadi', 'Karet Rambut (100)', 12500, 10000, 21, 0.15, false],

        // Elektronik
        ['elektronik', 'Kabel Charger Type-C 1m', 35000, 28000, 24, 0.05, true],
        ['elektronik', 'Powerbank 10000mAh', 185000, 155000, 6, 0.3, false],
        ['elektronik', 'Earphone Kabel 3.5mm', 28000, 22000, 17, 0.04, false],
        ['elektronik', 'Baterai AA (4pcs)', 22000, 18000, 27, 0.1, false],
        ['elektronik', 'Lampu LED 9W', 16500, 13000, 20, 0.08, false],
        ['elektronik', 'Adaptor 20W USB-C', 89000, 72000, 3, 0.15, false],
    ];

    public function run(): void
    {
        $categoryIds = [];

        foreach ($this->categories as $name => $meta) {
            $category = Category::withTrashed()->updateOrCreate(
                ['slug' => $meta['slug']],
                [
                    'name' => $name,
                    'description' => $meta['description'],
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );

            if ($category->trashed()) {
                $category->restore();
            }

            $categoryIds[$meta['slug']] = $category->id;
        }

        $created = 0;

        foreach ($this->products as $index => [$categorySlug, $name, $price, $cost, $stock, $weight, $featured]) {
            // Slug is the lookup key, never the SKU. BundleSeeder already
            // ships a handful of these products, so matching on SKU would
            // try to insert a second row under a slug that is already taken.
            $slug = Str::slug($name);

            $product = Product::withTrashed()->where('slug', $slug)->first() ?? new Product;

            $product->slug = $slug;
            $product->name = $name;
            $product->sku = $this->sku($name, $index + 1);
            $product->category_id = $categoryIds[$categorySlug] ?? null;
            $product->description = $name.' — produk demo untuk pengujian mesin POS.';
            $product->price = $price;
            $product->compare_price = $price + 1000;
            $product->cost_price = $cost;
            $product->stock = $stock;
            $product->weight = $weight;
            $product->is_active = true;
            $product->featured = $featured;
            $product->deleted_at = null;

            $product->save();

            $created++;
        }

        $this->command?->info(sprintf(
            'POS dummy catalog siap: %d kategori, %d produk.',
            count($categoryIds),
            $created
        ));
    }

    /**
     * Stable, human-readable SKU.
     *
     * The array index is part of the SKU on purpose. Truncating the name
     * alone is not unique enough: "Air Mineral 600ml" and "Air Mineral 1.5L"
     * both collapse to AIRMINERAL, and the second one would silently
     * overwrite the first.
     */
    protected function sku(string $name, int $index): string
    {
        $code = preg_replace('/[^A-Z0-9]/', '', Str::upper($name));

        return 'HS-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).'-'.substr($code, 0, 8);
    }
}
