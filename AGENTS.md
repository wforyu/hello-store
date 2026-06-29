# AGENTS.md — Hello Store

## Informasi Proyek

**Hello Store** adalah aplikasi toko online pribadi ala Shopee dengan Laravel 13, PHP ^8.3, MySQL via XAMPP (MariaDB 10.4.32). Database `hello_store_db`, user `root` tanpa password. Tailwind CSS v4 + Alpine.js + Vite. Laravel Breeze untuk scaffolding auth. PHPUnit 12, Laravel Pint 1.29. Filament 5.6.7 untuk admin panel (Schema-based form — BUKAN `Filament\Forms\Form`).

### Arsitektur & Alur
- **Session-based cart**: Tidak perlu login untuk browsing + cart; produk bisa ditambahkan sebagai guest
- **Three-role system**: `admin` (semua akses), `cashier` (POS only), `customer` (storefront only)
- **POS**: Split layout — grid produk kiri + sidebar cart 420px kanan; dibuat dengan Alpine.js
- **Storefront**: Layout publik dengan navbar, search suggestions, cart badge, user dropdown (Alpine.js, bukan group-hover agar works di touch)
- **Admin Panel**: Filament dark mode, primary Amber, semua label Bahasa Indonesia, navigation groups (Tampilan, Keuangan, Pengaturan, Produk, Pesanan, Pengguna)
- **Flow pesanan**: Pending → upload payment → auto Processing → admin set Shipped → customer klik "Pesanan Diterima" → Delivered
- **PPN**: Bisa diaktifkan/dinonaktifkan dari admin settings; rate bisa diubah (default 11%); berlaku di POS (checkbox toggle per transaksi) dan storefront (otomatis)

---

## Perintah Penting

| Perintah | Fungsi |
|---|---|
| `composer test` | `config:clear` lalu `php artisan test` (Unit + Feature) |
| `composer dev` | Jalankan server, queue, pail, dan Vite secara bersamaan |
| `composer setup` | Setup lengkap: install, .env, key:generate, migrate, npm install, vite build |
| `npm run build` | Build Vite production |
| `php artisan migrate` | Jalankan migrations |
| `php artisan migrate:fresh --seed` | Reset DB + seed (categories, products, users, expense categories, settings, PPN defaults) |
| `vendor/bin/pint` | Format code dengan Laravel Pint |
| `php artisan test --filter test_name` | Jalankan test tertentu |
| `php artisan make:filament-resource ModelName --generate` | Buat Filament resource |

### Akses

| Role | URL | Email | Password |
|---|---|---|---|
| Admin | `/admin` | `admin@hello-store.test` | `password` |
| Cashier | `/pos` | `kasir@hello-store.test` | `password` |
| Customer | Storefront | `test@example.com` | `password` |

---

## Daftar Routes

### Storefront (Publik — tanpa middleware)
| Method | URI | Controller@method | Nama |
|---|---|---|---|
| GET | `/` | `StoreController@home` | `home` |
| GET | `/products` | `StoreController@products` | `products.index` |
| GET | `/product/{slug}` | `StoreController@productDetail` | `products.show` |
| GET | `/products/suggestions` | `StoreController@suggestions` | `products.suggestions` |
| GET | `/cart` | `StoreController@cartIndex` | `cart.index` |
| POST | `/cart/add/{product}` | `StoreController@cartAdd` | `cart.add` |
| POST | `/cart/update` | `StoreController@cartUpdate` | `cart.update` |
| POST | `/cart/remove/{productId}` | `StoreController@cartRemove` | `cart.remove` |

### Auth (middleware: `auth`)
| Method | URI | Controller@method | Nama |
|---|---|---|---|
| GET | `/account` | `AccountController@dashboard` | `account.dashboard` |
| GET | `/checkout` | `StoreController@checkout` | `checkout` |
| POST | `/checkout/place-order` | `StoreController@placeOrder` | `checkout.place` |
| GET | `/orders` | `StoreController@orders` | `orders.index` |
| GET | `/orders/{order}` | `StoreController@orderShow` | `orders.show` |
| POST | `/orders/{order}/payment` | `StoreController@paymentUpload` | `orders.payment` |
| POST | `/orders/{order}/confirm-received` | `StoreController@confirmReceived` | `orders.confirm-received` |
| POST | `/orders/{order}/cancel` | `StoreController@cancelOrder` | `orders.cancel` |
| GET | `/orders/{order}/print` | `StoreController@printReceipt` | `orders.print` |
| POST | `/product/{product}/review` | `StoreController@reviewStore` | `products.review` |
| GET/PATCH/DELETE | `/profile` | `ProfileController` | `profile.*` |
| Resource | `/addresses` (exc. show) | `AddressController` | `addresses.*` |

### POS (middleware: `auth`, `can:access-pos`)
| Method | URI | Controller@method | Nama |
|---|---|---|---|
| GET | `/pos` | `PosController@index` | `pos.index` |
| GET | `/pos/search` | `PosController@search` | `pos.search` |
| GET | `/pos/customers` | `PosController@customers` | `pos.customers` |
| POST | `/pos/add` | `PosController@add` | `pos.add` |
| POST | `/pos/update` | `PosController@update` | `pos.update` |
| POST | `/pos/remove` | `PosController@remove` | `pos.remove` |
| POST | `/pos/checkout` | `PosController@checkout` | `pos.checkout` |
| POST | `/pos/hold` | `PosController@holdOrder` | `pos.hold` |
| GET | `/pos/holds` | `PosController@recallOrders` | `pos.holds` |
| GET | `/pos/hold/{id}` | `PosController@recallOrder` | `pos.recall` |
| DELETE | `/pos/hold/{id}` | `PosController@deleteHold` | `pos.hold-delete` |
| GET | `/pos/history` | `PosController@history` | `pos.history` |
| GET | `/pos/print/{order}` | `PosController@printReceipt` | `pos.print` |
| GET | `/orders/{order}/print-admin` | `StoreController@printReceiptAdmin` | `orders.print-admin` |

---

## Database — 25 Migrations

| # | File | Fungsi |
|---|---|---|
| 1-3 | Laravel defaults | users, cache, jobs |
| 4 | `create_categories_table` | Categories (parent_id, slug, is_active, sort_order) |
| 5 | `create_products_table` | Products (category_id, name, slug, price, compare_price, stock, sku, weight, images JSON, is_active, featured, meta fields) |
| 6 | `create_carts_table` | Cart session/user-based |
| 7 | `create_cart_items_table` | Cart items |
| 8 | `create_addresses_table` | Alamat user (is_default) |
| 9 | `create_orders_table` | Orders (status, subtotal, shipping_cost, total, payment_method, payment_status, shipping fields, address_id, timestamps + shipped_at/delivered_at/cancelled_at) |
| 10 | `create_order_items_table` | Order items (product_id, name, price, qty, subtotal) |
| 11 | `create_payments_table` | Payments (method, amount, status, proof_image, bank fields, paid_at) |
| 12 | `add_role_to_users_table` | Tambah kolom role (admin/cashier/customer) |
| 13 | `create_expense_categories_table` | Kategori pengeluaran |
| 14 | `create_expenses_table` | Pengeluaran (amount, description, date, FK ke expense_category + user) |
| 15 | `create_banners_table` | Banners (title, description, image_url, link_url, type, start_at, end_at, is_active, sort_order) |
| 16 | `create_settings_table` | Key-value store settings |
| 17 | `create_reviews_table` | Reviews (rating 1-5, comment, is_approved, unique product+user) |
| 18 | `add_seo_fields_to_products_table` | meta_title (varchar 70), meta_description (varchar 160) |
| 19 | `add_soft_deletes_to_products_table` | Soft delete products |
| 20 | `add_soft_deletes_to_banners_table` | Soft delete banners |
| 21 | `add_soft_deletes_to_categories_table` | Soft delete categories |
| 22 | `create_product_images_table` | Product images gallery (product_id, image, sort_order) |
| 23 | `migrate_product_images_data` | Migrasi JSON images → product_images, drop kolom images |
| 24 | `create_stock_histories_table` | Stock history (product_id, user_id, type, reference, quantity_change, stock_before, stock_after, notes) |
| 25 | `add_images_json_to_products_table` | Tambah kolom images JSON kembali (untuk Filament FileUpload compatibility) |

---

## Models (15 total)

| Model | File | Fillable | Casts | Relasi Utama |
|---|---|---|---|---|
| **User** | `Models/User.php` | name, email, password, role | `password => hashed` | reviews() |
| **Product** | `Models/Product.php` | category_id, name, slug, description, price, compare_price, stock, sku, weight, images, is_active, featured, meta_title, meta_description | `price/compare_price/weight => decimal:2`, `images => array`, `is_active/featured => boolean` | category(), productImages(), stockHistories(), reviews(), approvedReviews(); `booted` saved event: sync images JSON → ProductImage records + auto-log stock change |
| **ProductImage** | `Models/ProductImage.php` | product_id, image, sort_order | — | belongsTo(Product); `$appends=['url']` dengan getUrlAttribute (relative path → Storage::url(), absolute → pass through) |
| **StockHistory** | `Models/StockHistory.php` | product_id, user_id, type, reference_type, reference_id, quantity_change, stock_before, stock_after, notes | — | belongsTo(Product), belongsTo(User) |
| **Category** | `Models/Category.php` | parent_id, name, slug, description, image, is_active, sort_order | — | parent(), children(), products(); SoftDeletes |
| **Order** | `Models/Order.php` | user_id, order_number, status, subtotal, shipping_cost, total, payment_method, payment_status, notes, admin_notes, address_id, shipping_courier, shipping_tracking_number, shipped_at, delivered_at, cancelled_at | `subtotal/shipping_cost/total => decimal:2`, `shipped_at/delivered_at/cancelled_at => datetime` | user(), items(), payment(), address() |
| **OrderItem** | `Models/OrderItem.php` | order_id, product_id, product_name, product_price, quantity, subtotal | `product_price/subtotal => decimal:2` | order(), product() |
| **Payment** | `Models/Payment.php` | order_id, method, amount, status, proof_image, bank_name, account_name, account_number, paid_at, notes | `amount => decimal:2`, `paid_at => datetime` | belongsTo(Order); `$appends=['proof_image_url']` |
| **Address** | `Models/Address.php` | user_id, label, recipient, phone, street, city, province, postal_code, notes, is_default | `is_default => boolean` | belongsTo(User) |
| **Cart** | `Models/Cart.php` | user_id, session_id | — | user(), items() |
| **CartItem** | `Models/CartItem.php` | cart_id, product_id, quantity, price | — | cart(), product() |
| **Review** | `Models/Review.php` | product_id, user_id, rating, comment, is_approved | — | belongsTo(Product), belongsTo(User) |
| **Banner** | `Models/Banner.php` | title, description, image_url, link_url, link_text, type, start_at, end_at, is_active, sort_order | `start_at/end_at => datetime`, `is_active => boolean` | scopeActive(); SoftDeletes |
| **Setting** | `Models/Setting.php` | key, value | — | Static `get($key, $default)` — bank_accounts return `[]` never null; booleans stored as `"1"`/`"0"` |
| **Expense** | `Models/Expense.php` | expense_category_id, amount, description, user_id, date | `amount => decimal:2`, `date => date` | belongsTo(ExpenseCategory), belongsTo(User) |
| **ExpenseCategory** | `Models/ExpenseCategory.php` | name, slug, description | — | hasMany(Expense) |

---

## Controllers (6 custom + Auth bawaan)

### StoreController (`app/Http/Controllers/StoreController.php`)
- **home()**: Ambil categories tree, featured + latest products (8 each) with avg rating + count
- **products()**: Filter by search + category + sort (terbaru/termurah/termahal/nama), pagination 12
- **productDetail()**: Product with images, related products (same category, 4 items), reviews, user review status, recently viewed session tracking
- **suggestions()**: JSON search — match name/SKU, min 2 chars, max 6 results
- **cartIndex()**: Tampilkan session cart
- **cartAdd()**: Validasi stock >= 1, add/update cart item, cap by stock
- **cartUpdate()**: Batch update quantities dengan live stock check (N+1 safe via `whereIn`)
- **cartRemove()**: Remove item dari cart
- **checkout()**: Cart validation, address selection, shipping rates (RajaOngkir via ShippingService), subtotal, weight, **PPN (dari setting)**
- **placeOrder()**: Validasi stock dengan N+1 fix (`$liveProducts`), DB transaction: **subtotal + ongkir + PPN = total**, create Order + OrderItems + Payment (manual_transfer), decrement stock, recordStockHistory, clear cart; PPN disimpan di notes
- **orders()**: User's orders dengan eager load items+payment+address
- **orderShow()**: Single order detail (authorization check user_id)
- **paymentUpload()**: Upload proof image, auto set paid+processing, delete old proof file sebelum upload baru
- **confirmReceived()**: Customer confirms delivery (only if status shipped → delivered + delivered_at)
- **cancelOrder()**: Only pending orders, DB transaction restore stock + recordStockHistory; eager load items.product
- **printReceipt()**: Thermal 80mm receipt
- **printReceiptAdmin()**: Admin receipt print (tanpa auth check)
- **reviewStore()**: Upsert review (create or update if exists)
- **getCartWeight()**: Hitung total weight untuk shipping

### PosController (`app/Http/Controllers/PosController.php`)
- **index()**: Load all active products, categories, customers, **PPN rate dari setting**
- **search()**: Filter produk by name/SKU/category
- **customers()**: Search customers by name
- **add()**: Add to POS cart, validasi stock >= 1
- **update()**: Update quantity + discount per item dengan live stock
- **remove()**: Remove item dari POS cart
- **checkout()**: Hitung item discount, global discount, **PPN dinamis (dari setting)**, total; validasi amount_paid untuk cash; DB transaction create Order (status=completed) + OrderItems + Payment, decrement stock, recordStockHistory; clear cart; PPN disimpan di notes
- **holdOrder()**: Hold cart (simpan di session)
- **recallOrders()**: List all held orders
- **recallOrder()**: Recall held order ke cart
- **deleteHold()**: Delete held order
- **history()**: Today's completed orders (return JSON dengan parsed customer name dari notes)
- **printReceipt()**: Thermal receipt view

### AccountController (`app/Http/Controllers/AccountController.php`)
- **dashboard()**: Stat cards (total orders, addresses, reviews) + recent orders

### AddressController (`app/Http/Controllers/AddressController.php`)
- Full resource (except show): CRUD alamat pengiriman

### ProfileController (`app/Http/Controllers/ProfileController.php`)
- **edit()/update()/destroy()**: Manage user profile (Breeze default)

### Auth Controllers (`app/Http/Controllers/Auth/`)
- Login, Register, Password Reset, Email Verification, Logout (Breeze scaffolding + custom layout/storefront integration)

---

## Filament Admin Panel

### Konfigurasi (`AdminPanelProvider.php`)
- Dark mode enabled, Primary: Amber
- Brand: "Hello Store" dengan favicon SVG
- Navigation groups: Tampilan, Keuangan, Pengaturan, Produk, Pesanan, Pengguna
- Tooltip helper script untuk sidebar items
- Widgets auto-discovered
- **`formatRupiah` JS** — IIFE mendefinisikan fungsi global + capture-phase `document.addEventListener('input', ...)` untuk auto-dot formatting pada input dengan `wire:model` mengandung `price|subtotal|shipping|total|amount` (menggunakan `.fi-input` selector). Dipanggil via event delegation, bukan `extraInputAttributes`.

### Resources (10)

| Group | Resource | Model | Icon |
|---|---|---|---|
| Tampilan | Banners | Banner | `Photo` |
| Keuangan | Expenses | Expense | `Banknotes` |
| Keuangan | Expense Categories | ExpenseCategory | `Tag` |
| Pengaturan | Pengaturan Toko (Page) | Setting | `Cog6Tooth` |
| Produk | Products | Product | `ShoppingBag` |
| Produk | Categories | Category | `RectangleGroup` |
| Produk | Riwayat Stok | StockHistory | `ClipboardDocumentList` |
| Pesanan | Orders | Order | `Truck` |
| Pesanan | Payments | Payment | `CreditCard` |
| Pengguna | Users | User | `Users` |

### Widgets (5)

| Widget | Sort | Fungsi |
|---|---|---|
| **FinanceOverview** | 1 | 4 stat cards: Total Pendapatan, Total Pengeluaran, Laba Bersih, Total Pesanan |
| **RevenueChart** | 2 | Line chart pendapatan 6 bulan terakhir (bukan 30 hari, per bulan) |
| **StatsOverviewWidget** | 1 | 4 stat cards: Pesanan Hari Ini, **Pendapatan Bulan Ini**, Pesanan Menunggu, Stok Menipis — semuanya **clickable** via `->url()` menuju resource page dengan filter |
| **RevenueChartWidget** | 2 | Line chart pendapatan 30 hari terakhir |
| **RecentOrdersWidget** | 3 | Table 10 pesanan terakhir dengan status badges; full column span |

### Custom Table Filters
- **ProductsTable**: `SelectFilter::make('stock')` — "Stok Menipis (≤ 5)" dan "Habis (0)" dengan custom `query()` callback
- **OrdersTable**: `Filter::make('hari_ini')` (`whereDate('created_at', today())`) dan `Filter::make('menunggu')` (`whereIn('status', ['pending','processing'])`) — toggle filter

### Catatan Penting Filament
- `form()` menerima `Schema $schema`, mengembalikan `Schema $schema` — BUKAN `Form $form`
- Import: `Filament\Schemas\Schema`, `Filament\Schemas\Components\Section`, `Filament\Forms\Components\*`
- `->statePath('data')` pada Schema root + `public ?array $data = []` pada page
- Custom page view: `protected string $view` + `<form wire:submit="handler">` (BUKAN `<x-filament-panels::form>`)
- `->money('IDR')` hanya pada `TextColumn` (tables), BUKAN `TextInput`
- `formatStateUsing` truthy check: `$state !== null && $state !== '' ? ... : ''` (jangan `$state ? ... : ''` karena `0` falsy)
- Order form: `status`, `payment_method`, `payment_status` pakai **Select dropdowns**
- Product form: `FileUpload::make('images')->multiple()` langsung (JANGAN di dalam Repeater — Repeater+FileUpload+relationship bug di Filament 5.6)
- Semua label Bahasa Indonesia, semua field punya `->helperText()`
- Untuk non-input element price display di form (read-only): pakai `Placeholder::make()` + `<img>` tag dengan `HtmlString`, BUKAN `FileUpload::make()->disabled()`

---

## POS Kasir

### Controller: `PosController`
- Produk search by name/SKU/category
- Cart CRUD dengan stock cap per item
- Discount support per-item (% atau Rp) + global discount
- **PPN dinamis dari settings** (dulu hardcoded 11%)
- Checkout dalam DB transaction: decrement stock, create Order (status=completed) + Items + Payment
- Hold/Recall orders (session-based)
- History today's completed orders
- Print receipt thermal 80mm

### View: `resources/views/pos/index.blade.php`
- Alpine.js split layout: product grid (kiri) + 420px cart sidebar (kanan)
- Category pills filter
- Debounce search
- Quantity stepper tombol +/-
- Customer name + customer search
- Discount toggle (% / Rp)
- Quick amount buttons (50k, 100k, 200k, 500k, 1jt)
- Keyboard shortcuts: Enter → checkout, Esc → reset
- Loading state
- Stock warning: merah kalau ≤ 5
- Change/kurang display setelah checkout
- Success state dengan tombol print
- **Auto-dot formatting**: `onAmountInput` (jumlah dibayar), `formatDiscount` (global diskon — nominal mode only), `updateItemDiscount` (per-item diskon — nominal mode only) — pakai regex `\B(?=(\d{3})+(?!\d))` + `.` separator

### Receipt: `resources/views/pos/print-receipt.blade.php`
- 80mm thermal layout
- Store name, items list, discount row (merah), PPN dinamis, grand total, payment method, kasir name
- Parse dari notes: `PPN {rate}%: Rp {amount}` via regex `/^PPN (\d+)%: Rp ([\d.]+)$/`

### Notes format: `Dine-in - NamaCustomer | Diskon: Rp X | PPN {rate}%: Rp Y`

### Login redirect: Cashier → `/pos`

---

## Settings System

- **Model**: `Setting` — key-value store; `Setting::get($key, $default)` (bank_accounts returns `[]` never null; boolean settings disimpan sebagai string `"1"`/`"0"`)
- **Page**: `ManageSettings.php` — Schema-based form
- **Fields**: store_address, phone, whatsapp, email, instagram, facebook, tiktok, bank_accounts (Repeater), **ppn_enabled (Toggle)**, **ppn_percentage (TextInput, suffix %, default 11)**
- **Save**: Boolean values (`is_bool`) otomatis dikonversi ke `"1"`/`"0"` sebelum disimpan
- **Defaults (seeder)**: ppn_enabled = "0", ppn_percentage = "11"
- **Footer**: 5-column grid — alamat, kontak, sosial media, pembayaran (logo bank dari `public/images/payments/`: bca, mandiri, bri, bni, cod), info toko

---

## Cart & Checkout Flow

1. **Session-based cart**: `session('cart', collect())` — setiap item: `{product_id, name, slug, price, image, quantity, stock}`
2. **Guest bisa browsing + cart**, tapi checkout perlu login (redirect ke login)
3. **Checkout**: `/checkout` → pilih alamat + kurir (via RajaOngkir) + payment method + notes; **PPN otomatis dihitung kalau setting aktif**
4. **Place order**: Validasi stock (N+1 safe), DB transaction → total = subtotal + ongkir + PPN, create Order + OrderItems + Payment (kalau manual_transfer), decrement stock, recordStockHistory, clear cart; PPN disimpan di notes
5. **Upload payment**: `/orders/{order}/payment` → upload proof image + bank details → auto `paid` + `processing` (tanpa persetujuan admin)
6. **Confirm received**: Customer klik "Pesanan Diterima" saat status `shipped` → Alpine.js modal confirm → status `delivered`, `delivered_at` diisi
7. **Cancel order**: Customer batalkan hanya saat `pending` → DB transaction restore stock + recordStockHistory

---

## Storefront Views (10)

| View | Lokasi | Fungsi |
|---|---|---|
| Layout Store | `layouts/store.blade.php` | Main layout: navbar (search suggestions Alpine.js, cart badge, user dropdown @click), announcement bar, promo popup, 5-column footer; favicon SVG |
| Layout Account | `layouts/account.blade.php` | Customer dashboard sidebar (avatar + nav: Dashboard, Pesanan, Alamat, Profil, Keluar) |
| Layout Guest | `layouts/guest.blade.php` | Dark purple gradient untuk auth pages; favicon SVG |
| Layout App | `layouts/app.blade.php` | Breeze default dengan role-based nav; logo HS + "Hello Store" |
| Navigation | `layouts/navigation.blade.php` | Navbar dengan logo "HS" orange + nama toko |
| Home | `store/home.blade.php` | Hero section, categories grid, featured + latest products dengan rating |
| Products | `store/products.blade.php` | Sidebar category filter, search, sort dropdown (Terbaru/Termurah/Termahal/Nama A-Z), pagination |
| Product Detail | `store/product-detail.blade.php` | Breadcrumb, Alpine.js image gallery (thumbnail strip click to switch), price, stock, description, add-to-cart (flex-col di mobile), related products, recently viewed, review form star picker, SEO meta tags |
| Product Card | `components/product-card.blade.php` | Discount badge, featured badge, main_image, quick view overlay, rating stars component, price |
| Cart | `store/cart.blade.php` | Items with quantity stepper, subtotal per item, total all, checkout button |
| Checkout | `store/checkout.blade.php` | Address selection radio, payment method radio, courier selection with shipping cost, notes textarea, order summary **dengan PPN line (jika setting aktif)** |
| Orders | `store/orders.blade.php` | Order list with status badges, order number, date, total |
| Order Detail | `store/order-detail.blade.php` | Order info, address card, payment info, status timeline, shipped_at/delivered_at, confirm received button (Alpine.js modal), cancel button (Alpine.js modal) — hanya untuk pending, payment info card (green check setelah upload), payment upload form, items list, review link per item, **PPN line (dari notes)** |
| Addresses | `store/addresses.blade.php` | Address cards with edit/delete buttons, default badge |
| Address Form | `store/address-form.blade.php` | Create/edit address form |
| Print Receipt | `store/print-receipt.blade.php` | Thermal 80mm untuk online orders — **PPN line (dari notes)** |
| Account Dashboard | `account/dashboard.blade.php` | 4 stat cards (total orders, addresses, reviews) + recent orders list + empty state |

---

## Daftar Fitur Lengkap (A-Z)

### 1. Auto-Dot Price Formatting
- **Admin (Filament)**: Fungsi `formatRupiah` JS via IIFE di `AdminPanelProvider`; capture-phase `document.addEventListener('input', ...)` mendeteksi `.fi-input` dengan `wire:model` mengandung `price|subtotal|shipping|total|amount`; inser separator titik otomatis saat mengetik
- **POS Kasir**: Alpine.js method `onAmountInput`, `formatDiscount`, `updateItemDiscount` — format dengan titik untuk nominal Rupiah; getter `amountPaidNum`/`discountNum` strip titik sebelum parse

### 2. Banners & Promo
- Banner migration: type (announcement/popup), is_active, date range, sort_order
- Model `Banner` dengan `scopeActive()` + SoftDeletes
- Tampil di store layout: announcement bar di atas navbar, promo popup modal
- Filament resource full CRUD

### 3. Cart (Session-based)
- Guest-friendly: tanpa login bisa add to cart
- Cart items store di session: `{product_id, name, slug, price, image, quantity, stock}`
- Qty stepper + stock cap (tidak bisa melebihi stock tersedia)
- Live stock check di update (`$liveProducts` via `whereIn` — N+1 safe)
- Zero-stock check di `cartAdd` (reject kalau stock 0)

### 4. Customer Dashboard
- `AccountController@dashboard` — stat cards + recent orders
- Sidebar layout: avatar + nav links (Dashboard, Pesanan, Alamat, Profil, Keluar)
- Route `GET /account` — navbar link "Akun Saya"

### 5. Expense Tracking
- Migrations: `expense_categories` + `expenses`
- Models: `ExpenseCategory` (hasMany), `Expense` (belongsTo category + user)
- Filament resources: full CRUD, grouped under "Keuangan"

### 6. Filament Admin Panel
- Schema-based forms (`Filament\Schemas\Schema`, bukan `Filament\Forms\Form`)
- 10 resources + 1 custom page (Settings)
- 5 dashboard widgets: FinanceOverview, RevenueChart, StatsOverviewWidget, RevenueChartWidget, RecentOrdersWidget
- Dark mode, Amber primary, Bahasa Indonesia labels
- Tooltip helper JavaScript untuk sidebar navigation items
- Auto-dot price formatting via event delegation

### 7. Mobile Responsiveness
- User dropdown pakai `@click` (Alpine.js) bukan `group-hover` (support touch)
- Cart table: `min-w-[90px]` dihapus di mobile, hanya `lg:min-w-[110px]`
- Product detail add-to-cart: `flex-col sm:flex-row` (stack di 320px screens)
- Semua view pakai responsive Tailwind classes (`sm:`, `md:`, `lg:`)

### 8. Order Flow (Payment + Shipping)
- **Status flow**: pending → (upload payment) → processing → (admin set shipped) → shipped → (customer confirm) → delivered
- **Payment status**: unpaid → (upload/verify) → paid
- **Upload payment**: auto `paid` + `processing` (tanpa perlu admin approve)
- **Confirm received**: Alpine.js modal → status `delivered` + `delivered_at` timestamp
- **Cancel order**: Hanya pending orders → DB transaction restore stock + recordStockHistory
- Admin form pakai Select dropdowns (bukan manual typing)
- Customer lihat `shipped_at` / `delivered_at` di order detail

### 9. POS (Point of Sale)
- Split layout: product grid + cart sidebar (420px)
- Product search by name/SKU + category filter pills
- Cart CRUD dengan stock cap
- Discount: per-item (%/Rp toggle) + global discount
- **PPN dinamis dari settings** (checkbox toggle per transaksi)
- Checkout: DB transaction create Order (completed) + Items + Payment, decrement stock
- Payment methods: cash, QRIS, debit, transfer
- Hold/Recall order (session-based)
- History hari ini (JSON endpoint)
- Thermal 80mm print receipt
- Keyboard shortcuts: Enter → checkout, Esc → reset
- **Auto-dot formatting** untuk input jumlah dibayar dan diskon
- Akses: hanya admin + cashier (via `Gate::define('access-pos')`)

### 10. Product Images Gallery
- Migration `create_product_images_table` (product_id, image, sort_order)
- Model `ProductImage`: `$appends = ['url']`, `getUrlAttribute` (relative path → `Storage::url()`, absolute → pass through)
- Data migration: migrasi dari JSON `images` column → `product_images` records
- Added `images` JSON column kembali untuk Filament FileUpload compatibility
- Product model: `productImages()` HasMany (ordered by sort_order), `getMainImageAttribute`
- `booted` saved event syncs JSON `images` → ProductImage records
- Filament: `FileUpload::make('images')->multiple()` langsung (tidak di dalam Repeater)
- Storefront: Alpine.js gallery dengan thumbnail strip
- `$product->images` = JSON array (column), `$product->productImages` = Collection (relationship)

### 11. Product Sorting (Storefront)
- Dropdown sort: Terbaru, Termurah, Termahal, Nama A-Z
- Sort param `?sort=...` di URL
- Preserved di category sidebar links via `request('sort')`
- Implementasi: `match ($sort)` di `StoreController@products`

### 12. Recently Viewed Products
- Track product IDs di `session('recently_viewed')`
- Max 12, deduplicated, exclude current product
- "Baru Dilihat" section di product detail (below related products)
- Eager loaded `productImages`

### 13. Review & Rating
- Migration dengan unique constraint (product_id + user_id)
- Review model + factory
- Product: `reviews()`, `approvedReviews()` HasMany
- Filament: list + edit + approve toggle + delete (no create); `BulkActionGroup` dengan Approve/Reject
- Storefront: Alpine.js star picker (5 bintang interaktif)
- Product card: rating stars dari `withAvg('approvedReviews', 'rating')`
- Order detail: "Beri Ulasan" muncul kalau delivered + belum review
- N+1 safe: `withCount`/`withAvg` pada `approvedReviews`

### 14. Search Suggestions (AJAX)
- Route `GET /products/suggestions?q=...` → JSON
- Match name + SKU, min 2 chars, max 6 results
- Alpine.js di navbar: 300ms debounce, dropdown thumbnail/name/price
- Click → navigate ke product detail

### 15. SEO Fields
- Migration: meta_title (varchar 70), meta_description (varchar 160)
- Product form: TextInput dengan `->maxLength()` validation
- `@stack('meta')` di layouts store head
- `@push('meta')` di product detail: OG tags, Twitter card, meta description

### 16. Settings (Key-Value Store)
- Model Setting: `get($key, $default)` static method
- ManageSettings page: Schema-based form dengan Sections
- Fields: store_address, phone, whatsapp, email, instagram, facebook, tiktok, bank_accounts (Repeater), **ppn_enabled (Toggle)**, **ppn_percentage (suffix %)**
- Boolean values dikonversi ke string `"1"`/`"0"` di `save()`
- Footer 5-column driven by settings

### 17. Soft Delete
- Migrations add `deleted_at` ke products, categories, banners
- Models pakai `SoftDeletes` trait
- Filament tables: `TrashedFilter`, `RestoreAction`, `ForceDeleteAction`

### 18. Stock History & Adjustment
- Migration `create_stock_histories_table`
- `StockHistory` model belongsTo Product + User
- Product `stockHistories()` HasMany + `recordStockHistory()` helper
- Auto-log on Filament save (`wasChanged`), POS checkout (`pos`), storefront checkout (`order`), cancel order (`order`), stock adjustment (`manual`)
- "Sesuaikan Stok" action di EditProduct page (modal: qty + reason dropdown: Restok/Retur/Penyesuaian + notes)
- Filament resource "Riwayat Stok" read-only: type badges, color-coded quantity (hijau positif, merah negatif, abu-abu 0), stock before/after, user, notes, timestamp

### 19. Dashboard Widgets (Admin)
- **StatsOverviewWidget**: Pesanan Hari Ini, Pendapatan Bulan Ini, Pesanan Menunggu, Stok Menipis — **semua clickable** menuju resource page dengan filter
- **RevenueChartWidget**: Line chart 30 hari terakhir
- **RecentOrdersWidget**: Table 10 pesanan terakhir dengan status badges
- **FinanceOverview**: Total Pendapatan, Total Pengeluaran, Laba Bersih, Total Pesanan — dari seluruh waktu
- **RevenueChart**: Line chart pendapatan 6 bulan terakhir

### 20. Authentication
- Laravel Breeze scaffolding dengan custom styling
- Login dengan ikon input + eye toggle password + amber gradient button
- Register dengan layout flex yang sama
- Login redirect: admin → `/admin`, cashier → `/pos`, customer → storefront

### 21. Branding Hello Store
- `welcome.blade.php` DHL (default Laravel page, sudah dihapus)
- `.env`: `APP_NAME="Hello Store"`
- `config/app.php`, `config/mail.php`, `config/logging.php`: fallback `'Hello Store'`
- Navigation: "HS" logo orange + "Hello Store"
- Dashboard: brand welcome card dengan user name + role + navigation buttons
- `public/favicon.svg`: SVG rounded square amber gradient dengan "HS" putih
- Favicon referenced di AdminPanelProvider, store layout, guest layout, app layout
- `public/favicon.ico` DHL (Laravel default, sudah dihapus)

### 22. PPN Settings & Dynamic Rate
- Setting `ppn_enabled` (Toggle) dan `ppn_percentage` (default 11) di halaman Pengaturan Toko
- **POS**: Checkbox toggle PPN menggunakan rate dari settings (bukan hardcoded 11%); label dinamis `PPN {rate}%`
- **Storefront checkout**: PPN otomatis dihitung kalau setting aktif; ditampilkan sebagai line terpisah (Subtotal + Ongkir + PPN + Total)
- **Notes format**: `PPN {rate}%: Rp {amount}` disimpan di order notes
- **Receipt parsing**: POS dan storefront receipt parse PPN dari notes via regex `/^PPN (\d+)%: Rp ([\d.]+)$/` (fleksibel terhadap rate berapa pun)

### 23. Clickable Dashboard Stats + Table Filters
- **StatsOverviewWidget**: Stat card "Pesanan Hari Ini" → `OrderResource` dengan filter `hari_ini`; "Pesanan Menunggu" → `OrderResource` dengan filter `menunggu`; "Stok Menipis" → `ProductResource` dengan stock filter
- **OrdersTable**: `Filter::make('hari_ini')` dan `Filter::make('menunggu')` — toggle filter
- **ProductsTable**: `SelectFilter::make('stock')` — "Stok Menipis (≤ 5)", "Habis (0)"

---

## Gate & Middleware

- `Gate::define('access-pos', fn ($user) => in_array($user->role, ['admin', 'cashier']))` di `AppServiceProvider::boot()`
- POS route group: `['auth', 'can:access-pos']`
- Order authorization: `if ($order->user_id !== auth()->id()) abort(403)`

---

## Konvensi Kode

- Harga IDR: `number_format($x, 0, ',', '.')`
- PHP 8 attributes untuk model config (`#[UseFactory]`, `#[Fillable]`, `#[Hidden]`)
- Route model binding: `Product $product`, `Order $order`
- Storefront views extend `layouts.store`, auth pakai `x-guest-layout` / `x-app-layout`
- Auth inputs: raw `<input>`+`<label>` (bukan Breeze `<x-text-input>`)
- Filament resources di subdirectory sesuai nama
- Semua harga disimpan sebagai decimal di DB, diformat pas display
- RajaOngkir origin: Bandung (city ID 23)
- Timezone: `Asia/Jakarta` (WIB)
- `.npmrc`: `ignore-scripts=true` — npm postinstall scripts tidak berjalan otomatis

---

## Catatan Penting & Gotchas

- **`Property [$form] not found`**: `form()` harus pakai `Schema $schema`, bukan `Form $form`. Import `Filament\Schemas\Schema`.
- **`formatStateUsing` falsy `0`**: Pakai `$state !== null && $state !== '' ? ... : ''` bukan `$state ? ... : ''`.
- **`->money('IDR')`**: Hanya di `TextColumn`, TIDAK di `TextInput`.
- **`@json()` di Blade**: Aman di `<script>` context; menghasilkan double-quotes di HTML attributes (break syntax).
- **FileUpload inside Repeater with `->relationship()`**: TIDAK bekerja di Filament 5.6 — file tidak tersimpan. Pakai `FileUpload::make()->multiple()` langsung di parent model + sync via model event.
- **`$product->images` vs `$product->productImages`**: `images` = JSON column (return array/null), `productImages` = HasMany relationship (return Collection). Mereka BERBEDA.
- **PHP property type**: Override parent static properties membutuhkan exact type match (`string | BackedEnum | null` untuk `$navigationIcon`, `bool` untuk `$shouldRegisterNavigation`, `?int` untuk `$sort`).
- **XSS safety**: Semua `old()` values di Alpine.js pakai `@json()` (bukan raw Blade).
- **`isRecentlyCreated` TIDAK valid** — properti Eloquent yang benar adalah `wasRecentlyCreated`.
- **Static property override**: Di Filament versi baru, beberapa parent properties seperti `$heading` di ChartWidget adalah **non-static**. Override sebagai `protected ?string`, bukan `protected static ?string`.
- **Union type spaces**: `int | string | array` dianggap style issue oleh Pint — harus `int|string|array`.
- **Auto-dot via event delegation**: `extraInputAttributes(['oninput' => 'formatRupiah(this)'])` gagal karena fungsi tidak global. Solusi: capture-phase `document.addEventListener('input', ...)` dengan filter `wire:model` regex. JANGAN dispatch `input` event dari handler (avoid recursion dengan `wire:model`).
- **Boolean settings**: Di `ManageSettings::save()`, `Toggle` mengembalikan `bool` PHP. Konversi manual ke `"1"`/`"0"` via `is_bool($value)` sebelum `updateOrCreate`.
- **PPN notes parsing**: Format `PPN {rate}%: Rp {amount}` — parse via regex `/^PPN (\d+)%: Rp ([\d.]+)$/` di receipt views (POS + storefront).

---

## Testing

- Unit tests: `PHPUnit\Framework\TestCase` (tanpa app boot)
- Feature tests: `Tests\TestCase` (full app boot), SQLite `:memory:`
- Semua feature tests pakai `RefreshDatabase` (kecuali ExampleTest)
- 25 tests, 61 assertions — semuanya pass
- Pint clean: 159 files, 0 issues
- Command: `composer test` (config:clear lalu artisan test)
