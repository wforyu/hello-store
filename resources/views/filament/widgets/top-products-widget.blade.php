<x-filament-widgets::widget>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Produk Paling Laku --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Produk Paling Laku
            </h3>
            <div class="space-y-2.5">
                @forelse($this->getTopProducts() as $i => $item)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-5 h-5 flex items-center justify-center rounded-full text-xs font-bold {{ $i < 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['name'] }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white ml-2 shrink-0">{{ $item['qty'] }} terjual</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-4">Belum ada penjualan</p>
                @endforelse
            </div>
        </div>

        {{-- Kategori Paling Laku --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Kategori Paling Laku
            </h3>
            <div class="space-y-2.5">
                @forelse($this->getTopCategories() as $i => $item)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-5 h-5 flex items-center justify-center rounded-full text-xs font-bold {{ $i < 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['name'] }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white ml-2 shrink-0">{{ $item['qty'] }} terjual</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-4">Belum ada penjualan</p>
                @endforelse
            </div>
        </div>

        {{-- Kasir Terbaik --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Kasir Terbaik
            </h3>
            <div class="space-y-2.5">
                @forelse($this->getTopCashiers() as $i => $item)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-5 h-5 flex items-center justify-center rounded-full text-xs font-bold {{ $i < 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['name'] }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white ml-2 shrink-0">{{ $item['orders'] }} order</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-4">Belum ada transaksi</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
