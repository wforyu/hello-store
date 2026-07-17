<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        {{-- Export --}}
        <div style="background: var(--gray-800); border: 1px solid var(--gray-700); border-radius: 12px; padding: 24px;">
            <h3 style="font-size: 16px; font-weight: 700; color: var(--gray-100); margin-bottom: 8px;">Export Produk</h3>
            <p style="font-size: 13px; color: var(--gray-400); margin-bottom: 16px;">Download semua data produk dalam format CSV.</p>

            <div style="background: var(--gray-900); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <p style="font-size: 12px; color: var(--gray-400); margin: 0;">
                    Kolom yang di-export: ID, Kategori, Brand, Nama, Slug, Deskripsi, Harga, Harga Banding, Harga Modal, Stok, SKU, Berat, Aktif, Unggulan, Digital, Meta Title, Meta Description
                </p>
            </div>

            <x-filament::button
                tag="a"
                :href="route('admin.products.export')"
                icon="heroicon-o-arrow-down-tray"
                color="success"
                size="lg"
            >
                Download CSV
            </x-filament::button>
        </div>

        {{-- Import --}}
        <div style="background: var(--gray-800); border: 1px solid var(--gray-700); border-radius: 12px; padding: 24px;">
            <h3 style="font-size: 16px; font-weight: 700; color: var(--gray-100); margin-bottom: 8px;">Import Produk</h3>
            <p style="font-size: 13px; color: var(--gray-400); margin-bottom: 16px;">Upload CSV untuk menambah/update produk massal.</p>

            <div style="background: var(--gray-900); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <p style="font-size: 12px; color: var(--gray-400); margin: 0 0 6px 0;"><strong style="color: var(--gray-300);">Aturan import:</strong></p>
                <ul style="font-size: 12px; color: var(--gray-400); margin: 0; padding-left: 16px;">
                    <li>Header wajib: <code style="color: var(--primary-400);">Nama</code> (minimal)</li>
                    <li>Baris dengan ID akan <strong style="color: var(--gray-200);">update</strong> produk exist</li>
                    <li>Tanpa ID = <strong style="color: var(--gray-200);">create</strong> baru (berdasarkan nama duplikat = update)</li>
                    <li>Format harga: angka tanpa prefix (contoh: 50000)</li>
                    <li>Boolean: 1/true/yes = aktif, lainnya = nonaktif</li>
                </ul>
            </div>

            <form wire:submit="handleImport" style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: var(--gray-300); display: block; margin-bottom: 6px;">File CSV</label>
                    <input
                        type="file"
                        wire:model.live="importFile"
                        accept=".csv,text/csv,text/plain"
                        style="display: block; width: 100%; font-size: 13px; color: var(--gray-300); padding: 8px 12px; background: var(--gray-900); border: 1px solid var(--gray-600); border-radius: 8px;"
                    />
                    <p style="font-size: 11px; color: var(--gray-500); margin-top: 4px;">Maksimal 10MB. Format: CSV.</p>
                </div>

                <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" color="primary" size="lg">
                    Import CSV
                </x-filament::button>
            </form>

            @if($importError)
                <div style="margin-top: 12px; padding: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px;">
                    <p style="font-size: 13px; color: #ef4444; margin: 0;">{{ $importError }}</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
