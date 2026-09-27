{{-- Idle session timer. Di-inject ke panel Filament (renderHook BODY_END) dan
     ke view POS. area: 'admin' | 'pos'.

     Kenapa JS, bukan cuma andalkan request server: request Livewire dari polling
     widget diabaikan middleware (biar tidak selalu terhitung aktif), sehingga
     interaksi user yang sebenarnya harus dilaporkan dari sini. --}}
@php
    use App\Support\IdleTimeout;

    // session() di Blade mengembalikan SessionManager (factory), bukan store
    // yang aktif. Store-nya diambil dari request yang sudah lewat StartSession.
    $idleSnapshot = auth()->check() ? IdleTimeout::snapshot(request()->session(), $area) : null;
    $idleLoginUrl = $area === 'admin' ? \Filament\Facades\Filament::getLoginUrl() : route('login');
@endphp

@if ($idleSnapshot['enabled'] ?? false)
<div id="idle-timer-root"
     data-area="{{ $area }}"
     data-timeout="{{ $idleSnapshot['timeout'] }}"
     data-remaining="{{ $idleSnapshot['remaining'] }}"
     data-warning="{{ $idleSnapshot['warning'] }}"
     data-keep-alive-url="{{ route('session.keep-alive') }}"
     data-login-url="{{ $idleLoginUrl }}"
     data-csrf="{{ csrf_token() }}"
     style="display:none"></div>

<script>
(function () {
    var root = document.getElementById('idle-timer-root');
    if (!root || typeof window.fetch !== 'function') { return; }

    var PING_INTERVAL_MS = 60000; // throttle: maksimal 1 ping per menit
    var AREA = root.dataset.area;
    var KEEP_ALIVE_URL = root.dataset.keepAliveUrl;
    var LOGIN_URL = root.dataset.loginUrl;
    var CSRF = root.dataset.csrf;
    var WARNING = parseInt(root.dataset.warning, 10) || 60;

    var remaining = parseInt(root.dataset.remaining, 10) || 0;
    // Di-set ke now() (bukan 0) supaya ping pertama tunable setelah 60 detik,
    // dan tidak langsung memicu begitu ada satu mousemove random.
    var lastPingAt = Date.now();
    var dirty = false;
    var warned = false;

    // --- UI modal -----------------------------------------------------------
    var overlay = document.createElement('div');
    overlay.setAttribute('id', 'idle-warning-overlay');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;display:none;'
        + 'align-items:center;justify-content:center;background:rgba(0,0,0,.6);'
        + 'font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;';
    overlay.innerHTML = '<div style="background:#fff;color:#111827;border-radius:12px;'
        + 'padding:24px;max-width:420px;width:calc(100% - 32px);text-align:center;'
        + 'box-shadow:0 20px 45px rgba(0,0,0,.35);">'
        + '<div style="font-size:32px;margin-bottom:8px;">&#9203;</div>'
        + '<h2 style="margin:0 0 8px;font-size:18px;">Sesi akan berakhir</h2>'
        + '<p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.5;">'
        + 'Anda tidak aktif selama beberapa menit.<br>Logout otomatis dalam '
        + '<strong><span id="idle-countdown">60</span> detik</strong>.</p>'
        + '<button id="idle-stay-btn" style="background:#f59e0b;color:#fff;border:0;'
        + 'border-radius:8px;padding:10px 20px;font-size:14px;font-weight:600;cursor:pointer;">'
        + 'Tetap masuk</button>'
        + '<p style="margin:14px 0 0;font-size:12px;color:#6b7280;">'
        + 'Untuk POS, keranjang BELUM akan hilang selama Anda menekan tombol di atas.</p>'
        + '</div>';
    document.body.appendChild(overlay);

    var countdownEl = overlay.querySelector('#idle-countdown');
    var stayBtn = overlay.querySelector('#idle-stay-btn');

    function hideWarning() {
        overlay.style.display = 'none';
        warned = false;
    }

    function showWarning() {
        overlay.style.display = 'flex';
        warned = true;
    }

    // --- keep-alive ---------------------------------------------------------
    function ping() {
        return window.fetch(KEEP_ALIVE_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ area: AREA })
        }).then(function (response) {
            if (response.status === 401) {
                // Sesi sudah habis di server (mis. tab dorman lalu di-build ulang).
                window.location.href = LOGIN_URL;
                return null;
            }
            // Parse sebagai teks dulu: kalau yang cameback HTML (halaman login
            // dari proxy/CDN, atau handler yang masih fallback redirect), sesi
            // tidak bisa dianggap valid.
            return response.text().then(function (body) {
                if (!response.ok) { return null; }

                var data;
                try {
                    data = JSON.parse(body);
                } catch (error) {
                    window.location.href = LOGIN_URL;
                    return null;
                }

                if (typeof data.remaining === 'number') {
                    remaining = data.remaining;
                }
                lastPingAt = Date.now();
                dirty = false;
                hideWarning();

                return data;
            });
        }).catch(function () {
            // Jaringan putus sesaat: biarkan countdown server yang jadi
            // sumber kebenaran, jangan reset agresif.
        });
    }

    // --- activity tracking --------------------------------------------------
    // Polling Livewire sengaja TIDAK memanggil invalidate(); hanya event user.
    ['mousemove', 'keydown', 'click', 'touchstart', 'scroll', 'wheel'].forEach(function (evt) {
        window.addEventListener(evt, function () {
            dirty = true;
        }, { passive: true });
    });

    stayBtn.addEventListener('click', function () {
        ping();
    });

    // --- tick ---------------------------------------------------------------
    setInterval(function () {
        remaining -= 1;

        if (remaining <= 0) {
            // Server yang memutuskan arah logout; reload supaya middleware
            // yang memaksa logout dan mengarahkan ke halaman login.
            window.location.reload();
            return;
        }

        if (remaining <= WARNING) {
            if (!warned) { showWarning(); }
            countdownEl.textContent = String(remaining);
        }

        var now = Date.now();
        if (dirty && (now - lastPingAt) >= PING_INTERVAL_MS) {
            ping();
        }
    }, 1000);
})();
</script>
@endif
