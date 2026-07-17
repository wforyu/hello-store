# Hello Store

Toko online pribadi ala Shopee — **Laravel 13**, Filament Admin, POS Kasir, Storefront publik, Mobile App.

## Fitur

- **Storefront** — Halaman publik: home, produk, detail produk, cart, checkout, wishlist, compare, product bundles
- **POS Kasir** — Split layout dengan produk grid + cart sidebar, diskon per-item & global, PPN dinamis, shift kasir, barcode scanner, hold/recall order
- **Admin Panel** — Filament dark mode (Amber), 23 resources, 15 dashboard widgets, reports export CSV, sidebar tooltips, help center
- **Mobile App** — React Native/Expo, auto-detect API URL dari admin panel, push notifications (Firebase)
- **Manajemen Stok** — Riwayat stok otomatis, stock opname, purchase order, retur supplier
- **Review & Rating** — Bintang 1-5 interaktif (Alpine.js), approve oleh admin
- **PPN Dinamis** — Bisa diaktifkan/dinonaktifkan, rate bisa diubah dari settings (default 11%)
- **Flash Sale** — Diskon waktu terbatas per produk dengan kuota stok
- **Product Bundle** — Paket produk dengan harga khusus, tersedia di web storefront & mobile app
- **Voucher/Kupon** — Persen atau nominal, min order, max diskon, usage limit
- **Points System** — 10% dari total pesanan, redeem max 50% total
- **Tracking Event** — Timeline status pengiriman per order
- **Digital Product** — Download file dengan limit 5x
- **Barcode** — Generate & print barcode (Code128/EAN13/QR)
- **Notifications** — Real-time notifikasi (Alpine.js unread count)
- **Wishlist** — Toggle wishlist + halaman khusus
- **Compare** — Session-based, max 4 produk
- **Banners & Promo** — Announcement bar + popup modal
- **Expense Tracking** — Catat pengeluaran toko + kategori
- **Shift Kasir** — Buka/tutup shift, kas keluar, history
- **3 Role User** — Admin, Cashier (POS only), Customer (storefront only)
- **Print Receipt** — Thermal 80mm untuk POS dan online orders
- **Auto-dot Formatting** — Input harga otomatis separator titik di admin & POS

## Requirements

- PHP ^8.3
- MySQL / MariaDB
- Redis (untuk queue & cache)
- Composer
- Node.js & NPM
- XAMPP (untuk local development)

## Instalasi

```bash
git clone https://github.com/wforyu/hello-store.git
cd hello-store
composer install
cp .env.example .env
php artisan key:generate
# Setup database di .env lalu:
php artisan migrate --seed
npm install && npm run build
php artisan storage:link
```

## Akses

| Role | URL | Email | Password |
|---|---|---|---|
| Admin | `/admin` | `admin@hello-store.test` | `password` |
| Cashier | `/pos` | `kasir@hello-store.test` | `password` |
| Customer | Storefront | `test@example.com` | `password` |

## Perintah Penting

| Perintah | Fungsi |
|---|---|
| `composer test` | `config:clear` lalu `php artisan test` (25 tests, 61 assertions) |
| `composer dev` | Jalankan server, queue, pail, dan Vite bersamaan |
| `composer setup` | Setup lengkap: install, .env, key:generate, migrate, npm install, vite build |
| `npm run build` | Build Vite production |
| `php artisan migrate:fresh --seed` | Reset DB + seed semua data |
| `vendor/bin/pint` | Format code (300 files, 0 issues) |
| `php artisan optimize` | Cache config, routes, views, events, blade-icons, filament |

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3
- **Admin Panel:** Filament 5.6.7 (Schema-based forms — `Filament\Schemas\Schema`)
- **Frontend:** Tailwind CSS v4, Alpine.js, Vite
- **Mobile App:** React Native / Expo SDK 57
- **Database:** MySQL / MariaDB
- **Cache & Queue:** Redis
- **Testing:** PHPUnit 12, Laravel Pint 1.29
- **Barcode:** milon/barcode

## Mobile App

React Native / Expo app di folder `mobile/`.

### Fitur Mobile

- Browse produk, detail, search, wishlist
- Cart & checkout dengan alamat & metode pembayaran
- Riwayat pesanan & detail pesanan
- Upload bukti bayar
- Bundle produk dengan harga khusus
- Push notifications (Firebase)
- Profil & pengaturan

### Auto-Detect API URL

Mobile app otomatis mendeteksi URL server dari admin panel. Saat admin mengubah `mobile_api_url` di Pengaturan Toko, mobile app akan switch ke URL baru otomatis — tanpa perlu rebuild APK.

**Flow:**
1. Admin mengatur `mobile_api_url` di Panel Admin → Pengaturan → Mobile App
2. Mobile app fetches `/api/config` saat startup
3. Jika server mengembalikan URL baru, app otomatis switch
4. Fallback ke ngrok URL jika unreachable

### Build APK

```bash
cd mobile/android
.\gradlew.bat assembleRelease -PreactNativeArchitectures=arm64-v8a
```

APK output: `mobile/android/app/build/outputs/apk/release/app-release.apk`

**PENTING:** Jangan jalankan `expo prebuild --clean` — akan menghapus splash screen fixes.

Lihat `mobile/AGENTS.md` untuk detail build rules.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3
- **Admin Panel:** Filament 5.6.7 (Schema-based forms — `Filament\Schemas\Schema`)
- **Frontend:** Tailwind CSS v4, Alpine.js, Vite
- **Database:** MySQL / MariaDB
- **Cache & Queue:** Redis
- **Testing:** PHPUnit 12, Laravel Pint 1.29
- **Barcode:** milon/barcode

## Bug Fixes (2026-07-15)

Total **30 bugs** telah diperbaiki dalam 3 ronde audit kode:
- **High**: Coupon `usage_per_user = null` bikin kupon gak bisa dipakai; IDOR vulnerability di mobile order; Logo/Favicon FileUpload tanpa `disk('public')`
- **Medium**: PPN DPP mismatch di mobile checkout; stock adjustment tanpa refresh; POS gak validasi `ppn_enabled` setting; flash sale filter gak diimplementasi
- **Low**: Shipping cost truncation; undefined `comment` key; hold ID logic; debounce memory leak

Lihat `AGENTS.md` untuk detail lengkap.
