<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBundle;
use Illuminate\Database\Seeder;

class BundleSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(
            ['slug' => 'elektronik'],
            ['name' => 'Elektronik', 'description' => 'Perangkat elektronik', 'is_active' => true, 'sort_order' => 1]
        );

        $products = [
            Product::firstOrCreate(
                ['slug' => 'tws-bluetooth-headphone'],
                [
                    'name' => 'TWS Bluetooth Headphone',
                    'description' => 'Earphone wireless Bluetooth 5.0 dengan noise cancelling dan bass boost. Nyaman dipakai seharian.',
                    'price' => 189000,
                    'compare_price' => 299000,
                    'cost_price' => 85000,
                    'stock' => 50,
                    'sku' => 'ELEC-TWS-001',
                    'weight' => 0.1,
                    'category_id' => $category->id,
                    'is_active' => true,
                    'featured' => true,
                ]
            ),
            Product::firstOrCreate(
                ['slug' => 'smartwatch-fitnes-tracker'],
                [
                    'name' => 'Smartwatch Fitness Tracker',
                    'description' => 'Smartwatch dengan fitur heart rate monitor, step counter, sleep tracker, dan water resistant IP68.',
                    'price' => 349000,
                    'compare_price' => 549000,
                    'cost_price' => 150000,
                    'stock' => 30,
                    'sku' => 'ELEC-SW-002',
                    'weight' => 0.15,
                    'category_id' => $category->id,
                    'is_active' => true,
                    'featured' => true,
                ]
            ),
            Product::firstOrCreate(
                ['slug' => 'powerbank-10000mah'],
                [
                    'name' => 'Powerbank 10000mAh',
                    'description' => 'Power bank slim dengan dual USB output, fast charging 20W, dan LED indicator.',
                    'price' => 129000,
                    'compare_price' => 199000,
                    'cost_price' => 55000,
                    'stock' => 80,
                    'sku' => 'ELEC-PB-003',
                    'weight' => 0.25,
                    'category_id' => $category->id,
                    'is_active' => true,
                    'featured' => false,
                ]
            ),
            Product::firstOrCreate(
                ['slug' => 'kabel-type-c-fast-charging'],
                [
                    'name' => 'Kabel Type-C Fast Charging 2m',
                    'description' => 'Kabel data & charging Type-C to Type-C, braided nylon, support PD 60W fast charging.',
                    'price' => 59000,
                    'compare_price' => 89000,
                    'cost_price' => 20000,
                    'stock' => 120,
                    'sku' => 'ELEC-KBL-004',
                    'weight' => 0.05,
                    'category_id' => $category->id,
                    'is_active' => true,
                    'featured' => false,
                ]
            ),
            Product::firstOrCreate(
                ['slug' => 'wireless-charging-pad'],
                [
                    'name' => 'Wireless Charging Pad 15W',
                    'description' => 'Charger nirkabel Qi 15W, kompatibel dengan semua smartphone yang mendukung wireless charging.',
                    'price' => 149000,
                    'compare_price' => 249000,
                    'cost_price' => 60000,
                    'stock' => 40,
                    'sku' => 'ELEC-WC-005',
                    'weight' => 0.12,
                    'category_id' => $category->id,
                    'is_active' => true,
                    'featured' => false,
                ]
            ),
        ];

        $bundles = [
            [
                'name' => 'Paket Audio Essential',
                'slug' => 'paket-audio-essential',
                'description' => 'Paket lengkap untuk kebutuhan audio harian: TWS headphone + kabel charging berkualitas.',
                'products' => [
                    ['product_id' => $products[0]->id, 'quantity' => 1],
                    ['product_id' => $products[3]->id, 'quantity' => 1],
                ],
            ],
            [
                'name' => 'Paket Gadget Pro',
                'slug' => 'paket-gadget-pro',
                'description' => 'Bundle lengkap untuk produktivitas: smartwatch + powerbank + wireless charger. Cocok untuk profesional aktif.',
                'products' => [
                    ['product_id' => $products[1]->id, 'quantity' => 1],
                    ['product_id' => $products[2]->id, 'quantity' => 1],
                    ['product_id' => $products[4]->id, 'quantity' => 1],
                ],
            ],
            [
                'name' => 'Paket Charging Complete',
                'slug' => 'paket-charging-complete',
                'description' => 'Semua kebutuhan charging dalam satu paket: powerbank, kabel fast charging, dan wireless pad.',
                'products' => [
                    ['product_id' => $products[2]->id, 'quantity' => 1],
                    ['product_id' => $products[3]->id, 'quantity' => 2],
                    ['product_id' => $products[4]->id, 'quantity' => 1],
                ],
            ],
        ];

        foreach ($bundles as $bundleData) {
            $productsData = $bundleData['products'];
            unset($bundleData['products']);

            $totalOriginal = 0;
            foreach ($productsData as $pd) {
                $product = Product::find($pd['product_id']);
                if ($product) {
                    $totalOriginal += (float) $product->price * $pd['quantity'];
                }
            }

            $discount = match ($bundleData['slug']) {
                'paket-audio-essential' => 0.15,
                'paket-gadget-pro' => 0.20,
                'paket-charging-complete' => 0.18,
                default => 0.15,
            };

            $bundle = ProductBundle::firstOrCreate(
                ['slug' => $bundleData['slug']],
                array_merge($bundleData, [
                    'bundle_price' => round($totalOriginal * (1 - $discount)),
                    'total_original_price' => $totalOriginal,
                    'is_active' => true,
                ])
            );

            foreach ($productsData as $pd) {
                $bundle->products()->syncWithoutDetaching([
                    $pd['product_id'] => ['quantity' => $pd['quantity']],
                ]);
            }
        }
    }
}
