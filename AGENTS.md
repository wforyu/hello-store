# AGENTS.md — Hello Store

## Informasi Proyek

**Hello Store** adalah aplikasi toko online pribadi ala Shopee dengan Laravel 13, PHP ^8.3, MySQL via XAMPP (MariaDB 10.4.32). Database `hello_store_db`, user `root` tanpa password. Tailwind CSS v4 + Alpine.js + Vite. Laravel Breeze untuk scaffolding auth. PHPUnit 12, Laravel Pint 1.29. Filament 5.6.7 untuk admin panel (Schema-based form — BUKAN `Filament\Forms\Form`).

### Arsitektur & Alur
- **Session-based cart**: Tidak perlu login untuk browsing + cart; produk bisa ditambahkan sebagai guest
- **Three-role system**: `admin` (semua akses), `cashier` (POS only), `customer` (storefront only)
- **POS**: Split layout — grid produk kiri + sidebar cart 420px kanan; dibuat dengan Alpine.js
- **Storefront**: Layout publik dengan navbar, search suggestions, cart badge, user dropdown (Alpine.js, bukan group-hover agar works di touch)
- **Admin Panel**: Filament dark mode, primary Amber, semua label Bahasa Indonesia, navigation groups (Tampilan, Keuangan, Pengaturan, Produk, Pesanan, Persediaan, Pemasaran, Pengguna) — sudah sesuai dengan semua resource
- **Flow pesanan**: Pending → upload payment → auto Processing → admin set Shipped → customer klik "Pesanan Diterima" → Delivered
- **Points System**: Customer dapat poin dari order delivered (10% dari total); bisa redeem di checkout (1 poin = Rp1, maks 50% total)
- **Flash Sale**: Diskon waktu terbatas per produk dengan stok kuota; berlaku di storefront
- **Product Bundle**: Paket produk dengan harga khusus
- **Supplier & Purchase Order**: Manajemen supplier, order barang, receiving
- **Stock Opname**: Adjust stok fisik (berbeda dengan sistem), auto-record stock history
- **Purchase Return**: Retur barang ke supplier, auto-restore stok
- **Tracking Event**: Lacak status pengiriman per order
- **PPN**: Bisa diaktifkan/dinonaktifkan dari admin settings; rate bisa diubah (default 11%); berlaku di POS (checkbox toggle per transaksi) dan storefront (otomatis)

---

## Perintah Penting

| Perintah | Fungsi |
|---|---|
| `composer test` | `config:clear` lalu `php artisan test` (Unit + Feature) |
| `composer test:coverage` | Jalankan test dengan coverage report |
| `composer test:coverage` | Jalankan test dengan coverage report |
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
| POST | `/wishlist/toggle/{product}` | `StoreController@wishlistToggle` | `wishlist.toggle` |
| GET | `/wishlist` | `StoreController@wishlistIndex` | `wishlist.index` |
| POST | `/compare/toggle/{product}` | `StoreController@compareToggle` | `compare.toggle` |
| GET | `/compare` | `StoreController@compareIndex` | `compare.index` |
| POST | `/orders/{order}/reorder` | `StoreController@reorder` | `orders.reorder` |
| POST | `/checkout/apply-coupon` | `StoreController@applyCoupon` | `checkout.coupon` |
| POST | `/orders/{order}/refund` | `StoreController@processRefund` | `orders.refund` |
| GET | `/orders/{order}/download/{product}` | `StoreController@downloadDigital` | `orders.download` |
| GET | `/notifications` | `NotificationController@index` | `notifications.index` |
| GET | `/notifications/unread-count` | `NotificationController@unreadCount` | `notifications.unread-count` |
| POST | `/notifications/{id}/read` | `NotificationController@markAsRead` | `notifications.read` |
| POST | `/notifications/mark-all-read` | `NotificationController@markAllAsRead` | `notifications.mark-all-read` |
| GET | `/notifications/unread-json` | `NotificationController@markAsReadJson` | `notifications.unread-json` |
| GET | `/barcode` | `BarcodeController@index` | `barcode.index` |
| POST | `/barcode/generate` | `BarcodeController@generate` | `barcode.generate` |
| POST | `/barcode/generate-for-product/{product}` | `BarcodeController@generateForProduct` | `barcode.product` |

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

## Database — 31 Migrations

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
| 26 | `create_suppliers_table` | Supplier (name, contact_person, phone, email, address) |
| 27 | `create_purchase_orders_table` + `create_purchase_order_items_table` | PO (supplier_id, order_number, status, subtotal, tax, total, notes, created_by, ordered_at, received_at) + items (product_id, product_name, product_sku, quantity, unit_cost, subtotal) |
| 28 | `create_stock_opnames_table` + `create_stock_opname_items_table` | Opname (opname_number, status, notes, created_by) + items (product_id, system_stock, physical_stock, difference, notes) |
| 29 | `create_purchase_returns_table` + `create_purchase_return_items_table` | Retur (return_number, supplier_id, purchase_order_id, status, reason, notes, total_amount, created_by) + items (product_id, product_name, product_sku, quantity, unit_cost, subtotal, reason) |
| 30 | `create_flash_sales_table` + `create_flash_sale_product_table` | Flash Sale (name, slug, description, start_time, end_time, status, is_active, banner_image) + pivot (flash_sale_id, product_id, discount_type, discount_value, max_qty, sold_count) |
| 31 | `create_product_bundles_table` + `create_bundle_products_table` | Bundle (name, slug, description, bundle_price, total_original_price, image, is_active, start_time, end_time) + pivot (bundle_id, product_id, quantity) |
| — | `create_tracking_events_table` | Tracking events (order_id, status, location, description, occurred_at) |
| — | `create_point_transactions_table` | Point transactions (user_id, type, points, balance_before, balance_after, reference_type, reference_id, description) |
| — | `add_points_to_users_table` | Tambah kolom points (default 0) ke users |
| — | `create_brands_table` | Brand (name, slug, description, logo, website, is_active) |
| — | `create_product_variants_table` | Product Variant (product_id, name, price, stock, weight, image, sku, is_active, sort_order) |
| — | `create_product_variant_attributes_table` | Variant Attributes (product_variant_id, type, value, label, sort_order) |
| — | `add_variant_id_to_cart_items_table` | Add product_variant_id nullable ke cart_items |
| — | `add_variant_fields_to_orders` | Add product_variant_id + variant_name ke order_items |

---

## Models (27+ total)

| Model | File | Fillable | Casts | Relasi Utama |
|---|---|---|---|---|
| **User** | `Models/User.php` | name, email, password, role | `password => hashed` | reviews(), wishlistProducts() |
| **Product** | `Models/Product.php` | category_id, brand_id, name, slug, description, price, compare_price, stock, sku, weight, images, is_active, featured, is_digital, digital_file, license_key, meta_title, meta_description | `price/compare_price/weight => decimal:2`, `images => array`, `is_active/featured/is_digital => boolean` | category(), brand(), productImages(), stockHistories(), reviews(), approvedReviews(), attributes(), orderDownloads(); `booted` saved event: sync images JSON → ProductImage records + auto-log stock change |
| **ProductImage** | `Models/ProductImage.php` | product_id, image, sort_order | — | belongsTo(Product); `$appends=['url']` dengan getUrlAttribute (relative path → Storage::url(), absolute → pass through) |
| **ProductAttribute** | `Models/ProductAttribute.php` | product_id, type, value, label, sort_order | — | belongsTo(Product); scopeOfType() |
| **StockHistory** | `Models/StockHistory.php` | product_id, user_id, type, reference_type, reference_id, quantity_change, stock_before, stock_after, notes | — | belongsTo(Product), belongsTo(User) |
| **Category** | `Models/Category.php` | parent_id, name, slug, description, image, is_active, sort_order | — | parent(), children(), products(); SoftDeletes |
| **Brand** | `Models/Brand.php` | name, slug, description, logo, website, is_active | — | products() |
| **ProductVariant** | `Models/ProductVariant.php` | product_id, name, price, stock, weight, image, sku, is_active, sort_order | `price/weight => decimal:2`, `is_active => boolean` | product(), attributes() |
| **ProductVariantAttribute** | `Models/ProductVariantAttribute.php` | product_variant_id, type, value, label, sort_order | — | variant() |
| **FlashSaleProduct** | `Models/FlashSaleProduct.php` | flash_sale_id, product_id, discount_type, discount_value, max_qty, sold_count | — | — |
| **Order** | `Models/Order.php` | user_id, order_number, status, subtotal, shipping_cost, total, payment_method, payment_status, notes, admin_notes, address_id, shipping_courier, shipping_tracking_number, coupon_id, discount, shipped_at, delivered_at, cancelled_at | `subtotal/shipping_cost/total/discount => decimal:2`, `shipped_at/delivered_at/cancelled_at => datetime` | user(), items(), payment(), address(), coupon() |
| **OrderItem** | `Models/OrderItem.php` | order_id, product_id, product_name, product_price, quantity, subtotal | `product_price/subtotal => decimal:2` | order(), product() |
| **OrderDownload** | `Models/OrderDownload.php` | order_id, product_id, user_id, download_count | — | belongsTo(Order), belongsTo(Product); canDownload() max 5 |
| **Payment** | `Models/Payment.php` | order_id, method, amount, status, proof_image, bank_name, account_name, account_number, paid_at, notes | `amount => decimal:2`, `paid_at => datetime` | belongsTo(Order); `$appends=['proof_image_url']` |
| **Address** | `Models/Address.php` | user_id, label, recipient, phone, street, city, province, postal_code, notes, is_default | `is_default => boolean` | belongsTo(User) |
| **Cart** | `Models/Cart.php` | user_id, session_id | — | user(), items() |
| **CartItem** | `Models/CartItem.php` | cart_id, product_id, quantity, price | — | cart(), product() |
| **Wishlist** | `Models/Wishlist.php` | user_id, product_id | — | belongsTo(User), belongsTo(Product); unique pair |
| **Review** | `Models/Review.php` | product_id, user_id, rating, comment, is_approved | — | belongsTo(Product), belongsTo(User) |
| **Banner** | `Models/Banner.php` | title, description, image_url, link_url, link_text, type, start_at, end_at, is_active, sort_order | `start_at/end_at => datetime`, `is_active => boolean` | scopeActive(); SoftDeletes |
| **Coupon** | `Models/Coupon.php` | code, type, value, max_discount, min_order, usage_limit, usage_per_user, expires_at, is_active | `expires_at => datetime`, `is_active => boolean` | isValid(), canUseBy(), calculateDiscount(); users() BelongsToMany |
| **Setting** | `Models/Setting.php` | key, value | — | Static `get($key, $default)` — bank_accounts return `[]` never null; booleans stored as `"1"`/`"0"` |
| **Expense** | `Models/Expense.php` | expense_category_id, amount, description, user_id, date | `amount => decimal:2`, `date => date` | belongsTo(ExpenseCategory), belongsTo(User) |
| **ExpenseCategory** | `Models/ExpenseCategory.php` | name, slug, description | — | hasMany(Expense) |
| **Shift** | `Models/Shift.php` | user_id, opened_at, closed_at, opening_balance, closing_balance, expected_balance, status | `opened_at/closed_at => datetime`, `opening_balance/closing_balance/expected_balance => decimal:2` | user(), orders(), expenses(); isOpen(), totalOrders(), totalRevenue() |
| **ShiftExpense** | `Models/ShiftExpense.php` | shift_id, expense_category_id, amount, description, user_id | `amount => decimal:2` | belongsTo(Shift), belongsTo(ExpenseCategory), belongsTo(User) |
| **Notification** | `Models/Notification.php` | user_id, type, title, message, data, is_read, read_at | `data => array`, `is_read => boolean`, `read_at => datetime` | createForUser(), createForAdmins(), markAsRead(), scopeUnread() |
| **Supplier** | `Models/Supplier.php` | name, contact_person, phone, email, address | — | purchaseOrders() HasMany; SoftDeletes |
| **PurchaseOrder** | `Models/PurchaseOrder.php` | supplier_id, order_number, status, subtotal, tax, total, notes, created_by, ordered_at, received_at | `subtotal/tax/total => decimal:2`, `ordered_at/received_at => datetime` | supplier(), items(), creator() |
| **PurchaseOrderItem** | `Models/PurchaseOrderItem.php` | purchase_order_id, product_id, product_name, product_sku, quantity, unit_cost, subtotal | `quantity/unit_cost/subtotal => decimal:2` | purchaseOrder(), product() |
| **StockOpname** | `Models/StockOpname.php` | opname_number, status, notes, created_by | — | items(), creator() |
| **StockOpnameItem** | `Models/StockOpnameItem.php` | stock_opname_id, product_id, system_stock, physical_stock, difference, notes | `system_stock/physical_stock/difference => integer` | stockOpname(), product() |
| **PurchaseReturn** | `Models/PurchaseReturn.php` | return_number, supplier_id, purchase_order_id, status, reason, notes, total_amount, created_by | `total_amount => decimal:2` | supplier(), purchaseOrder(), items(), creator() |
| **PurchaseReturnItem** | `Models/PurchaseReturnItem.php` | purchase_return_id, product_id, product_name, product_sku, quantity, unit_cost, subtotal, reason | `quantity/unit_cost/subtotal => decimal:2` | purchaseReturn(), product() |
| **FlashSale** | `Models/FlashSale.php` | name, slug, description, start_time, end_time, status, is_active, banner_image | `start_time/end_time => datetime`, `is_active => boolean` | products() BelongsToMany (flash_sale_products pivot with discount_type, discount_value, max_qty, sold_count) |
| **ProductBundle** | `Models/ProductBundle.php` | name, slug, description, bundle_price, total_original_price, image, is_active, start_time, end_time | `bundle_price/total_original_price => decimal:2`, `start_time/end_time => datetime`, `is_active => boolean` | products() BelongsToMany (bundle_products pivot with quantity) |
| **TrackingEvent** | `Models/TrackingEvent.php` | order_id, status, location, description, occurred_at | `occurred_at => datetime` | belongsTo(Order); `$appends=['status_label']` dengan getStatusLabelAttribute() |
| **PointTransaction** | `Models/PointTransaction.php` | user_id, type, points, balance_before, balance_after, reference_type, reference_id, description | `points/balance_before/balance_after => integer` | user(), reference() MorphTo |

---

## Controllers (6 custom + Auth bawaan + Phase 2)

### Phase 2 Modifications on StoreController
- **checkout()**: Added points display + points redeem checkbox (max 50% total)
- **placeOrder()**: Deducts redeemed points if `use_points` is set; adds earned points (10% of total) on confirmed received
- **confirmReceived()**: Added `auth()->user()->addPoints(floor($order->total * 0.1))` untuk earned points

### StoreController (`app/Http/Controllers/StoreController.php`)
- **home()**: Ambil categories tree, featured + latest products (8 each) with avg rating + count
- **products()**: Filter by search (name + SKU) + category + sort (terbaru/termurah/termahal/nama) + flash_sale, pagination 12
- **productDetail()**: Product with images, related products (same category, 4 items), reviews, user review status, recently viewed session tracking
- **suggestions()**: JSON search — match name/SKU, min 2 chars, max 6 results
- **cartIndex()**: Tampilkan session cart
- **cartAdd()**: Validasi stock >= 1, add/update cart item, cap by stock
- **cartUpdate()**: Batch update quantities dengan live stock check (N+1 safe via `whereIn`)
- **cartRemove()**: Remove item dari cart
- **checkout()**: Cart validation, address selection, shipping rates (RajaOngkir via ShippingService), subtotal, weight, **PPN (dari setting)**; coupon input + AJAX validation
- **placeOrder()**: Validasi stock dengan N+1 fix (`$liveProducts`), DB transaction: **subtotal + ongkir - diskon + PPN = total**, create Order + OrderItems + Payment (manual_transfer), decrement stock, recordStockHistory, clear cart; PPN & diskon disimpan di notes; auto-notify user + admins
- **orders()**: User's orders dengan eager load items+payment+address
- **orderShow()**: Single order detail (authorization check user_id)
- **paymentUpload()**: Upload proof image, auto set paid+processing, delete old proof file sebelum upload baru; auto-notify admins
- **confirmReceived()**: Customer confirms delivery (only if status shipped → delivered + delivered_at); auto-notify admins
- **cancelOrder()**: Only pending orders, DB transaction restore stock + recordStockHistory; eager load items.product
- **printReceipt()**: Thermal 80mm receipt
- **printReceiptAdmin()**: Admin receipt print (tanpa auth check)
- **reviewStore()**: Upsert review (create or update if exists)
- **getCartWeight()**: Hitung total weight untuk shipping
- **wishlistToggle()**: POST toggle wishlist (user, product_id unique), return JSON
- **wishlistIndex()**: Paginated wishlist view with product images + prices
- **compareToggle()**: POST toggle compare (session max 4), return JSON + count
- **compareIndex()**: Full comparison table (price, stock, SKU, weight, category, rating, attributes, description)
- **reorder()**: POST reorder dari order delivered — merge items ke session cart, skip inactive/out-of-stock
- **applyCoupon()**: POST AJAX validasi kupon (check expired, usage limit, min_order, usage per user), return diskon + nama
- **processRefund()**: POST refund (admin only) — restore stock per item, recordStockHistory, update order status refunded, notify user
- **downloadDigital()**: GET download digital product — validasi ownership + payment + download limit (max 5), serve file dari Storage

### PosController (`app/Http/Controllers/PosController.php`)
- **index()**: Load all active products, categories, customers, **PPN rate dari setting**
- **search()**: Filter produk by name/SKU/category
- **customers()**: Search customers by name
- **add()**: Add to POS cart, validasi stock >= 1
- **update()**: Update quantity + discount per item dengan live stock
- **remove()**: Remove item dari POS cart
- **checkout()**: Hitung item discount, global discount, **PPN dinamis (dari setting)**, total; validasi amount_paid untuk cash; DB transaction create Order (status=completed) + OrderItems + Payment, decrement stock, recordStockHistory; clear cart; PPN disimpan di notes; record shift_id
- **holdOrder()**: Hold cart (simpan di session)
- **recallOrders()**: List all held orders
- **recallOrder()**: Recall held order ke cart
- **deleteHold()**: Delete held order
- **history()**: Today's completed orders (return JSON dengan parsed customer name dari notes)
- **printReceipt()**: Thermal receipt view
- **scanBarcode()**: POST /pos/scan — lookup by SKU, add to cart, return JSON
- **openShift()**: Buka shift (set opening balance)
- **closeShift()**: Tutup shift (hitung closing balance — opening + revenue - expenses)
- **shiftHistory()**: Paginated list of all shifts
- **addExpense()**: POST tambah pengeluaran shift (amount, category, description)
- **deleteExpense()**: DELETE hapus pengeluaran shift

### AccountController (`app/Http/Controllers/AccountController.php`)
- **dashboard()**: Stat cards (total orders, addresses, reviews) + recent orders

### AddressController (`app/Http/Controllers/AddressController.php`)
- Full resource (except show): CRUD alamat pengiriman

### ProfileController (`app/Http/Controllers/ProfileController.php`)
- **edit()/update()/destroy()**: Manage user profile (Breeze default)

### Auth Controllers (`app/Http/Controllers/Auth/`)
- Login, Register, Password Reset, Email Verification, Logout (Breeze scaffolding + custom layout/storefront integration)

### BarcodeController (`app/Http/Controllers/BarcodeController.php`)
- **index()/generate()/generateForProduct()**: Barcode generation page with product checkboxes, type (Code128/EAN13/QR), label size; print-optimized output with inline SVG + auto-print

### NotificationController (`app/Http/Controllers/NotificationController.php`)
- **index()/unreadCount()/markAsRead()/markAllAsRead()/markAsReadJson()**: Notification center with color-coded types, pagination, empty state, Alpine.js unread count

### ReportController (`app/Http/Controllers/ReportController.php`)
- **export()**: Stream CSV with UTF-8 BOM for Reports page, date range filter

---

## Filament Admin Panel

### Konfigurasi (`AdminPanelProvider.php`)
- Dark mode enabled, Primary: Amber
- Brand: "Hello Store" dengan favicon SVG
- Navigation groups: Tampilan, Keuangan, Pengaturan, Produk, Pesanan, Pengguna
- Tooltip helper script untuk sidebar items
- Widgets auto-discovered
- **`formatRupiah` JS** — IIFE mendefinisikan fungsi global + capture-phase `document.addEventListener('input', ...)` untuk auto-dot formatting pada input dengan `wire:model` mengandung `price|subtotal|shipping|total|amount` (menggunakan `.fi-input` selector). Dipanggil via event delegation, bukan `extraInputAttributes`.

### Resources (16)

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
| Persediaan | Suppliers | Supplier | `Truck` |
| Persediaan | Purchase Orders | PurchaseOrder | `ClipboardDocumentCheck` |
| Persediaan | Stock Opnames | StockOpname | `ClipboardDocumentList` |
| Persediaan | Purchase Returns | PurchaseReturn | `ArrowUturnLeft` |
| Pemasaran | Flash Sales | FlashSale | `Bolt` |
| Pemasaran | Product Bundles | ProductBundle | `Gift` |
| Pengguna | Users | User | `Users` |

### Widgets (9)

| Widget | Type | Sort | Colspan | Fungsi |
|---|---|---|---|---|---|
| **EnhancedStatsOverviewWidget** | Stats | 1 | full | 8 stat cards: daily orders/sold/revenue/net profit + new customers/repeat/AOV/conversion rate — semuanya clickable menuju resource page dengan filter |
| **FinanceOverview** | Stats | 2 | full | 4 stat cards (all-time): Total Pendapatan, Total Pengeluaran, Laba Bersih, Total Pesanan |
| **RevenueChart** | Chart | 3 | 6 | Line chart pendapatan 6 bulan terakhir (per bulan) |
| **RevenueChartWidget** | Chart | 3 | 6 | Line chart pendapatan 30 hari terakhir (per hari) |
| **TopProductsTableWidget** | Table | 4 | 4 | Table top 10 produk terlaris (nama + terjual) |
| **TopCategoriesTableWidget** | Table | 4 | 4 | Table top 10 kategori terlaris (nama + terjual) |
| **TopCashiersTableWidget** | Table | 4 | 4 | Table top 10 kasir (nama + order + pendapatan) |
| **RecentOrdersWidget** | Table | 5 | full | Table 10 pesanan terakhir dengan status badges |

### Custom Table Filters
- **ProductsTable**: `SelectFilter::make('stock')` — "Stok Menipis (≤ 5)" dan "Habis (0)" dengan custom `query()` callback
- **UsersTable**: `SelectFilter::make('role')` dan `SelectFilter::make('segment')` untuk filter user berdasarkan role dan segmen
- **StockHistoriesTable**: Type badges untuk `manual`, `order`, `pos`, `adjustment`, `opname`, `return`, `refund`
- **OrdersTable**: `Filter::make('hari_ini')` (`whereDate('created_at', today())`) dan `Filter::make('menunggu')` (`whereIn('status', ['pending','processing'])`) — toggle filter

### Phase 2 Resource Notes
- **Suppliers**: `SupplierForm` → TextInput fields (name, contact_person, phone, email, address); `SuppliersTable` → name, contact person, phone, active orders count
- **Purchase Orders**: `PurchaseOrderForm` → Select supplier (relationship), order_number, status, ordered_at, received_at, notes; **Repeater items** → Select product (via `->options()` NOT `->relationship()`), product_name hidden, SKU disabled, qty, unit_cost, subtotal disabled; Ringkasan section → subtotal, tax, total (disabled/dehydrated, auto-calculated in `mutateFormDataBeforeCreate/Save`)
- **Stock Opname**: `StockOpnameForm` → opname_number (auto-generated), status (draft/completed), notes; Repeater items → Select product (via `->options()`), system_stock disabled (filled on select via `afterStateUpdated`), physical_stock input, difference disabled/color-coded; `EditStockOpname::afterSave()` adjusts stock only when status completed + wasChanged, uses `saveQuietly()` to avoid double history
- **Purchase Returns**: `PurchaseReturnForm` → return_number, supplier, PO (nullable), status, reason, notes; Repeater items → same pattern as PO; Ringkasan → total_amount disabled/dehydrated; `EditPurchaseReturn::afterSave()` restores stock when completed/received, uses `saveQuietly()`
- **Flash Sales**: `FlashSaleForm` → name, slug (auto), desc, start/end time, status, is_active, banner; Repeater products → Select product (via `->options()`, NOT `->relationship()`), discount_type, discount_value, max_qty
- **Product Bundles**: `ProductBundleForm` → name, slug (auto), desc, bundle_price, is_active, start/end time, image; Repeater products → Select product (via `->options()`), quantity

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
- **CSS pre-compiled**: Filament 5.6.7 `theme.css` hanya berisi class yang dipakai komponen Filament. Standard Tailwind utilities (`text-gray-500`, `bg-gray-50`, `grid`, `p-4`, `font-bold`) **TIDAK ADA**. Di custom Blade views, pakai inline styles + CSS variables (`var(--gray-500)`, `var(--success-600)`) atau komponen Filament (`<x-filament::section>`, `<x-filament::button>`).
- **`TableWidget` grouped queries**: Untuk aggregated data (SUM, COUNT, GROUP BY), SELECT harus include `MAX(table.id) as id` sebagai record key. `getTableRecordKey()` expects string, null akan throw TypeError.
- **`<x-filament::table>` TIDAK ADA**: Tidak ada Blade component untuk table di Filament 5.6. Tabel render via PHP `Table` class. Untuk custom views, pakai `<table>` HTML biasa.
- **`<x-filament-widgets::widget>` wrapper**: Hanya `<div>` dengan grid column class (`fi-wi-widget`) — TIDAK memberikan card styling. Card look berasal dari inner component CSS (table component, dll).

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
- Keyboard shortcuts: F2 search, F4 checkout, F8 hold, Ctrl+B barcode, Enter checkout, Esc reset
- Loading state
- Stock warning: merah kalau ≤ 5
- Change/kurang display setelah checkout
- Success state dengan tombol print
- Shift status indicator (green pulse) + Buka/Tutup modals
- Kas Keluar modal (amount, category, description)
- Barcode scanner input with Ctrl+B shortcut
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
- **Fields**: store_address, phone, whatsapp, email, instagram, facebook, tiktok, bank_accounts (Repeater), **ppn_enabled (Toggle)**, **ppn_percentage (TextInput, suffix %, default 11)**, **logo**, **favicon**, **whatsapp_text**, **smtp fields** (host, port, username, password, encryption, from), **google_analytics_id**, **facebook_pixel_id**, **head_scripts**, **body_scripts**
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

## Storefront Views (10+)

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
- **EnhancedStatsOverviewWidget**: 8 stat cards (daily orders/sold/revenue/net profit + new customers/repeat/AOV/conversion rate) — semuanya clickable menuju resource page dengan filter
- **FinanceOverview**: Total Pendapatan, Total Pengeluaran, Laba Bersih, Total Pesanan — dari seluruh waktu
- **RevenueChart**: Line chart pendapatan 6 bulan terakhir (per bulan)
- **RevenueChartWidget**: Line chart pendapatan 30 hari terakhir
- **TopProductsTableWidget**: Table top 10 produk terlaris (nama + terjual)
- **TopCategoriesTableWidget**: Table top 10 kategori terlaris (nama + terjual)
- **TopCashiersTableWidget**: Table top 10 kasir (nama + order + pendapatan)
- **RecentOrdersWidget**: Table 10 pesanan terakhir dengan status badges

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
- **UsersTable**: `SelectFilter::make('role')` dan `SelectFilter::make('segment')` untuk filter user
- **StockHistoriesTable**: Type badges untuk `manual`, `order`, `pos`, `adjustment`, `opname`, `return`, `refund`

### 24. Wishlist ❤️
- Model `Wishlist` + migration (unique user_id + product_id)
- `User` model: `wishlistProducts()` BelongsToMany
- Routes: `wishlist.toggle` (POST JSON) + `wishlist.index` (paginated view)
- Storefront: Alpine.js heart button di product-card, wishlist count badge di navbar
- Empty state di halaman wishlist

### 25. Brand (Merek)
- Model `Brand` + migration (`brands` table + `brand_id` di products)
- Filament Resource (BrandResource) di group Produk
- 9 brand seeds (Samsung, Apple, Nike, Adidas, dll)
- Products table punya brand column

### 26. Voucher / Kupon
- Model `Coupon` dengan methods: `isValid()`, `canUseBy()`, `calculateDiscount()`
- Migrations: `coupons` + `coupon_user` pivot + `coupon_id`/`discount` di orders
- Filament Resource (CouponResource) di group Keuangan
- AJAX validation di checkout (`applyCoupon()`)
- Discount applied server-side di `placeOrder()` (prevents manipulation)
- 3 seed coupons: `HELLO10` (10% max Rp50k), `FLAT50` (Rp50k min Rp200k), `GRATIS` (Rp25k)

### 27. Reorder (Beli Lagi)
- Route `POST /orders/{order}/reorder`
- Validasi ownership + status delivered
- Merge items ke session cart, skip inactive/out-of-stock
- "Beli Lagi" button di order-detail + orders list

### 28. Barcode Scanner (POS)
- `POST /pos/scan` — lookup by SKU, add to POS cart
- Barcode input field + Ctrl+B keyboard shortcut
- Success reloads page, failure shows inline error

### 29. Reports (Admin)
- Custom Filament page (`Reports.php`) di group Keuangan
- Period filter (today/week/month/year/custom + date range)
- 4 summary stat cards (orders/revenue/products sold/AOV)
- Laba/rugi section (revenue vs expense vs profit)
- Produk Terlaris table (top 20 by qty)
- Kategori Terlaris table
- Export CSV button (streamed via ReportController with UTF-8 BOM)
- Print button
- **CSS via inline styles + `<x-filament::section>`** (standard Tailwind utilities tidak tersedia di Filament's pre-compiled theme.css)

### 30. Shift Kasir
- Migration `shifts` table + `shift_id` pada orders
- Model `Shift` with `isOpen()`, `totalOrders()`, `totalRevenue()`
- PosController: `openShift`, `closeShift`, `shiftHistory`
- POS view: shift status indicator (green pulse) + Buka/Tutup modals
- Shift history paginated view
- `shift_id` recorded on checkout

### 31. Dashboard Kinerja (Laba Bersih & Top Products/Kategori/Kasir)
- **EnhancedStatsOverviewWidget**: Row 1 (Hari Ini: Pesanan, Terjual, Pendapatan, Laba Bersih), Row 2 (Customer Baru, Repeat, AOV, Conversion Rate)
- 3 TableWidgets: TopProductsTableWidget, TopCategoriesTableWidget, TopCashiersTableWidget — masing-masing `columnSpan=4`, menggunakan Eloquent grouped queries (MAX id untuk record key)
- Old TopProductsWidget + blade view dihapus

### 32. Product Attributes (Color, Size, Material)
- Migration `product_attributes` table
- Model `ProductAttribute` with `scopeOfType()`
- Product: HasMany relationship + `getAttributeByType()` helper
- Filament Repeater di product form (type select + value + label + sort_order)
- Shown di product table column + product-detail page

### 33. Print Barcode (EAN/QR/Code128)
- Package: `milon/barcode`
- `BarcodeController` (index, generate, generateForProduct)
- 2 Blade views: selection form + print-optimized labels
- Inline SVG barcode generation + auto-print
- Barcode action button di ProductsTable

### 34. Compare Product
- Session-based: `session('compare', collect())` max 4 items
- `compareToggle` (POST JSON) + `compareIndex` (comparison table)
- Alpine.js toggle button di product-card, dispatches `compare-updated` event
- Navbar badge with count
- Full comparison: price, stock, SKU, weight, category, rating, attributes, description

### 35. Notification Center
- Migration `notifications` table
- Model `Notification` with `createForUser()`, `createForAdmins()`, `markAsRead()`, `scopeUnread()`
- Auto-notify on: placeOrder (user + admins), paymentUpload, confirmReceived, shipped, refund
- Bell icon with Alpine.js live unread count in navbar
- Notification list view with color-coded types + pagination + empty state
- "Notifikasi" nav link in account sidebar

### 36. Kas Keluar (Cash Outflow from Shift)
- Migration `shift_expenses` table
- Model `ShiftExpense`, Shift `HasMany` expenses
- POS: Kas Keluar modal (amount, category, description)
- `closeShift()` deducts total expenses from expected balance
- Shift history view shows expenses column

### 37. Refund (Stock Balik)
- Route `POST /orders/{order}/refund` (admin only)
- DB transaction: restore stock per item + StockHistory entries (type `refund`)
- Order status → `refunded`, payment status → `refunded`
- Red "Diretur" badge di orders list + order detail
- Admin refund button (visible when processing/shipped/delivered + paid)

### 38. Keyboard Shortcuts (POS)
- F2: focus search
- F4: checkout
- F8: hold
- Ctrl+B: barcode scanner
- Enter: checkout
- Esc: reset
- Keyboard hint bar at bottom of product grid

### 39. Settings: Logo, WhatsApp, SMTP, Google Analytics, Facebook Pixel
- ManageSettings updated: Toko (logo upload, favicon, whatsapp), SMTP/Email (host, port, username, password, encryption, from), SEO & Analytics (GA ID, FB Pixel, head/body scripts)
- Dynamic favicon in all 3 layouts
- GA + FB Pixel + custom scripts injected in `<head>`
- Dynamic logo in navbar from setting
- WhatsApp floating button (fixed bottom-right, green)
- `Setting::get()` wrapped in try/catch for QueryException

### 40. Digital Product (Downloadable File)
- Migration: `is_digital`, `digital_file`, `license_key` on products + `order_downloads` table
- Model `OrderDownload` with `canDownload()` (max 5) + `recordDownload()`
- Filament form section (Toggle + FileUpload + license key Textarea)
- Route `GET /orders/{order}/download/{product}` — validates ownership, payment, download limit
- Download button in order-detail for paid digital products

### 41. Dashboard Layout Fixes
- All widgets have explicit `columnSpan` (no inherited defaults)
- Unique sort values per row: stats=1, overview=2, charts=3, top*=4, recent=5
- Unused widgets removed: AccountWidget
- RevenueChart + RevenueChartWidget: side by side (colspan=6 each), `fill => true` for both
- Filament CSS pre-compiled: hanya class dari komponen Filament yang tersedia — custom Tailwind utilities (text-gray-500, bg-gray-50, dll) TIDAK ADA

### 42. Reports Page Fix
- Rewritten with `<x-filament::section>` (card container) + inline styles + CSS variables (`var(--gray-500)`, `var(--success-600)`)
- Tombol pakai `<x-filament::button>` (`color="success"`, `color="gray"`)
- Tabel pakai `<table>` HTML biasa (tidak ada `<x-filament::table>` di Filament 5.6)

### 43. Top Products Widget (Refactor)
- Old custom `TopProductsWidget.php` + Blade view **dihapus**
- Diganti 3 `TableWidget` classes: TopProductsTableWidget, TopCategoriesTableWidget, TopCashiersTableWidget
- Menggunakan Eloquent grouped queries (bukan `->data()` — method tidak ada di Filament 5)
- Wajib include `MAX(table.id) as id` di SELECT untuk record key (`getTableRecordKey()` expects string)

### 44. Supplier & Purchase Order (PO)
- Model `Supplier` + migration (SoftDeletes) dengan Filament resource CRUD
- Model `PurchaseOrder` + `PurchaseOrderItem` dengan Repeater items
- PO status: draft → ordered → partial → received → cancelled
- Auto-calculate subtotal/total via `mutateFormDataBeforeCreate/Save`
- History stok tercatat via Product `recordStockHistory()` saat PO diterima
- Navigation group: Persediaan

### 45. Stock Opname
- Model `StockOpname` + `StockOpnameItem` dengan Repeater items
- System stock diisi otomatis saat pilih produk (via `afterStateUpdated`)
- Difference dihitung otomatis (system - physical) dengan color-coded display
- `EditStockOpname::afterSave()`: hanya adjust stok saat status berubah jadi completed
- Menggunakan `saveQuietly()` + explicit `recordStockHistory()` (type `opname`) untuk mencegah double entry
- Navigation group: Persediaan

### 46. Purchase Return (Retur ke Supplier)
- Model `PurchaseReturn` + `PurchaseReturnItem` dengan Repeater items
- Relasi ke `Supplier` + optional `PurchaseOrder`
- Status: draft → submitted → received → completed / rejected
- `EditPurchaseReturn::afterSave()`: restore stok saat status jadi completed/received
- Sama seperti Stock Opname: `saveQuietly()` + explicit `recordStockHistory()` (type `return`)
- Navigation group: Persediaan

### 47. Flash Sale
- Model `FlashSale` + pivot `flash_sale_products` (BelongsToMany)
- Discount per produk: persen atau nominal; max_qty (kuota) + sold_count tracking
- CRUD via Filament: Repeater products (Select pakai `->options()`, BUKAN `->relationship()`)
- Discount logic di storefront: otomatis terapkan di product detail / product-card
- Navigation group: Pemasaran

### 48. Product Bundle
- Model `ProductBundle` + pivot `bundle_products` (BelongsToMany)
- Harga bundle khusus + total original price (auto-calc)
- CRUD via Filament: Repeater products dengan quantity
- Display di storefront: card bundle dengan daftar produk + total hemat
- Navigation group: Pemasaran

### 49. Tracking Event (Lacak Pengiriman)
- Model `TrackingEvent` dengan `$appends=['status_label']`
- Order `trackingEvents()` HasMany
- Filament Repeater di OrderForm: status, location, description, occurred_at
- Tampil di order detail storefront sebagai timeline
- Status labels: pending → diproses → dikirim → sampai tujuan → delivered

### 50. Points System
- Kolom `points` (default 0) di users table
- Model `PointTransaction` dengan MorphTo relasi (reference → order/review/etc)
- Methods di User: `addPoints()`, `redeemPoints()`, `getPointsBalance()`
- StoreController checkout(): display points + redeem checkbox (max 50% total)
- StoreController placeOrder(): deduct redeemed points, add earned points (10% of total, via confirmReceived)
- StoreController confirmReceived(): `auth()->user()->addPoints(floor($order->total * 0.1))`
- PointTransaction history di admin via Filament resource (ListPointTransactions, read-only)
- Navigation group: Pengguna
- Old custom `TopProductsWidget.php` + Blade view **dihapus**
- Diganti 3 `TableWidget` classes: TopProductsTableWidget, TopCategoriesTableWidget, TopCashiersTableWidget
- Menggunakan Eloquent grouped queries (bukan `->data()` — method tidak ada di Filament 5)
- Wajib include `MAX(table.id) as id` di SELECT untuk record key (`getTableRecordKey()` expects string)

---

## Gate & Middleware

- `Gate::define('access-pos', fn ($user) => in_array($user->role, ['admin', 'cashier']))` di `AppServiceProvider::boot()`
- `Gate::define('admin', fn ($user) => $user->role === 'admin')` di `AppServiceProvider::boot()`
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
- **`$product->stock += X; $product->saveQuietly()`**: Untuk menghindari double stock history di manual stock adjustment, gunakan `saveQuietly()` (tidak trigger saved event) diikuti `recordStockHistory()` eksplisit. Pattern ini dipakai di Stock Opname dan Purchase Return afterSave.
- **PHP property type**: Override parent static properties membutuhkan exact type match (`string | BackedEnum | null` untuk `$navigationIcon`, `bool` untuk `$shouldRegisterNavigation`, `?int` untuk `$sort`).
- **XSS safety**: Semua `old()` values di Alpine.js pakai `@json()` (bukan raw Blade).
- **`isRecentlyCreated` TIDAK valid** — properti Eloquent yang benar adalah `wasRecentlyCreated`.
- **Static property override**: Di Filament versi baru, beberapa parent properties seperti `$heading` di ChartWidget adalah **non-static**. Override sebagai `protected ?string`, bukan `protected static ?string`.
- **Union type spaces**: `int | string | array` dianggap style issue oleh Pint — harus `int|string|array`.
- **Auto-dot via event delegation**: `extraInputAttributes(['oninput' => 'formatRupiah(this)'])` gagal karena fungsi tidak global. Solusi: capture-phase `document.addEventListener('input', ...)` dengan filter `wire:model` regex. JANGAN dispatch `input` event dari handler (avoid recursion dengan `wire:model`).
- **Boolean settings**: Di `ManageSettings::save()`, `Toggle` mengembalikan `bool` PHP. Konversi manual ke `"1"`/`"0"` via `is_bool($value)` sebelum `updateOrCreate`.
- **PPN notes parsing**: Format `PPN {rate}%: Rp {amount}` — parse via regex `/^PPN (\d+)%: Rp ([\d.]+)$/` di receipt views (POS + storefront).
- **`(int) "3.250.000"` returns `3`**: PHP stops parsing integer at the first non-digit character. Always strip dots before cast: `(int) str_replace('.', '', $value)`.
- **`$get()` returns dotted string** during real-time typing in Filament callbacks (from formatRupiah JS). Strip dots before any numeric operation.
- **PPN tax base**: Indonesian PPN is on DPP (Dasar Pengenaan Pajak = price after ALL discounts). Use `max(0, $subtotal - $discountAmount)`, NOT just `$subtotal`.
- **BelongsToMany Repeaters**: `->relationship()` does NOT work in Filament 5.6. Use `->options()` on Select + manual sync in `mutateFormDataBeforeCreate/Save`/after hooks.
- **FK cascadeOnDelete**: Deleting a parent (user, supplier) cascades to orders, shifts, expenses, purchase records. Use `nullOnDelete` + nullable FK for business-critical data.
- **API rate limiting**: `bootstrap/app.php` applies `ThrottleRequests:60,1` (60 req/min) to all API routes. If mobile gets 429 errors, check if too many requests are being made.
- **Site audit checklist**: Check migrations (FK/indexes/defaults), controllers (null guards, PPN calc, transaction boundaries), views (alt text, hardcoded URLs), routes (GET|POST where POST only is needed), Filament (SoftDeletes widgets, navigation groups, sort values).

---

## Git & GitHub

- **Remote**: `origin → https://github.com/wforyu/hello-store.git`, branch `master`
- Multiple commits up to latest — includes all Phase 1 + Phase 1.5 features
- Push command: `git push origin master`

---

## Testing

- Unit tests: `PHPUnit\Framework\TestCase` (tanpa app boot)
- Feature tests: `Tests\TestCase` (full app boot), SQLite `:memory:`
- Semua feature tests pakai `RefreshDatabase` (kecuali ExampleTest)
- 25 tests, 61 assertions — semuanya pass
- Pint clean: 300 files, 0 issues
- Command: `composer test` (config:clear lalu artisan test)

---

## Bug Fixes & Code Audit (2026-07-08 + 2026-07-15)

Total **30 bugs** ditemukan dan diperbaiki dalam 3 ronde audit kode menyeluruh:

### StoreController — 4 Fixes
| # | File:Ln | Bug | Severity | Date |
|---|---|---|---|---|
| 1 | `StoreController.php:271` | PPN checkout preview mismatch — Alpine.js `ppnAmount` getter pakai `subtotal` tanpa diskon, beda sama `placeOrder()` yg pake `subtotal - discount` (DPP) | Medium | 07-08 |
| 2 | `StoreController.php:685,693` | `$validated['comment']` undefined key risk pas API request tanpa field `comment` | Low | 07-08 |
| 3 | `StoreController.php:303` | `(int)` truncate shipping cost (RajaOngkir kadang ada decimal) jadi pake `(float)` | Low | 07-08 |
| 4 | `StoreController.php:51` | Flash sale filter (`?flash_sale=`) gak diimplementasi di query `products()` | Medium | 07-15 |

### PosController — 8 Fixes
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 5 | `PosController.php:95` | `add()` gak update `stock` field pas re-add item existing (stale stock display) | Medium |
| 6 | `PosController.php:184` | `ppn_enabled` admin setting gak dicek server-side — client kirim `ppn: true` tetap diproses walau setting disabled | Medium |
| 7 | `PosController.php:186` | PPN base kurang `max(0, ...)` guard (defensive) | Low |
| 8 | `PosController.php:207` | `$activeShift` double query di dalam DB transaction | Low |
| 9 | `PosController.php:300` | Hold ID confusing `$holds->max('id') + 1 ?: 1` diganti `($holds->max('id') ?? 0) + 1` | Low |
| 10 | `PosController.php:439` | `openShift()` gak validasi input `opening_balance` — nambah `$request->validate(...)` | Medium |
| 11 | `PosController.php:527` | **`$shiftExpense->shift->user_id` crash** — shift pake `nullOnDelete`, kalo user dihapus `->shift` jadi null | **High** |
| 12 | `PosController.php:80` | `add()` gak validasi request — nambah validation `product_id` + `quantity` | Low |

### Models — 3 Fixes
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 13 | `Coupon.php:62` | **`usage_per_user = null` bikin kupon gak pernah bisa dipake** — `$usageCount < null` selalu false. Nambah guard `if ($this->usage_per_user === null) return true` | **High** |
| 14 | `User.php:47` | `redeemPoints()` gak guard negative balance — nambah `min($points, $this->points)` + throw exception | Medium |
| 15 | `Product.php:129` | Missing `flashSales()` dan `bundles()` BelongsToMany relationships — nambah relasi inverse | Medium |

### Blade Views — 1 Fix
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 16 | `shifts.blade.php:34` | `$shift->user->name` crash kalo user dihapus (FK `nullOnDelete`) — pake `$shift->user?->name ?? 'Akun dihapus'` | Medium |

### Frontend (Web) — 1 Fix
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 17 | `checkout.blade.php:280` | Alpine.js `ppnAmount` getter pake `this.subtotal` bukan `this.subtotal - this.discount` — PPN preview overestimate pas ada kupon | Medium |

### API Security & Logic — 3 Fixes (07-15)
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 18 | `Api/OrderController.php:127` | PPN DPP mismatch — `discount` gak dipake di mobile checkout PPN calc, beda sama web | Medium |
| 19 | `Api/OrderController.php:62` | **IDOR vulnerability** — gak ada ownership check `address_id` pas bikin order | **High** |
| 20 | `Api/ProductController.php` | Search gak include SKU — product gak bisa dicari berdasarkan SKU di mobile | Medium |

### Filament Admin — 6 Fixes (07-15)
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 21 | `EditProduct.php:50` | Stock adjustment `increment()` + `recordStockHistory()` tanpa `$product->refresh()` — in-memory `stock` stale | Medium |
| 22 | `StockHistoriesTable.php` | Missing type badges: `opname`, `return`, `refund` — semua type gak punya label/warna | Medium |
| 23 | `UsersTable.php` | Missing `SelectFilter` untuk role dan segment — gak bisa filter user | Medium |
| 24 | `CouponResource.php` | `usage_per_user` gak nullable — helperText bilang "Kosongkan untuk unlimited" tapi field required | Medium |
| 25 | `ManageSettings.php:145,151` | Logo/Favicon `FileUpload` gak ada `disk('public')` — upload gagal di production | **High** |

### Infrastructure — 1 Fix (07-15)
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 26 | `bootstrap/app.php` | No API rate limiting — endpoint `/login` `/register` gak ada throttle | Medium |

### Mobile Code Quality — 7 Fixes (07-15)
| # | File:Ln | Bug | Severity |
|---|---|---|---|
| 27 | `SearchScreen.js:47` | Debounce timeout gak di-clear on unmount — memory leak | Medium |
| 28 | `ProfileScreen.js` | Stats gak refresh pas screen di-focus — data stale setelah navigate back | Medium |
| 29 | `NotificationScreen.js` | Gak ada "Mark All Read" button — user harus tap satu-satu | Low |
| 30 | `CartScreen.js` | Gak ada pull-to-refresh | Low |

### Mobile Code Deduplication (07-15)
- Created `mobile/src/utils.js` — shared `formatPrice`, `STATUS_COLORS`, `STATUS_LABELS`
- Refactored 7 screens (HomeScreen, ProductDetail, Search, Wishlist, Checkout, Cart, OrderDetail, OrderList) ke shared utilities
- Menghilangkan duplicate `formatPrice` definitions di semua screens

### Catatan Penting
- **Config cache**: Jangan `config:cache` pas running tests — cached config override env testing (SQLite in-memory). Always `config:clear` before `php artisan test`.
- **DB seeder**: `DatabaseSeeder` bisa dimodif sementara (comment product seeding) kalo mau reset DB tanpa produk — tinggal `migrate:fresh --seed`. Jangan lupa direstore setelahnya.
- **`php artisan optimize`** udah jalan — config, events, routes, views, blade-icons, filament all cached.
- **Tests: 25/25 pass, Pint: 300 files clean** setelah semua fix diterapkan.

---

## Mobile App (React Native / Expo)

### Info
- **Framework**: Expo SDK 57, React Native 0.86, `mobile/` folder
- **Bundle ID**: `com.hellostore.app`
- **API**: Token-based auth via SecureStore + Axios `Bearer` header
- **API_URL**: `mobile/src/config.js` → `API_URL` (ubah ke domain production saat deploy)
- **Splash**: Custom JS splash (HS logo + slogan) di `App.js` — native splash = solid amber `#FEF3C7`
- **CustomAlert**: `src/components/CustomAlert.js` + `src/context/AlertContext.js` — GUNAKAN INI, jangan `Alert.alert()`

### CRITICAL: Build Rules

| Rule | Detail |
|---|---|
| **JANGAN `expo prebuild --clean`** | Regenerate android folder dari nol — splash fixes hilang, splash PNG broken balik lagi |
| **PAKAI `gradlew clean && gradlew assembleRelease`** | Full clean build tanpa regenerate android folder — JS bundle di-rebuild otomatis |
| **Incremental build** | `gradlew assembleRelease` saja (cepat ~26 detik, tapi JS mungkin ga di-rebuild) |
| **Kapan boleh prebuild** | Hanya saat tambah/hapus native dependency atau Expo plugin |
| **Setelah prebuild** | WAJIB apply splash fix lagi (hapus PNG, fix colors.xml, buat drawable XML) |

### Image URL Convention (CRITICAL)
- **Backend WAJIB pakai `/storage/` prefix** untuk semua image URLs
- **JANGAN pakai `asset()`** — generate `http://localhost:8000/storage/...` yang broken di mobile
- Mobile `getImageUrl()` di `config.js` otomatis prepend `API_URL` ke relative path
- Contoh benar: `'/storage/'.$img->image` → mobile jadi `https://domain.com/storage/products/abc.jpg`
- Contoh salah: `asset('storage/'.$img->image)` → `http://localhost:8000/storage/products/abc.jpg` ❌

### Splash Screen — Dilarang Diotak-atik
- Native splash: solid amber `#FEF3C7` via `drawable/splashscreen_logo.xml` + `colors.xml`
- JS splash: HS logo + "Belanja Mudah, Harga Terjangkau" di `App.js` (CustomSplash component)
- Keduanya seamless karena warna background sama
- Kalau ada double splash = `expo prebuild` baru dijalankan → apply fix lagi

### Mobile Bug Fixes (9 total)

| # | Bug | Fix | File |
|---|---|---|---|
| 1 | Cart ga ilang setelah order | Hapus cart di backend + frontend `setCart(null)` | `OrderController.php`, `CheckoutScreen.js` |
| 2 | Bukti bayar ga muncul | `formatOrder()` pakai `/storage/` bukan `asset()` | `OrderController.php` |
| 3 | Stats profile ga load | Fix JSON path `data.data?.meta?.total` | `ProfileScreen.js` |
| 4 | Gambar produk ga tampil | Prepend `/storage/` di semua image URL | `ProductController.php` |
| 5 | Gambar detail kebesaran | `resizeMode="contain"` + height 350px | `ProductDetailScreen.js` |
| 6 | Notifikasi kosong | Override `notifications()` + fix field `body` | `User.php`, `NotificationScreen.js` |
| 7 | Ikon kategori default 📦 | Fuzzy matching 21 keyword arrays | `HomeScreen.js` |
| 8 | Splash double | Hapus PNG, amber color, XML layer-list | `colors.xml`, `splashscreen_logo.xml` |
| 9 | Upload bukti bayar error | `Content-Type: multipart/form-data` untuk FormData | `OrderDetailScreen.js` |
