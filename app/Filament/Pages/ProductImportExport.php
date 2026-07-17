<?php

namespace App\Filament\Pages;

use App\Models\Product;
use BackedEnum;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

class ProductImportExport extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|UnitEnum|null $navigationGroup = 'Produk';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Import / Export Produk';

    protected static ?string $slug = 'product-import-export';

    protected static ?string $navigationLabel = 'Import/Export';

    protected string $view = 'filament.pages.product-import-export';

    public ?string $importFile = null;

    public ?string $importError = null;

    public function handleImport(): void
    {
        $file = $this->importFile ?? null;

        if (! $file) {
            $this->importError = 'Pilih file CSV terlebih dahulu.';

            return;
        }

        $path = is_string($file) ? $file : ($file->getRealPath() ?? $file->getPathname());

        if (! $path || ! file_exists($path)) {
            $this->importError = 'File tidak ditemukan.';

            return;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->importError = 'Gagal membaca file.';

            return;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->importError = 'File kosong atau format salah.';

            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                if (count($row) < 2) {
                    $skipped++;
                    $errors[] = "Baris {$rowNum}: kolom kurang";

                    continue;
                }

                $data = array_combine(array_slice($header, 0, count($row)), $row);

                $name = trim($data['Nama'] ?? $data['name'] ?? '');
                if ($name === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNum}: nama kosong";

                    continue;
                }

                $slug = trim($data['Slug'] ?? $data['slug'] ?? '');
                if ($slug === '') {
                    $slug = Str::slug($name);
                }

                $productId = $data['ID'] ?? $data['id'] ?? null;

                $productData = [
                    'category_id' => $data['Kategori ID'] ?? $data['category_id'] ?? null,
                    'brand_id' => $data['Brand ID'] ?? $data['brand_id'] ?? null,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $data['Deskripsi'] ?? $data['description'] ?? null,
                    'price' => (float) ($data['Harga'] ?? $data['price'] ?? 0),
                    'compare_price' => (float) ($data['Harga Banding'] ?? $data['compare_price'] ?? 0) ?: null,
                    'cost_price' => (float) ($data['Harga Modal'] ?? $data['cost_price'] ?? 0) ?: null,
                    'stock' => (int) ($data['Stok'] ?? $data['stock'] ?? 0),
                    'sku' => $data['SKU'] ?? $data['sku'] ?? null,
                    'weight' => (float) ($data['Berat'] ?? $data['weight'] ?? 0),
                    'is_active' => in_array(($data['Aktif'] ?? $data['is_active'] ?? '1'), ['1', 'true', 'yes']),
                    'featured' => in_array(($data['Unggulan'] ?? $data['featured'] ?? '0'), ['1', 'true', 'yes']),
                    'is_digital' => in_array(($data['Digital'] ?? $data['is_digital'] ?? '0'), ['1', 'true', 'yes']),
                    'meta_title' => $data['Meta Title'] ?? $data['meta_title'] ?? null,
                    'meta_description' => $data['Meta Description'] ?? $data['meta_description'] ?? null,
                ];

                if ($productId && is_numeric($productId)) {
                    $existing = Product::find($productId);
                    if ($existing) {
                        $existing->update($productData);
                        $updated++;

                        continue;
                    }
                }

                $existingByName = Product::where('name', $name)->first();
                if ($existingByName) {
                    $existingByName->update($productData);
                    $updated++;

                    continue;
                }

                Product::create($productData);
                $created++;
            }

            fclose($handle);
            DB::commit();

            $this->importError = null;

            $message = "Import selesai: {$created} ditambahkan, {$updated} diperbarui, {$skipped} dilewati.";
            if (! empty($errors)) {
                $message .= ' Error: '.implode('; ', array_slice($errors, 0, 5));
            }

            FilamentNotification::make()
                ->title('Import Produk')
                ->body($message)
                ->success()
                ->send();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);

            $this->importError = 'Error: '.$e->getMessage();
        }
    }
}
