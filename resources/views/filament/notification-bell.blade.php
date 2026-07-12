<div
    x-data="{
        count: 0,
        prevCount: 0,
        audioCtx: null,
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
        goToNotifications() {
            window.location.href = '{{ route('notifications.index') }}';
        }
    }"
    x-init="init()"
    class="relative flex items-center"
>
    <button
        @click="goToNotifications()"
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
</div>
