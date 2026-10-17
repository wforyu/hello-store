{{-- Pencarian khusus POS: hanya produk POS, klik hasil = tambah ke keranjang POS --}}
@props(['wrapper' => ''])

<div class="{{ $wrapper }}"
    x-data="posNavSearch()"
    @click.away="show = false"
    @keydown.escape="show = false">
    <div class="relative">
        <input type="text" x-model="query"
            @input.debounce.250ms="run"
            @focus="if (results.length) show = true"
            @keydown.enter.prevent="pickFirst"
            @keydown.down.prevent="selectedIndex = Math.min(selectedIndex + 1, results.length - 1)"
            @keydown.up.prevent="selectedIndex = Math.max(selectedIndex - 1, 0)"
            placeholder="Cari produk di POS..."
            autocomplete="off"
            class="pos-search-input w-full pl-10 pr-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent focus:bg-white text-sm transition">
        <svg class="absolute left-3.5 top-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>

        <div x-show="show && results.length" x-cloak
            class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl border border-gray-200 shadow-lg z-50 overflow-hidden max-h-80 overflow-y-auto">
            <template x-for="(p, i) in results" :key="p.id">
                <button type="button" @click="select(p)"
                    class="w-full text-left flex items-center gap-3 px-4 py-3 hover:bg-amber-50 transition"
                    :class="{ 'border-t border-gray-100': i > 0, 'bg-amber-50': selectedIndex === i }"
                    @mouseenter="selectedIndex = i">
                    <img x-show="p.image" :src="p.image" :alt="p.name" class="w-10 h-10 rounded-lg object-contain bg-gray-50 shrink-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate" x-text="p.name"></p>
                        <p class="text-xs text-gray-500">
                            <span x-show="p.sku" x-text="p.sku + ' · '"></span>
                            <span x-text="'Stok: ' + p.stock"></span>
                            <span class="text-amber-600 font-semibold" x-text="' · Rp ' + Number(p.price).toLocaleString('id-ID')"></span>
                        </p>
                    </div>
                    <span class="text-[10px] font-bold text-white bg-amber-500 rounded-full px-2 py-0.5 shrink-0">Tambah</span>
                </button>
            </template>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function posNavSearch() {
            return {
                query: '',
                results: [],
                show: false,
                selectedIndex: -1,
                run() {
                    const q = this.query.trim();
                    window.dispatchEvent(new CustomEvent('pos:filter', { detail: q }));
                    this.selectedIndex = -1;
                    if (!q) {
                        this.results = [];
                        this.show = false;
                        return;
                    }
                    fetch('{{ route("pos.search") }}?search=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(d => { this.results = d; this.show = true; })
                        .catch(() => { this.results = []; this.show = false; });
                },
                select(p) {
                    window.dispatchEvent(new CustomEvent('pos:add-product', { detail: p }));
                    this.query = '';
                    this.results = [];
                    this.show = false;
                    window.dispatchEvent(new CustomEvent('pos:filter', { detail: '' }));
                },
                pickFirst() {
                    if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                        this.select(this.results[this.selectedIndex]);
                    } else if (this.results.length) {
                        this.select(this.results[0]);
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
