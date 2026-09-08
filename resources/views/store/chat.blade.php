@extends('layouts.store')

@section('title', 'Live Chat')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-4 flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <div class="flex-1">
                    <h1 class="text-white font-bold">Live Chat Support</h1>
                    <p class="text-amber-100 text-xs flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 bg-emerald-300 rounded-full animate-pulse"></span>
                        Admin sedang online
                    </p>
                </div>
            </div>

            {{-- Messages --}}
            <div class="h-[420px] overflow-y-auto p-5 space-y-3 bg-gray-50" id="chat-messages">
                @forelse($messages as $msg)
                    <div class="flex {{ $msg->sender_type === 'customer' ? 'justify-end' : 'justify-start' }}" data-msg-id="{{ $msg->id }}">
                        <div class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm shadow-sm
                            {{ $msg->sender_type === 'customer'
                                ? 'bg-amber-500 text-white rounded-br-sm'
                                : 'bg-white border border-gray-200 rounded-bl-sm' }}">
                            <p class="{{ $msg->sender_type === 'customer' ? 'text-white' : 'text-gray-800' }}">{{ $msg->message }}</p>
                            <p class="mt-1 text-[10px] {{ $msg->sender_type === 'customer' ? 'text-amber-100' : 'text-gray-400' }}">
                                {{ $msg->sender_type === 'admin' ? 'Admin' : 'Anda' }} · {{ $msg->created_at->format('H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-gray-400" id="chat-empty">
                        <div class="text-4xl mb-3">🛍️</div>
                        <p class="text-sm">Halo! Ada yang bisa kami bantu?</p>
                        <p class="text-xs mt-1">Tanyakan tentang produk, pesanan, atau pembayaran.</p>
                    </div>
                @endforelse
            </div>

            {{-- Input --}}
            <div class="border-t border-gray-100 p-4 bg-white">
                @if(!auth()->check())
                    <div class="mb-3">
                        <input type="text" id="chat-guest-name" placeholder="Nama Anda (opsional)"
                            class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-gray-700"
                            maxlength="100" value="{{ session('chat_guest_name', '') }}">
                    </div>
                @endif
                <div class="flex items-end gap-2">
                    <textarea id="chat-input" rows="2" placeholder="Ketik pesan..."
                        class="flex-1 border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition resize-none text-gray-700"
                        maxlength="1000"></textarea>
                    <button type="button" id="chat-send"
                        class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-5 py-3 rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-xs text-gray-400">
            Chat hanya menampilkan pesan dari sesi browser ini. Pesan admin akan muncul otomatis saat dibalas.
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const container = document.getElementById('chat-messages');
            if (!container) return;

            let lastId = 0;
            document.querySelectorAll('#chat-messages [data-msg-id]').forEach((el) => {
                const id = parseInt(el.dataset.msgId, 10);
                if (id > lastId) lastId = id;
            });

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const pollUrl = '{{ route('chat.poll') }}';
            const sendUrl = '{{ route('chat.send') }}';

            function scrollChat() {
                container.scrollTop = container.scrollHeight;
            }

            function appendMessage(msg) {
                if (!msg || !msg.id) return;
                if (container.querySelector('[data-msg-id="' + msg.id + '"]')) return;

                const empty = document.getElementById('chat-empty');
                if (empty) empty.remove();

                const isCustomer = msg.sender_type === 'customer';
                const wrap = document.createElement('div');
                wrap.className = 'flex ' + (isCustomer ? 'justify-end' : 'justify-start');
                wrap.dataset.msgId = msg.id;

                const bubble = document.createElement('div');
                bubble.className = 'max-w-[75%] rounded-2xl px-4 py-2.5 text-sm shadow-sm ' +
                    (isCustomer ? 'bg-amber-500 text-white rounded-br-sm' : 'bg-white border border-gray-200 rounded-bl-sm');

                const p = document.createElement('p');
                p.className = isCustomer ? 'text-white' : 'text-gray-800';
                p.textContent = msg.message;

                const time = document.createElement('p');
                time.className = 'mt-1 text-[10px] ' + (isCustomer ? 'text-amber-100' : 'text-gray-400');
                const created = msg.created_at ? new Date(msg.created_at) : new Date();
                if (Number.isNaN(created.getTime())) time.textContent = (isCustomer ? 'Anda' : 'Admin');
                else {
                    const hh = String(created.getHours()).padStart(2, '0');
                    const mm = String(created.getMinutes()).padStart(2, '0');
                    time.textContent = (isCustomer ? 'Anda' : 'Admin') + ' · ' + hh + ':' + mm;
                }

                bubble.appendChild(p);
                bubble.appendChild(time);
                wrap.appendChild(bubble);
                container.appendChild(wrap);

                if (msg.id > lastId) lastId = msg.id;
            }

            function poll() {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 8000);
                fetch(pollUrl + '?after_id=' + lastId, {
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                })
                    .then((r) => r.json())
                    .then((data) => {
                        clearTimeout(timeoutId);
                        if (data.success && Array.isArray(data.messages)) {
                            data.messages.forEach(appendMessage);
                            scrollChat();
                        }
                    })
                    .catch(() => clearTimeout(timeoutId));
            }

            async function send() {
                const input = document.getElementById('chat-input');
                const message = (input.value || '').trim();
                if (!message) return;

                const guestNameEl = document.getElementById('chat-guest-name');
                const payload = { message: message };
                if (guestNameEl && guestNameEl.value.trim()) {
                    payload.guest_name = guestNameEl.value.trim();
                }

                input.value = '';
                const tmpWrap = document.createElement('div');
                tmpWrap.className = 'flex justify-end';
                const tmpBubble = document.createElement('div');
                tmpBubble.className = 'max-w-[75%] rounded-2xl px-4 py-2.5 text-sm shadow-sm bg-amber-500 text-white rounded-br-sm';
                const tmpP = document.createElement('p');
                tmpP.className = 'text-white';
                tmpP.textContent = message;
                tmpBubble.appendChild(tmpP);
                tmpWrap.appendChild(tmpBubble);
                container.appendChild(tmpWrap);
                scrollChat();

                try {
                    const res = await fetch(sendUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json();
                    tmpWrap.remove();
                    if (data.success && data.message) {
                        appendMessage(data.message);
                        scrollChat();
                    } else {
                        tmpP.textContent = '❌ Gagal mengirim. Coba lagi.';
                        tmpWrap.className = tmpWrap.className + ' opacity-70';
                    }
                } catch (err) {
                    tmpP.textContent = '❌ Gagal mengirim. Coba lagi.';
                    tmpWrap.className = tmpWrap.className + ' opacity-70';
                }
            }

            document.getElementById('chat-send').addEventListener('click', send);
            document.getElementById('chat-input').addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    send();
                }
            });

            scrollChat();
            setInterval(poll, 5000);
        })();
    </script>
@endpush