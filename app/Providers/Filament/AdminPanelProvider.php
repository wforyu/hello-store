<?php

namespace App\Providers\Filament;

use App\Filament\Pages\HelpCenter;
use App\Filament\Pages\Reports;
use App\Filament\Widgets\ActivityTimelineWidget;
use App\Filament\Widgets\CustomerSegmentationWidget;
use App\Filament\Widgets\EnhancedStatsOverviewWidget;
use App\Filament\Widgets\FinanceOverview;
use App\Filament\Widgets\ProductAnalyticsWidget;
use App\Filament\Widgets\PurchaseAnalyticsWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\SalesComparisonWidget;
use App\Filament\Widgets\SalesTargetWidget;
use App\Filament\Widgets\StorePerformanceWidget;
use App\Filament\Widgets\TopCashiersTableWidget;
use App\Filament\Widgets\TopCategoriesTableWidget;
use App\Filament\Widgets\TopProductsTableWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->darkMode(true)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName('Hello Store')
            ->favicon(asset('favicon.svg'))
            ->navigationGroups([
                NavigationGroup::make('Tampilan'),
                NavigationGroup::make('Keuangan'),
                NavigationGroup::make('Pengaturan'),
                NavigationGroup::make('Produk'),
                NavigationGroup::make('Pesanan'),
                NavigationGroup::make('Persediaan'),
                NavigationGroup::make('Pemasaran'),
                NavigationGroup::make('Pengguna'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                Reports::class,
                HelpCenter::class,
            ])
            ->widgets([
                EnhancedStatsOverviewWidget::class,
                SalesComparisonWidget::class,
                FinanceOverview::class,
                PurchaseAnalyticsWidget::class,
                RevenueChart::class,
                RevenueChartWidget::class,
                TopProductsTableWidget::class,
                TopCategoriesTableWidget::class,
                TopCashiersTableWidget::class,
                ProductAnalyticsWidget::class,
                StorePerformanceWidget::class,
                CustomerSegmentationWidget::class,
                SalesTargetWidget::class,
                RecentOrdersWidget::class,
                ActivityTimelineWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(PanelsRenderHook::BODY_END, function (): string {
                return <<<'HTML'
<script>
(function() {
    var tips = {
        'Dashboard': 'Ikhtisar penjualan & statistik toko',
        'Product': 'Kelola daftar produk toko',
        'Brand': 'Kelola brand / merek produk',
        'Category': 'Atur kelompok kategori produk',
        'Order': 'Lihat & kelola pesanan pelanggan',
        'Payment': 'Konfirmasi & kelola pembayaran',
        'User': 'Kelola data pelanggan & staf',
        'Expense': 'Catat pengeluaran operasional toko',
        'Expense Category': 'Atur jenis-jenis kategori pengeluaran',
        'Banner': 'Atur banner promosi & pengumuman toko',
        'Review': 'Kelola ulasan produk dari pelanggan',
        'Coupon': 'Kelola kupon & voucher diskon',
        'Tampilan': 'Atur tampilan & konten promosi toko',
        'Keuangan': 'Kelola keuangan & pengeluaran toko',
        'Pengaturan': 'Atur informasi toko, kontak & media sosial',
        'Pengaturan Toko': 'Atur informasi toko, kontak & media sosial',
        'Produk': 'Kelola produk, brand & kategori toko',
        'Pesanan': 'Kelola semua pesanan & pembayaran',
        'Supplier': 'Kelola data pemasok / supplier produk',
        'Rantai Pasok': 'Kelola supplier, PO, stok opname & retur',
        'Purchase Order': 'Kelola pesanan pembelian ke supplier',
        'Stock Opname': 'Kelola stok opname & penyesuaian stok',
        'Purchase Return': 'Kelola retur barang ke supplier',
        'Flash Sale': 'Atur promo flash sale diskon waktu terbatas',
        'Product Bundle': 'Kelola bundle produk dengan harga spesial',
        'Point Transaction': 'Riwayat transaksi poin pelanggan',
        'Pengguna': 'Kelola data pengguna & staf toko',
        'Sliders': 'Kelola slider / carousel beranda toko',
        'Sales Target': 'Atur target penjualan & pantau pencapaian',
        'Social Follow Claim': 'Kelola klaim follow media sosial dari pelanggan',
        'Persediaan': 'Kelola stok barang, supplier & pembelian',
        'Pemasaran': 'Atur flash sale, bundle & promo toko',
        'Kalender Promosi': 'Lihat jadwal promo & flash sale dalam kalender',
        'Import Export': 'Impor & ekspor data produk, pesanan & pelanggan',
        'Laporan': 'Lihat laporan penjualan, laba rugi & analitik',
        'Pusat Bantuan': 'Panduan lengkap penggunaan admin panel',
        'Audit Log': 'Riwayat aktivitas admin & perubahan data',
    };
    function formatRupiah(el) {
        var val = el.value.replace(/[^0-9]/g, '');
        if (val === '') { el.value = ''; return; }
        var formatted = val.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        if (el.value !== formatted) {
            el.value = formatted;
        }
    }
    window.formatRupiah = formatRupiah;
    document.addEventListener('input', function(e) {
        var el = e.target;
        if (!el.matches('input.fi-input')) return;
        var model = el.getAttribute('wire:model') || el.getAttribute('wire:model.blur') || el.getAttribute('wire:model.live') || '';
        if (!/price|subtotal|shipping|total|amount/i.test(model)) return;
        formatRupiah(el);
    }, true);
    function applyTips() {
        document.querySelectorAll('.fi-sidebar-item-label, .fi-sidebar-group-label').forEach(function(el) {
            var key = el.textContent.trim();
            if (tips[key]) {
                var parent = el.closest('a, button, .fi-sidebar-group-btn, .fi-sidebar-item-btn');
                if (parent) parent.setAttribute('title', tips[key]);
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTips);
    } else {
        applyTips();
    }
    setTimeout(applyTips, 500);
    setTimeout(applyTips, 1500);
    var obs = new MutationObserver(applyTips);
    obs.observe(document.body, { childList: true, subtree: true });
})();
</script>
HTML;
            })
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, function (): string {
                return View::make('filament.notification-bell')->render();
            });
    }
}
