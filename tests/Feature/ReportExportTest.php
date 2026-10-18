<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /admin/reports/export is what the "Export CSV" button on the Reports page
 * hits. It streams from php://output, so a mistake here surfaces as a 500 on a
 * page the admin cannot debug.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    protected function paidOrder(string $number, int $total): Order
    {
        return Order::create([
            'user_id' => $this->admin->id,
            'order_number' => $number,
            'status' => 'completed',
            'subtotal' => $total,
            'shipping_cost' => 0,
            'total' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
    }

    public function test_export_csv_mengembalikan_baris_order(): void
    {
        $this->paidOrder('POS-AAA111', 15000);
        $this->paidOrder('POS-BBB222', 25000);

        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/export?start='.now()->startOfMonth()->format('Y-m-d').'&end='.now()->format('Y-m-d').'&format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();

        $this->assertStringContainsString('No. Pesanan', $body);
        $this->assertStringContainsString('POS-AAA111', $body);
        $this->assertStringContainsString('POS-BBB222', $body);
    }

    /**
     * Keluhan sebenarnya: "Export CSV isinya bukan laporan". File harus memuat
     * keempat bagian yang ada di layar, baru detail pesanan di bawahnya.
     */
    public function test_export_csv_berisi_semua_bagian_laporan(): void
    {
        $this->paidOrder('POS-SEC001', 30000);

        $body = $this->actingAs($this->admin)
            ->get('/admin/reports/export?start='.now()->startOfMonth()->format('Y-m-d').'&end='.now()->format('Y-m-d'))
            ->assertOk()
            ->streamedContent();

        foreach (['Ringkasan Penjualan', 'Laba / Rugi', 'Produk Terlaris', 'Kategori Terlaris', 'Detail Pesanan'] as $section) {
            $this->assertStringContainsString($section, $body, "Bagian \"{$section}\" hilang dari CSV.");
        }

        $this->assertStringContainsString('Total Pendapatan', $body);
        $this->assertStringContainsString('Laba Bersih', $body);
    }

    /**
     * CSV dan halaman WAJIB memakai angka yang sama. Kalau perhitungannya
     * ditulis dua kali, cepat atau lambat keduanya melenceng — dan admin
     * menganggap export-nya rusak.
     */
    public function test_angka_csv_sama_dengan_angka_halaman(): void
    {
        $this->paidOrder('POS-AGREE1', 40000);

        $query = 'start='.now()->startOfMonth()->format('Y-m-d').'&end='.now()->format('Y-m-d');

        $html = $this->actingAs($this->admin)->get('/admin/reports?'.$query)->assertOk()->getContent();
        $csv = $this->actingAs($this->admin)->get('/admin/reports/export?'.$query)->assertOk()->streamedContent();

        // Halaman menampilkan "Rp 40.000"; CSV menulis angka yang sama tanpa "Rp".
        $this->assertStringContainsString('Rp 40.000', $html);

        // Diurai jadi baris agar tahan terhadap aturan quoting fputcsv.
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n/', trim($csv)));

        $this->assertContains(['Total Pendapatan', '40.000'], $rows);
        $this->assertContains(['Laba Bersih', '40.000'], $rows);
    }

    /** Detail pesanan juga tampil di halaman supaya layar = file. */
    public function test_halaman_laporan_menampilkan_detail_pesanan(): void
    {
        $this->paidOrder('POS-DETAIL1', 31000);

        $this->actingAs($this->admin)
            ->get('/admin/reports?start='.now()->startOfMonth()->format('Y-m-d').'&end='.now()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Detail Pesanan')
            ->assertSee('POS-DETAIL1');
    }

    public function test_export_csv_tanpa_parameter_tanggal(): void
    {
        $this->paidOrder('POS-CCC333', 10000);

        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/export')
            ->assertOk();

        $this->assertStringContainsString('POS-CCC333', $response->streamedContent());
    }

    public function test_export_csv_menukar_tanggal_terbalik(): void
    {
        $order = $this->paidOrder('POS-SWAP01', 7000);
        $order->forceFill(['created_at' => '2026-10-18 10:00:00'])->save();

        // end < start tidak boleh jadi 500 atau halaman error tanpa pesan;
        // nilai ditukar supaya admin tetap dapat rentang yang dia maksud.
        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/export?start=2026-10-18&end=2026-10-01')
            ->assertOk();

        $this->assertStringContainsString('POS-SWAP01', $response->streamedContent());
    }

    public function test_export_csv_menolak_non_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin/reports/export')
            ->assertForbidden();
    }

    public function test_export_csv_tidak_mengirim_order_belum_dibayar(): void
    {
        $this->paidOrder('POS-PAID99', 5000);

        Order::create([
            'user_id' => $this->admin->id,
            'order_number' => 'POS-UNPAID',
            'status' => 'pending',
            'subtotal' => 9999,
            'shipping_cost' => 0,
            'total' => 9999,
            'payment_method' => 'manual_transfer',
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/reports/export')->assertOk();

        $body = $response->streamedContent();

        $this->assertStringContainsString('POS-PAID99', $body);
        $this->assertStringNotContainsString('POS-UNPAID', $body);
    }

    /** Produk tanpa harga modal tidak boleh membuat COGS hilang/error. */
    public function test_halaman_laporan_menampilkan_cogs(): void
    {
        $category = Category::firstOrCreate(['slug' => 'report-test'], ['name' => 'Report Test']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Laporan',
            'slug' => Str::slug('Produk Laporan'),
            'price' => 10000,
            'cost_price' => 4000,
            'stock' => 10,
            'sku' => 'RPT-001',
            'is_active' => true,
        ]);

        $order = $this->paidOrder('POS-RPT001', 20000);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 10000,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Rp 8.000', false);
    }

    /**
     * Tombol Print memanggil window.print(). Tanpa aturan @media print, yang
     * tercetak adalah seluruh kerangka panel (topbar, sidebar, notifikasi),
     * bukan isi laporan — jadi blok CSS itu harus tetap ada.
     */
    public function test_halaman_laporan_menyembunyikan_kerangka_panel_saat_dicetak(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports')->assertOk();

        $response->assertSee('@media print', false);
        $response->assertSee('hs-report-doc', false);
        $response->assertSee('hs-no-print', false);
        $response->assertSee('.fi-sidebar', false);
        $response->assertSee('.fi-topbar-ctn', false);
    }

    /**
     * Regression "cetak kosong": `.fi-page-header-main-ctn` terdengar seperti
     * baris judul, padahal dia wadah flex yang MEMBUNGKUS `.fi-page-main`.
     * `display: none` di situ menghapus seluruh isi halaman — kertas keluar
     * kosong, dan itu terjadi walau warna teksnya sudah diperbaiki.
     */
    public function test_css_cetak_tidak_menyembunyikan_wadah_isi_halaman(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->getContent();

        preg_match('/\.fi-topbar-ctn[^{]*\{[^}]*display:\s*none/i', $html, $matches);

        $this->assertNotEmpty($matches, 'Aturan penyembunyian kerangka panel tidak ditemukan.');

        $this->assertStringNotContainsString(
            '.fi-page-header-main-ctn',
            $matches[0],
            '`.fi-page-header-main-ctn` membungkus isi halaman — menyembunyikannya bikin hasil cetak kosong.'
        );
    }

    /**
     * Regression: Livewire menempelkan `wire:snapshot` ke elemen PERTAMA yang
     * dirender. Kalau `<style>` jadi elemen pertama, dia yang jadi root
     * komponen dan isi halaman hanya jadi saudaranya — filter periode berhenti
     * me-render ulang apa pun.
     */
    public function test_blok_style_berada_di_dalam_root_komponen(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->getContent();

        $pos = strpos($html, 'wire:snapshot');
        $this->assertNotFalse($pos, 'Atribut wire:snapshot tidak ditemukan di halaman.');

        $tagStart = strrpos(substr($html, 0, $pos), '<');
        $this->assertNotFalse($tagStart);

        preg_match('/^<([a-zA-Z]+)/', substr($html, $tagStart), $matches);

        $this->assertNotSame(
            'style',
            strtolower($matches[1] ?? ''),
            'Blok <style> tidak boleh menjadi root komponen Livewire.'
        );
    }

    /** Isi laporan harus berada di dalam wadah yang dicetak. */
    public function test_isi_laporan_berada_di_dalam_wadah_cetak(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->getContent();

        $docStart = strpos($html, 'hs-report-doc');
        $this->assertNotFalse($docStart);

        // Judul cetak dan tabel terlaris harus berada setelah pembuka wadah.
        $this->assertStringContainsString('hs-print-title', $html);
        $this->assertStringContainsString('Produk Terlaris', $html);
    }

    public function test_tautan_export_tidak_ter_escape_dua_kali(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->getContent();

        // `&` di query string boleh ter-escape SEKALI oleh Blade (&amp;) — itu
        // yang di-decode browser jadi `&`. Tapi `&amp;amp;` bikin browser
        // mengirim parameter bernama "amp;end"/"amp;format", `end` hilang, dan
        // ReportController melempar "Undefined array key" alias HTTP 500.
        $this->assertStringContainsString('admin/reports/export?start=', $html);
        $this->assertStringContainsString('&amp;end=', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * Regression: URL hasil render harus benar-benar dipakai browser. Simulasi
     * decode HTML mengubah `&amp;` -> `&`, lalu request itu harus 200.
     */
    public function test_tautan_export_hasil_decode_html_tetap_berfungsi(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->getContent();

        preg_match('/href="([^"]*admin\/reports\/export[^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches, 'Tautan export tidak ditemukan di halaman laporan.');

        $url = html_entity_decode($matches[1], ENT_QUOTES);

        $this->assertStringContainsString('&end=', $url);
        $this->assertStringNotContainsString('amp;', $url);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }
}
