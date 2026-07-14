<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi - Hello Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-amber-600 hover:text-amber-700 transition text-sm font-medium mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali ke Beranda
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Kebijakan Privasi</h1>
            <p class="text-sm text-gray-500 mt-2">Terakhir diperbarui: {{ date('d F Y') }}</p>
        </div>

        <div class="prose prose-gray max-w-none space-y-8">
            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">1. Pengantar</h2>
                <p class="text-gray-600 leading-relaxed">Hello Store ("kami") menghargai privasi pengguna kami. Kebijakan Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, melindungi, dan menangani informasi pribadi Anda saat menggunakan aplikasi dan layanan kami.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">2. Informasi yang Kami Kumpulkan</h2>
                <div class="text-gray-600 leading-relaxed space-y-2">
                    <p><strong class="text-gray-700">Informasi Akun:</strong> Nama, alamat email, nomor telepon, dan kata sandi saat Anda mendaftar.</p>
                    <p><strong class="text-gray-700">Informasi Pengiriman:</strong> Alamat lengkap, nama penerima, dan nomor telepon untuk keperluan pengiriman pesanan.</p>
                    <p><strong class="text-gray-700">Informasi Pembayaran:</strong> Bukti transfer, nama bank, dan nomor rekening saat Anda melakukan pembayaran manual.</p>
                    <p><strong class="text-gray-700">Data Aktivitas:</strong> Riwayat pesanan, produk yang dilihat, ulasan, dan aktivitas browsing di dalam aplikasi.</p>
                    <p><strong class="text-gray-700">Data Perangkat:</strong> Sistem operasi, versi aplikasi, dan identitas perangkat untuk keperluan keamanan dan perbaikan.</p>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">3. Penggunaan Informasi</h2>
                <p class="text-gray-600 leading-relaxed">Kami menggunakan informasi yang dikumpulkan untuk:</p>
                <ul class="list-disc list-inside text-gray-600 space-y-1.5 mt-2">
                    <li>Memproses dan mengirim pesanan Anda</li>
                    <li>Mengirim pembaruan status pesanan dan notifikasi</li>
                    <li>Menyediakan layanan pelanggan dan dukungan</li>
                    <li>Memperbaiki dan meningkatkan aplikasi serta layanan kami</li>
                    <li>Mencegah penipuan dan menjaga keamanan akun</li>
                    <li>Mengirim promosi dan penawaran khusus (dengan persetujuan Anda)</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">4. Perlindungan Data</h2>
                <p class="text-gray-600 leading-relaxed">Kami menerapkan langkah-langkah keamanan yang sesuai untuk melindungi informasi pribadi Anda, termasuk enkripsi data, akses terbatas, dan pemantauan keamanan berkala. Namun, tidak ada metode transmisi atau penyimpanan elektronik yang 100% aman, dan kami tidak dapat menjamin keamanan absolut.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">5. Berbagi Informasi</h2>
                <div class="text-gray-600 leading-relaxed space-y-2">
                    <p>Kami <strong class="text-gray-700">tidak menjual</strong> atau menyewakan informasi pribadi Anda kepada pihak ketiga. Kami hanya membagikan informasi Anda kepada:</p>
                    <ul class="list-disc list-inside space-y-1.5 mt-2">
                        <li><strong class="text-gray-700">Kurir/Pengiriman:</strong> Untuk mengirimkan pesanan ke alamat Anda</li>
                        <li><strong class="text-gray-700">Payment Gateway:</strong> Untuk memproses pembayaran (jika applicable)</li>
                        <li><strong class="text-gray-700">Pihak Berwenang:</strong> Jika diwajibkan oleh hukum atau proses hukum</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">6. Hak Pengguna</h2>
                <p class="text-gray-600 leading-relaxed">Anda memiliki hak untuk:</p>
                <ul class="list-disc list-inside text-gray-600 space-y-1.5 mt-2">
                    <li>Mengakses dan melihat data pribadi Anda</li>
                    <li>Memperbarui atau mengoreksi informasi Anda</li>
                    <li>Menghapus akun dan data pribadi Anda</li>
                    <li>Menolak penggunaan data untuk pemasaran</li>
                    <li>Menarik persetujuan kapan saja</li>
                </ul>
                <p class="text-gray-600 leading-relaxed mt-3">Untuk menggunakan hak-hak tersebut, silakan hubungi kami melalui WhatsApp atau email yang tersedia di halaman kontak.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">7. Penyimpanan Data</h2>
                <p class="text-gray-600 leading-relaxed">Kami menyimpan informasi pribadi Anda selama akun Anda aktif atau selama diperlukan untuk menyediakan layanan. Setelah akun dihapus, data pribadi akan dihapus secara permanen dalam waktu 30 hari, kecuali diwajibkan oleh hukum untuk menyimpannya lebih lama.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">8. Cookie dan Teknologi Pelacakan</h2>
                <p class="text-gray-600 leading-relaxed">Aplikasi kami menggunakan cookie dan teknologi serupa untuk meningkatkan pengalaman Anda, menganalisis penggunaan, dan mengelola preferensi Anda. Anda dapat mengontrol penggunaan cookie melalui pengaturan perangkat Anda.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">9. Perubahan Kebijakan</h2>
                <p class="text-gray-600 leading-relaxed">Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu. Perubahan akan dipublikasikan di halaman ini dengan tanggal pembaruan terbaru. Penggunaan berkelanjutan setelah perubahan berarti Anda menyetujui kebijakan yang diperbarui.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-900 mb-3">10. Hubungi Kami</h2>
                <p class="text-gray-600 leading-relaxed">Jika Anda memiliki pertanyaan tentang Kebijakan Privasi ini, silakan hubungi kami:</p>
                <div class="bg-white rounded-xl border border-gray-200 p-4 mt-3 space-y-1.5 text-sm text-gray-600">
                    @if($settings['store_address'] ?? null)
                        <p><strong class="text-gray-700">Alamat:</strong> {{ $settings['store_address'] }}</p>
                    @endif
                    @if($settings['email'] ?? null)
                        <p><strong class="text-gray-700">Email:</strong> {{ $settings['email'] }}</p>
                    @endif
                    @if($settings['whatsapp'] ?? null)
                        <p><strong class="text-gray-700">WhatsApp:</strong> {{ $settings['whatsapp'] }}</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="border-t border-gray-200 mt-12 pt-6 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} Hello Store. All rights reserved.
        </div>
    </div>
</body>
</html>
