# Hello Store 🛒

Toko online pribadi ala Shopee — **Laravel 13**, Filament Admin, POS Kasir, Storefront publik.

## Fitur

- **Storefront** — Halaman publik: home, produk, detail produk, cart, checkout
- **POS Kasir** — Split layout dengan produk grid + cart sidebar, diskon, PPN dinamis
- **Admin Panel** — Filament dark mode, manajemen produk/pesanan/pengguna/pembayaran
- **Manajemen Stok** — Riwayat stok otomatis, penyesuaian stok
- **Review & Rating** — Bintang 1-5, approve oleh admin
- **PPN Dinamis** — Bisa diaktifkan/dinonaktifkan, rate bisa diubah dari settings
- **Banners & Promo** — Announcement bar + popup
- **Expense Tracking** — Catat pengeluaran toko
- **3 Role User** — Admin, Cashier (POS only), Customer (storefront only)
- **Print Receipt** — Thermal 80mm untuk POS dan online orders

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

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3
- **Admin Panel:** Filament 5.6 (Schema-based forms)
- **Frontend:** Tailwind CSS v4, Alpine.js, Vite
- **Database:** MySQL / MariaDB
- **Cache & Queue:** Redis
- **Testing:** PHPUnit 12, Laravel Pint
