<div style="max-width: 900px; margin: 0 auto; padding: 24px;">

    {{-- Search Bar --}}
    <div style="margin-bottom: 24px;">
        <div style="position: relative;">
            <input
                type="text"
                id="help-search"
                placeholder="Cari panduan... (contoh: produk, pesanan, flash sale)"
                style="width: 100%; padding: 14px 16px 14px 44px; border: 2px solid var(--gray-600); border-radius: 12px; background: var(--gray-800); color: var(--gray-100); font-size: 15px; outline: none; box-sizing: border-box;"
                oninput="filterGuides()"
            />
            <svg style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: var(--gray-400);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    {{-- Quick Nav --}}
    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px;">
        <a href="#produk" class="help-nav-btn">Produk</a>
        <a href="#kategori" class="help-nav-btn">Kategori</a>
        <a href="#pesanan" class="help-nav-btn">Pesanan</a>
        <a href="#pembayaran" class="help-nav-btn">Pembayaran</a>
        <a href="#stok" class="help-nav-btn">Stok & Persediaan</a>
        <a href="#flash-sale" class="help-nav-btn">Flash Sale</a>
        <a href="#bundle" class="help-nav-btn">Bundle</a>
        <a href="#promo" class="help-nav-btn">Promo & Slider</a>
        <a href="#keuangan" class="help-nav-btn">Keuangan</a>
        <a href="#pengguna" class="help-nav-btn">Pengguna</a>
        <a href="#pengaturan" class="help-nav-btn">Pengaturan</a>
        <a href="#toko" class="help-nav-btn">Tampilan Toko</a>
    </div>

    <style>
        .help-nav-btn {
            padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
            background: var(--gray-700); color: var(--gray-200); text-decoration: none;
            transition: all 0.2s;
        }
        .help-nav-btn:hover { background: var(--primary-600); color: #fff; }
        .help-card {
            border: 1px solid var(--gray-600); border-radius: 12px; padding: 20px;
            margin-bottom: 16px; background: var(--gray-800); transition: opacity 0.3s;
        }
        .help-card.hidden { display: none; }
        .help-card h3 { font-size: 17px; font-weight: 700; color: var(--gray-100); margin: 0 0 6px 0; }
        .help-card h4 { font-size: 14px; font-weight: 600; color: var(--primary-400); margin: 12px 0 6px 0; }
        .help-card p, .help-card li { font-size: 14px; color: var(--gray-300); line-height: 1.7; }
        .help-card ul { margin: 4px 0 0 0; padding-left: 20px; }
        .help-card li { margin-bottom: 4px; }
        .help-tag {
            display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px;
            font-weight: 600; margin-right: 6px;
        }
        .help-tag-green { background: #065f4620; color: #34d399; }
        .help-tag-blue { background: #1e40af20; color: #60a5fa; }
        .help-tag-yellow { background: #92400e20; color: #fbbf24; }
        .help-tag-red { background: #991b1b20; color: #f87171; }
        .help-tag-purple { background: #6b21a820; color: #c084fc; }
        .help-tip {
            background: #1e3a5f; border-left: 3px solid var(--primary-400); padding: 10px 14px;
            border-radius: 0 8px 8px 0; margin: 8px 0; font-size: 13px; color: var(--gray-200);
        }
    </style>

    <script>
    function filterGuides() {
        var query = document.getElementById('help-search').value.toLowerCase();
        document.querySelectorAll('.help-card').forEach(function(card) {
            var text = card.textContent.toLowerCase();
            var keywords = card.getAttribute('data-keywords') || '';
            var match = text.includes(query) || keywords.includes(query);
            card.classList.toggle('hidden', !match);
        });
    }
    </script>

    {{-- ============================================ --}}
    {{-- PRODUK --}}
    {{-- ============================================ --}}
    <div id="produk" class="help-card" data-keywords="produk produk tambah edit hapus gambar sku stok harga deskripsi varian">
        <h3>Produk</h3>
        <span class="help-tag help-tag-green">Produk</span>
        <p>Kelola seluruh produk yang dijual di toko.</p>

        <h4>Cara Menambah Produk</h4>
        <ul>
            <li>Buka menu <strong>Produk</strong> lalu klik <strong>"Tambah Produk"</strong></li>
            <li>Isi nama produk — slug akan otomatis ter-generate</li>
            <li>Isi harga jual, harga banding (opsional, untuk coret), stok, SKU, berat</li>
            <li>Pilih kategori dan brand</li>
            <li>Upload gambar produk — bisa multiple. Gambar pertama jadi gambar utama</li>
            <li>Tulis deskripsi produk — mendukung format kaya (bold, list, dll)</li>
            <li>Toggle <strong>Aktif</strong> untuk menampilkan produk di toko</li>
            <li>Toggle <strong>Produk Digital</strong> kalau produknya file download (bukan fisik)</li>
        </ul>

        <h4>Varian Produk</h4>
        <ul>
            <li>Scroll ke bawah, klik <strong>"Tambah Varian"</strong></li>
            <li>Isi nama varian (contoh: "Merah - XL")</li>
            <li>Set harga, stok, berat, dan SKU per varian</li>
            <li>Tambah atribut varian: tipe (warna/ukuran/bahan), nilai, label</li>
        </ul>

        <h4>SEO & Metadata</h4>
        <ul>
            <li><strong>Meta Title</strong>: judul untuk Google (maks 70 karakter)</li>
            <li><strong>Meta Description</strong>: deskripsi untuk Google (maks 160 karakter)</li>
        </ul>

        <div class="help-tip">
            <strong>Tips:</strong> Gunakan filter stok di tabel untuk melihat produk yang stoknya menipis (<=5) atau habis (0). Klik kolom stok untuk mengaktifkan filter.
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- KATEGORI --}}
    {{-- ============================================ --}}
    <div id="kategori" class="help-card" data-keywords="kategori kategori grup filter urut">
        <h3>Kategori</h3>
        <span class="help-tag help-tag-green">Kategori</span>
        <p>Atur kelompok kategori produk untuk memudahkan pelanggan menemukan barang.</p>

        <h4>Cara Membuat Kategori</h4>
        <ul>
            <li>Buka menu <strong>Kategori</strong> di sidebar</li>
            <li>Klik <strong>"Tambah Kategori"</strong></li>
            <li>Isi nama kategori — slug otomatis ter-generate</li>
            <li>Pilih <strong>Parent</strong> kalau ini sub-kategori</li>
            <li>Upload gambar kategori (opsional)</li>
            <li>Atur urutan tampil dengan field <strong>Sort Order</strong></li>
        </ul>

        <h4>Kategori Bertingkat</h4>
        <ul>
            <li>Contoh: Elektronik → Handphone → Aksesoris</li>
            <li>Parent kategori akan tampil sebagai grup di halaman toko</li>
        </ul>
    </div>

    {{-- ============================================ --}}
    {{-- PESANAN --}}
    {{-- ============================================ --}}
    <div id="pesanan" class="help-card" data-keywords="pesanan order status kirim terima bayar pending processing shipped delivered">
        <h3>Pesanan</h3>
        <span class="help-tag help-tag-blue">Pesanan</span>
        <p>Lihat dan kelola semua pesanan dari pelanggan.</p>

        <h4>Alur Pesanan</h4>
        <ul>
            <li><strong>Pending</strong> → Pelanggan belum upload bukti bayar</li>
            <li><strong>Processing</strong> → Bukti bayar ter-upload otomatis, pesanan sedang disiapkan</li>
            <li><strong>Shipped</strong> → Admin mengubah status ke "Dikirim" + isi nomor resi</li>
            <li><strong>Delivered</strong> → Pelanggan mengklik "Pesanan Diterima"</li>
        </ul>

        <h4>Yang Perlu Dilakukan Admin</h4>
        <ul>
            <li><strong>Pending:</strong> Tunggu pelanggan upload bukti bayar</li>
            <li><strong>Processing:</strong> Siapkan barang, set status ke "Shipped" + masukkan nomor resi + kurir</li>
            <li><strong>Shipped:</strong> Tunggu pelanggan konfirmasi penerimaan</li>
        </ul>

        <div class="help-tip">
            <strong>Tips:</strong> Gunakan filter "Hari Ini" dan "Menunggu" di tabel pesanan untuk melihat pesanan yang perlu segera ditindak.
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- PEMBAYARAN --}}
    {{-- ============================================ --}}
    <div id="pembayaran" class="help-card" data-keywords="pembayaran bayar transfer manual cod bukti transfer">
        <h3>Pembayaran</h3>
        <span class="help-tag help-tag-blue">Pembayaran</span>
        <p>Konfirmasi dan kelola pembayaran dari pelanggan.</p>

        <h4>Cara Kerja Pembayaran</h4>
        <ul>
            <li>Pelanggan upload bukti transfer → status otomatis jadi <strong>paid</strong></li>
            <li>Pesanan otomatis pindah ke <strong>processing</strong></li>
            <li>Admin bisa lihat detail: nama bank, atas nama, nomor rekening</li>
        </ul>

        <h4>Metode Pembayaran</h4>
        <ul>
            <li><strong>Manual Transfer</strong>: Pelanggan transfer lalu upload bukti</li>
            <li><strong>COD</strong>: Bayar di tempat (cash on delivery)</li>
        </ul>
    </div>

    {{-- ============================================ --}}
    {{-- STOK & PERSEDIAAN --}}
    {{-- ============================================ --}}
    <div id="stok" class="help-card" data-keywords="stok supplier purchase order po opname retur retur persediaan inventori restock">
        <h3>Stok & Persediaan</h3>
        <span class="help-tag help-tag-yellow">Persediaan</span>
        <p>Kelola stok barang, supplier, pemesanan, opname, dan retur.</p>

        <h4>Supplier</h4>
        <ul>
            <li>Buka menu <strong>Supplier</strong> untuk menambah data pemasok</li>
            <li>Isi nama, kontak, telepon, email, alamat supplier</li>
        </ul>

        <h4>Purchase Order (PO)</h4>
        <ul>
            <li>Buat PO baru → pilih supplier → pilih produk → isi jumlah + harga beli</li>
            <li>Ubah status: <strong>Draft → Dipesan → Diterima</strong></li>
            <li>Saat status "Diterima", stok produk otomatis bertambah</li>
        </ul>

        <h4>Stock Opname</h4>
        <ul>
            <li>Cocokkan stok fisik dengan stok sistem</li>
            <li>Pilih produk → stok sistem terisi otomatis → isi stok fisik</li>
            <li>Selisih terhitung otomatis (positif = kelebihan, negatif = kekurangan)</li>
            <li>Ubah status ke "Selesai" untuk menyesuaikan stok</li>
        </ul>

        <h4>Purchase Return (Retur ke Supplier)</h4>
        <ul>
            <li>Buat retur → pilih supplier → pilih produk → isi jumlah + alasan</li>
            <li>Ubah status ke "Selesai" untuk mengembalikan stok</li>
        </ul>

        <div class="help-tip">
            <strong>Tips:</strong> Semua perubahan stok tercatat otomatis di menu <strong>Riwayat Stok</strong>. Anda bisa melihat jejak setiap penyesuaian stok dari siapa dan kapan.
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- FLASH SALE --}}
    {{-- ============================================ --}}
    <div id="flash-sale" class="help-card" data-keywords="flash sale diskon waktu terbatas promo kilat harga spesial">
        <h3>Flash Sale</h3>
        <span class="help-tag help-tag-red">Flash Sale</span>
        <p>Atur promo diskon waktu terbatas untuk menarik pelanggan.</p>

        <h4>Cara Membuat Flash Sale</h4>
        <ul>
            <li>Klik <strong>"Tambah Flash Sale"</strong></li>
            <li>Isi nama, deskripsi, waktu mulai & selesai</li>
            <li>Upload banner promosi (opsional)</li>
            <li>Tambah produk: pilih produk → jenis diskon (%/Rp) → nilai diskon → kuota max</li>
        </ul>

        <h4>Detail Diskon Produk</h4>
        <ul>
            <li><strong>Persen (%)</strong>: diskon berupa persentase dari harga normal</li>
            <li><strong>Nominal (Rp)</strong>: diskon berupa potongan harga tetap</li>
            <li><strong>Maks. Terjual</strong>: kuota terjual per produk (0 = unlimited)</li>
        </ul>

        <div class="help-tip">
            <strong>Tips:</strong> Flash sale akan otomatis muncul di halaman depan toko sesuai waktu yang diatur. Setelah selesai, flash sale otomatis nonaktif.
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- BUNDLE --}}
    {{-- ============================================ --}}
    <div id="bundle" class="help-card" data-keywords="bundle paket combo hemat harga spesial gabungan">
        <h3>Product Bundle</h3>
        <span class="help-tag help-tag-purple">Bundle</span>
        <p>Jual beberapa produk sekaligus dengan harga paket yang lebih murah.</p>

        <h4>Cara Membuat Bundle</h4>
        <ul>
            <li>Klik <strong>"Tambah Bundle"</strong></li>
            <li>Isi nama, deskripsi, harga bundle</li>
            <li>Tambah produk ke bundle: pilih produk → jumlah</li>
            <li>Panel <strong>Estimasi Harga</strong> menampilkan perbandingan harga normal vs bundle</li>
        </ul>

        <h4>Perhitungan Harga</h4>
        <ul>
            <li><strong>Total Harga Normal</strong> = Σ (harga produk × jumlah)</li>
            <li><strong>Potongan</strong> = Total Normal - Harga Bundle</li>
            <li><strong>Diskon %</strong> = (Potongan / Total Normal) × 100</li>
            <li>Bundle akan otomatis tampil di halaman depan dan halaman bundle</li>
        </ul>
    </div>

    {{-- ============================================ --}}
    {{-- PROMO & SLIDER --}}
    {{-- ============================================ --}}
    <div id="promo" class="help-card" data-keywords="promo banner slider carousel iklan promosi social follow kalender">
        <h3>Promo & Slider</h3>
        <span class="help-tag help-tag-yellow">Promo</span>
        <p>Kelola promosi dan tampilan visual toko.</p>

        <h4>Banner</h4>
        <ul>
            <li>Atur banner promosi di halaman depan</li>
            <li>Isi judul, deskripsi, gambar, link tujuan</li>
            <li>Pilih tipe: <strong>announcement</strong> (bar di atas) atau <strong>popup</strong> (munculan)</li>
            <li>Atur jadwal tampil dengan tanggal mulai & selesai</li>
        </ul>

        <h4>Slider</h4>
        <ul>
            <li>Kelola slider / carousel di beranda toko</li>
            <li>Upload gambar slider dengan link tujuan</li>
        </ul>

        <h4>Social Follow Claim</h4>
        <ul>
            <li>Klaim follow media sosial dari pelanggan untuk mendapat reward</li>
        </ul>

        <h4>Kalender Promosi</h4>
        <ul>
            <li>Lihat semua jadwal promo dalam bentuk kalender</li>
            <li>Termasuk flash sale, bundle aktif, dan banner terjadwal</li>
        </ul>
    </div>

    {{-- ============================================ --}}
    {{-- KEUANGAN --}}
    {{-- ============================================ --}}
    <div id="keuangan" class="help-card" data-keywords="keuangan laporan pengeluaran expense laba rugi revenue chart target penjualan kupon coupon">
        <h3>Keuangan</h3>
        <span class="help-tag help-tag-green">Keuangan</span>
        <p>Pantau keuangan toko dari satu tempat.</p>

        <h4>Pengeluaran</h4>
        <ul>
            <li>Catat semua pengeluaran operasional toko (sewa, listrik, gaji, dll)</li>
            <li>Atur kategori pengeluaran terlebih dahulu</li>
        </ul>

        <h4>Laporan</h4>
        <ul>
            <li>Lihat ringkasan: total pesanan, pendapatan, produk terjual, rata-rata per pesanan</li>
            <li>Lihat laba/rugi: pendapatan vs pengeluaran vs laba bersih</li>
            <li>Top produk, kategori, dan kasir terlaris</li>
            <li>Filter periode: hari ini, minggu, bulan, tahun, atau kustom</li>
            <li>Export CSV untuk data lebih detail</li>
        </ul>

        <h4>Sales Target</h4>
        <ul>
            <li>Atur target pendapatan per periode</li>
            <li>Pantau progress pencapaian dengan bar grafik</li>
        </ul>

        <h4>Kupon / Voucher</h4>
        <ul>
            <li>Buat kupon diskon: persen atau nominal</li>
            <li>Atur: min order, max diskon, batas penggunaan, masa berlaku</li>
            <li>Pelanggan masukkan kode kupon saat checkout</li>
        </ul>
    </div>

    {{-- ============================================ --}}
    {{-- PENGGUNA --}}
    {{-- ============================================ --}}
    <div id="pengguna" class="help-card" data-keywords="pengguna user admin kasir customer pelanggan role poin point">
        <h3>Pengguna</h3>
        <span class="help-tag help-tag-blue">Pengguna</span>
        <p>Kelola data pelanggan, admin, dan kasir.</p>

        <h4>Role / Peran</h4>
        <ul>
            <li><strong>Admin</strong>: Akses penuh ke semua fitur</li>
            <li><strong>Cashier (Kasir)</strong>: Hanya bisa akses POS</li>
            <li><strong>Customer (Pelanggan)</strong>: Belanja di toko online</li>
        </ul>

        <h4>Fitur Lainnya</h4>
        <ul>
            <li>Filter user berdasarkan role dan segmen pelanggan</li>
            <li>Lihat riwayat poin transaksi pelanggan</li>
            <li>Segment pelanggan: Regular, Silver, Gold, Platinum (otomatis berdasarkan total belanja)</li>
        </ul>
    </div>

    {{-- ============================================ --}}
    {{-- PENGATURAN --}}
    {{-- ============================================ --}}
    <div id="pengaturan" class="help-card" data-keywords="pengaturan setting toko kontak whatsapp email smtp logo favicon ppn pajak analytics">
        <h3>Pengaturan Toko</h3>
        <span class="help-tag help-tag-yellow">Pengaturan</span>
        <p>Atur informasi dasar toko dari satu halaman.</p>

        <h4>Informasi Toko</h4>
        <ul>
            <li>Alamat, telepon, WhatsApp, email</li>
            <li>Sosial media: Instagram, Facebook, TikTok</li>
            <li>Upload logo & favicon toko</li>
        </ul>

        <h4>PPN (Pajak)</h4>
        <ul>
            <li>Toggle <strong>Aktifkan PPN</strong> untuk mengaktifkan pajak</li>
            <li>Atur <strong>Tarif PPN</strong> (default 11%)</li>
            <li>PPN otomatis diterapkan di POS dan checkout toko</li>
        </ul>

        <h4>SMTP / Email</h4>
        <ul>
            <li>Isi: host, port, username, password, enkripsi, pengirim</li>
        </ul>

        <h4>SEO & Analytics</h4>
        <ul>
            <li>Google Analytics ID</li>
            <li>Facebook Pixel ID</li>
            <li>Custom script di head & body</li>
        </ul>
    </div>

    {{-- ============================================ --}}
    {{-- TAMPILAN TOKO --}}
    {{-- ============================================ --}}
    <div id="toko" class="help-card" data-keywords="tampilan toko storefront depan beranda home navbar footer">
        <h3>Tampilan Toko (Storefront)</h3>
        <span class="help-tag help-tag-green">Storefront</span>
        <p>Bagaimana pelanggan melihat toko Anda.</p>

        <h4>Halaman Depan</h4>
        <ul>
            <li>Hero section dengan banner utama</li>
            <li>Produk unggulan dan produk terbaru</li>
            <li>Flash sale section (otomatis tampil jika ada flash sale aktif)</li>
            <li>Bundle section (otomatis tampil jika ada bundle aktif)</li>
            <li>Footer dengan info toko, kontak, dan logo pembayaran</li>
        </ul>

        <h4>Fitur Pelanggan</h4>
        <ul>
            <li>Pencarian dengan suggestions (auto-complete)</li>
            <li>Filter kategori + sorting (terbaru, termurah, termahal, nama)</li>
            <li>Wishlist dan bandingkan produk</li>
            <li>Review & rating produk</li>
            <li>Poin loyalitas dari setiap pembelian</li>
        </ul>

        <div class="help-tip">
            <strong>Tips:</strong> Semua perubahan di admin panel akan langsung terlihat di halaman depan toko. Tidak perlu restart server.
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- KONTAK --}}
    {{-- ============================================ --}}
    <div class="help-card" data-keywords="kontak bantuan support hubungi email telepon">
        <h3>Butuh Bantuan Lebih Lanjut?</h3>
        <p>Jika ada masalah teknis atau pertanyaan yang belum terjawab, silakan hubungi tim developer.</p>
        <ul>
            <li>Email: <strong>admin@hello-store.test</strong></li>
            <li>Akses admin: <strong>/admin</strong></li>
        </ul>
    </div>

</div>
