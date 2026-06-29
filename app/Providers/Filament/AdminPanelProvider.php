<?php

namespace App\Providers\Filament;

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
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
                NavigationGroup::make('Pengguna'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
        'Category': 'Atur kelompok kategori produk',
        'Order': 'Lihat & kelola pesanan pelanggan',
        'Payment': 'Konfirmasi & kelola pembayaran',
        'User': 'Kelola data pelanggan & staf',
        'Expense': 'Catat pengeluaran operasional toko',
        'Expense Category': 'Atur jenis-jenis kategori pengeluaran',
        'Banner': 'Atur banner promosi & pengumuman toko',
        'Review': 'Kelola ulasan produk dari pelanggan',
        'Tampilan': 'Atur tampilan & konten promosi toko',
        'Keuangan': 'Kelola keuangan & pengeluaran toko',
        'Pengaturan': 'Atur informasi toko, kontak & media sosial',
        'Pengaturan Toko': 'Atur informasi toko, kontak & media sosial',
        'Produk': 'Kelola produk & kategori toko',
        'Pesanan': 'Kelola semua pesanan & pembayaran',
        'Pengguna': 'Kelola data pengguna & staf toko',
    };
    function formatRupiah(el) {
        var val = el.value.replace(/[^0-9]/g, '');
        if (val === '') { el.value = ''; return; }
        var formatted = val.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        if (el.value !== formatted) {
            el.value = formatted;
        }
    }
    document.addEventListener('input', function(e) {
        var el = e.target;
        if (!el.matches('input.fi-input')) return;
        var model = el.getAttribute('wire:model') || '';
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
            });
    }
}
