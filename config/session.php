<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | This option determines the default session driver that is utilized for
    | incoming requests. Laravel supports a variety of storage options to
    | persist session data. Database storage is a great default choice.
    |
    | Supported: "file", "cookie", "database", "memcached",
    |            "redis", "dynamodb", "array"
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish the session
    | to be allowed to remain idle before it expires. If you want them
    | to expire immediately when the browser is closed then you may
    | indicate that via the expire_on_close configuration option.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Idle Session Timeout
    |--------------------------------------------------------------------------
    |
    | 'lifetime' di atas TIDAK menyebabkan auto-logout. Laravel 13
    | Illuminate\Session\Middleware\AuthenticateSession hanya memvalidasi
    | password hash, tidak punya cek last_activity_time. Nilai 'lifetime'
    | hanya=max-age cookie + garbage collection.
    |
    | Auto-logout idle diimplementasikan lewat App\Http\Middleware
    | \EnforceIdleLogout, yang dipasang di panel Filament (area 'admin') dan
    | group route POS (area 'pos'). Nilai = menit. Set 0 untuk mematikan.
    |
    | 'warning' = berapa detik sebelum expired halaman menampilkan modal
    | countdown, supaya kasir/admin sempat klik "Tetap masuk" dan tidak
    | kehilangan keranjang atau form yang sedang diisi.
    |
    */

    'idle_timeout' => [
        'enabled' => env('SESSION_IDLE_ENABLED', true),
        'admin' => (int) env('SESSION_IDLE_ADMIN', 30),
        'pos' => (int) env('SESSION_IDLE_POS', 30),
        'warning' => (int) env('SESSION_IDLE_WARNING', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | This option allows you to easily specify that all of your session data
    | should be encrypted before it's stored. All encryption is performed
    | automatically by Laravel and you may use the session like normal.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | When utilizing the "file" session driver, the session files are placed
    | on disk. The default storage location is defined here; however, you
    | are free to provide another location where they should be stored.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    |
    | When using the "database" or "redis" session drivers, you may specify a
    | connection that should be used to manage these sessions. This should
    | correspond to a connection in your database configuration options.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | When using the "database" session driver, you may specify the table to
    | be used to store sessions. Of course, a sensible default is defined
    | for you; however, you're welcome to change this to another table.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | When using one of the framework's cache driven session backends, you may
    | define the cache store which should be used to store the session data
    | between requests. This must match one of your defined cache stores.
    |
    | Affects: "dynamodb", "memcached", "redis"
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Request Blocking (anti race keranjang POS)
    |--------------------------------------------------------------------------
    |
    | Laravel MENYIMPAN session di akhir SETIAP request, dan driver database
    | tidak punya locking: dua request tumpang-tindih bisa membaca session
    | yang sama lalu saling menimpa — pemenangnya yang menulis TERAKHIR,
    | bukan yang paling baru. Di POS, kasir yang klik cepat (tambah produk
    | lalu langsung klik produk berikutnya, atau debounce diskon 400 ms yang
    | masih jalan) mengirim request berbarengan, sehingga keranjang di layar
    | tidak sinkron dengan session di database — gejala "produk muncul sebentar
    | lalu hilang, harus refresh dulu baru benar".
    |
    | Serialisasi request per session dijalankan oleh
    | App\Http\Middleware\SerializeSessionRequests — GAGAL-OPEN: kunci gagal
    | dibuat atau giliran ditunggu terlalu lama = request tetap jalan tanpa
    | serialisasi dan kegagalannya dicatat ke laravel.log.
    |
    | 'serialize_requests' = tombol utama middleware tersebut.
    | 'block_lock_seconds' = lama kunci dipegang; otomatis lepas kalau proses
    | mati mendadak, jadi jangan terlalu kecil (request lambat bisa lebih
    | dulu kehilangan kuncinya) dan jangan terlalu besar (session tetap
    | terkunci setelah crash).
    | 'block_wait_seconds' = berapa lama menunggu giliran sebelum lanjut
    | tanpa kunci — sengaja pendek supaya kasir tidak menunggu lama.
    |
    | PENTING — 'session.block' bawaan Laravel SENGAJA dibiarkan false:
    | key-nya sama dengan mekanisme blocking bawaan StartSession, dan
    | mekanisme bawaan itu MELEMPAR exception (LockTimeoutException /
    | QueryException) yang berujung 500 untuk SEMUA request saat kunci
    | tidak bisa diambil. Pernah terbukti di produksi: mengaktifkan
    | 'session.block' = POS mogok total dengan 500 di /pos/add & /pos/remove.
    | Serialisasi yang aman ada di middleware fail-open, BUKAN di sini.
    |
    */

    'serialize_requests' => (bool) env('SESSION_SERIALIZE_REQUESTS', true),

    'block' => (bool) env('SESSION_BLOCK', false),

    'block_lock_seconds' => (int) env('SESSION_BLOCK_LOCK_SECONDS', 30),

    'block_wait_seconds' => (int) env('SESSION_BLOCK_WAIT_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Some session drivers must manually sweep their storage location to get
    | rid of old sessions from storage. Here are the chances that it will
    | happen on a given request. By default, the odds are 2 out of 100.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the session cookie that is created by
    | the framework. Typically, you should not need to change this value
    | since doing so does not grant a meaningful security improvement.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | The session cookie path determines the path for which the cookie will
    | be regarded as available. Typically, this will be the root path of
    | your application, but you're free to change this when necessary.
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | This value determines the domain and subdomains the session cookie is
    | available to. By default, the cookie will be available to the root
    | domain without subdomains. Typically, this shouldn't be changed.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | By setting this option to true, session cookies will only be sent back
    | to the server if the browser has a HTTPS connection. This will keep
    | the cookie from being sent to you when it can't be done securely.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will prevent JavaScript from accessing the
    | value of the cookie and the cookie will only be accessible through
    | the HTTP protocol. It's unlikely you should disable this option.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | This option determines how your cookies behave when cross-site requests
    | take place, and can be used to mitigate CSRF attacks. By default, we
    | will set this value to "lax" to permit secure cross-site requests.
    |
    | See: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
    |
    | Supported: "lax", "strict", "none", null
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will tie the cookie to the top-level site for
    | a cross-site context. Partitioned cookies are accepted by the browser
    | when flagged "secure" and the Same-Site attribute is set to "none".
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Serialization
    |--------------------------------------------------------------------------
    |
    | This value controls the serialization strategy for session data, which
    | is JSON by default. Setting this to "php" allows the storage of PHP
    | objects in the session but can make an application vulnerable to
    | "gadget chain" serialization attacks if the APP_KEY is leaked.
    |
    | Supported: "json", "php"
    |
    */

    'serialization' => 'json',

];
