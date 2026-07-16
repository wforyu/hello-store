<div
    x-data="{
        count: 0,
        prevCount: 0,
        audioCtx: null,
        open: false,
        loading: false,
        notifications: [],
        init() {
            this.fetchCount();
            setInterval(() => this.fetchCount(), 10000);
        },
        fetchCount() {
            fetch('{{ route('notifications.unread') }}')
                .then(r => r.json())
                .then(d => {
                    this.prevCount = this.count;
                    this.count = d.count;
                    if (this.count > this.prevCount) {
                        this.playSound();
                    }
                })
                .catch(() => {});
        },
        playSound() {
            try {
                if (!this.audioCtx) {
                    this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                var ctx = this.audioCtx;
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.2);

                var osc2 = ctx.createOscillator();
                var gain2 = ctx.createGain();
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.frequency.value = 1100;
                gain2.gain.setValueAtTime(0.2, ctx.currentTime + 0.15);
                gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                osc2.start(ctx.currentTime + 0.15);
                osc2.stop(ctx.currentTime + 0.35);
            } catch(e) {}
        },
        toggle() {
            this.open = !this.open;
            if (this.open) this.fetchNotifications();
        },
        close() {
            this.open = false;
        },
        fetchNotifications() {
            this.loading = true;
            fetch('{{ route('notifications.json') }}')
                .then(r => r.json())
                .then(d => {
                    this.notifications = d.notifications || [];
                    this.loading = false;
                })
                .catch(() => { this.loading = false; });
        },
        markRead(id) {
            fetch('/notifications/' + id + '/read-json', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(() => {
                    var n = this.notifications.find(x => x.id === id);
                    if (n) n.is_read = true;
                    this.count = Math.max(0, this.count - 1);
                })
                .catch(() => {});
        },
        markAllRead() {
            fetch('/notifications/read-all', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                .then(() => {
                    this.notifications.forEach(n => n.is_read = true);
                    this.count = 0;
                })
                .catch(() => {});
        },
        typeIcon(type) {
            var icons = { order: '📦', payment: '💳', tier: '⭐', review: '📝', stock: '📊', shipping: '🚚', refund: '↩️', system: '🔔' };
            return icons[type] || '🔔';
        },
        typeColor(type) {
            var colors = { order: '#F59E0B', payment: '#10B981', tier: '#8B5CF6', review: '#3B82F6', stock: '#EF4444', shipping: '#06B6D4', refund: '#F97316', system: '#6B7280' };
            return colors[type] || '#6B7280';
        }
    }"
    x-init="init()"
    @keydown.escape.window="close()"
    class="relative flex items-center"
>
    <button
        @click="toggle()"
        class="relative p-2 text-gray-400 hover:text-amber-500 transition"
        title="Notifikasi"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        <span
            x-show="count > 0"
            x-cloak
            class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 shadow-sm"
            x-text="count"
        ></span>
    </button>

    {{-- Overlay --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        style="position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:40;"
    ></div>

    {{-- Slide-over Panel --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @click.away="close()"
        style="position:fixed;top:0;right:0;bottom:0;width:400px;max-width:100vw;z-index:50;display:flex;flex-direction:column;background:var(--gray-50,#f9fafb);box-shadow:-4px 0 24px rgba(0,0,0,0.15);"
    >
        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--gray-200,#e5e7eb);background:var(--gray-50,#f9fafb);flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:18px;">🔔</span>
                <span style="font-size:16px;font-weight:700;color:var(--gray-900,#111827);">Notifikasi</span>
                <span
                    x-show="count > 0"
                    x-cloak
                    style="background:#EF4444;color:#fff;font-size:11px;font-weight:700;border-radius:9999px;padding:2px 8px;min-width:20px;text-align:center;"
                    x-text="count"
                ></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <button
                    x-show="count > 0"
                    @click="markAllRead()"
                    style="font-size:12px;color:var(--primary-600,#d97706);font-weight:600;cursor:pointer;background:none;border:none;padding:4px 8px;border-radius:6px;"
                    onmouseover="this.style.background='var(--gray-200,#e5e7eb)'"
                    onmouseout="this.style.background='none'"
                >Tandai Semua Dibaca</button>
                <button
                    @click="close()"
                    style="font-size:20px;color:var(--gray-400,#9ca3af);cursor:pointer;background:none;border:none;padding:4px;"
                    onmouseover="this.style.color='var(--gray-600,#4b5563)'"
                    onmouseout="this.style.color='var(--gray-400,#9ca3af)'"
                >&#10005;</button>
            </div>
        </div>

        {{-- List --}}
        <div style="flex:1;overflow-y:auto;padding:8px 12px;">
            {{-- Loading --}}
            <template x-if="loading">
                <div style="text-align:center;padding:40px 20px;color:var(--gray-400,#9ca3af);">
                    <div style="font-size:24px;margin-bottom:8px;">⏳</div>
                    <div style="font-size:13px;">Memuat notifikasi...</div>
                </div>
            </template>

            {{-- Empty --}}
            <template x-if="!loading && notifications.length === 0">
                <div style="text-align:center;padding:40px 20px;color:var(--gray-400,#9ca3af);">
                    <div style="font-size:40px;margin-bottom:8px;">🔕</div>
                    <div style="font-size:14px;font-weight:600;margin-bottom:4px;">Tidak ada notifikasi</div>
                    <div style="font-size:12px;">Notifikasi baru akan muncul di sini</div>
                </div>
            </template>

            {{-- Items --}}
            <template x-for="n in notifications" :key="n.id">
                <div
                    @click="if (!n.is_read) markRead(n.id)"
                    :style="'display:flex;gap:12px;padding:12px;margin-bottom:6px;border-radius:10px;cursor:pointer;transition:background 0.15s;border:1px solid ' + (n.is_read ? 'transparent' : 'var(--primary-200,#fef3c7)') + ';background:' + (n.is_read ? 'transparent' : 'var(--primary-50,#fffbeb)') + ';'"
                    onmouseover="this.style.background='var(--gray-100,#f3f4f6)'"
                    onmouseout="this.style.background=n.is_read?'transparent':'var(--primary-50,#fffbeb)'"
                >
                    <div style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;background:var(--gray-100,#f3f4f6);">
                        <span x-text="typeIcon(n.type)"></span>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                            <span style="font-size:13px;font-weight:700;color:var(--gray-900,#111827);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="n.title"></span>
                            <span
                                x-show="!n.is_read"
                                style="width:8px;height:8px;border-radius:50%;background:#EF4444;flex-shrink:0;"
                            ></span>
                        </div>
                        <div style="font-size:12px;color:var(--gray-500,#6b7280);line-height:1.4;margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;" x-text="n.body"></div>
                        <div style="font-size:11px;color:var(--gray-400,#9ca3af);" x-text="n.created_at"></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div style="padding:12px 20px;border-top:1px solid var(--gray-200,#e5e7eb);text-align:center;flex-shrink:0;background:var(--gray-50,#f9fafb);">
            <a
                href="{{ route('notifications.index') }}"
                style="font-size:13px;font-weight:600;color:var(--primary-600,#d97706);text-decoration:none;"
                onmouseover="this.style.textDecoration='underline'"
                onmouseout="this.style.textDecoration='none'"
            >Lihat Semua Notifikasi</a>
        </div>
    </div>
</div>
