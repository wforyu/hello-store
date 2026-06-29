<x-filament-widgets::widget>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div>
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                Produk Paling Laku
            </h3>
            <div class="space-y-2">
                @forelse($this->getTopProducts() as $i => $item)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-5 h-5 flex items-center justify-center rounded text-xs font-bold {{ $i < 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['name'] }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white ml-2 shrink-0 text-xs">{{ $item['qty'] }} terjual</span>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 text-center py-4">Belum ada penjualan</p>
                @endforelse
            </div>
        </div>

        <div>
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                Kategori Paling Laku
            </h3>
            <div class="space-y-2">
                @forelse($this->getTopCategories() as $i => $item)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-5 h-5 flex items-center justify-center rounded text-xs font-bold {{ $i < 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['name'] }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white ml-2 shrink-0 text-xs">{{ $item['qty'] }} terjual</span>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 text-center py-4">Belum ada penjualan</p>
                @endforelse
            </div>
        </div>

        <div>
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                Kasir Terbaik
            </h3>
            <div class="space-y-2">
                @forelse($this->getTopCashiers() as $i => $item)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-5 h-5 flex items-center justify-center rounded text-xs font-bold {{ $i < 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['name'] }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white ml-2 shrink-0 text-xs">{{ $item['orders'] }} order</span>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 text-center py-4">Belum ada transaksi</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
