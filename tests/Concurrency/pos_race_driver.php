<?php

/**
 * Driver for the real-concurrency POS race tests.
 *
 * For each scenario it seeds a fixture, spawns two independent OS processes
 * that call PosService::checkout() at the same instant, then asserts the
 * money/stock invariants that must hold no matter who wins.
 *
 * Run against a real engine -- SQLite cannot express a race:
 *   php tests/Concurrency/pos_race_driver.php
 */
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=hello_store_test;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$worker = __DIR__.'/pos_race_worker.php';
$php = PHP_BINARY;

// proc_open() replaces the whole environment when $env is given. On Windows a
// child PHP without SystemRoot/PATH cannot initialise Winsock, and PDO then
// reports a misleading "2002 Unknown error while connecting". Inherit the
// parent environment and only override the keys we care about.
$env = array_merge(getenv(), [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'hello_store_test',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
    'DB_TIMEZONE' => '+07:00',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
]);

$failures = [];
$passes = [];

function check(string $scenario, string $label, bool $ok, string $detail = ''): void
{
    global $failures, $passes;

    $line = sprintf('[%s] %s %s', $ok ? ' OK ' : 'FAIL', $scenario, $label);

    if ($detail !== '') {
        $line .= ' -- '.$detail;
    }

    echo $line, "\n";

    if ($ok) {
        $passes[] = $line;
    } else {
        $failures[] = $line;
    }
}

/**
 * Run two workers concurrently and return their parsed JSON results.
 *
 * @param  array<int, array<string, string>>  $specs
 * @return array<int, array<string, mixed>>
 */
function race(PDO $pdo, string $barrier, array $specs, string $php, string $worker, array $env): array
{
    $pdo->exec('DELETE FROM pos_race_barrier');
    $releaseAt = microtime(true) + 1.5;

    $procs = [];

    foreach ($specs as $i => $s) {
        $cmd = [
            $php, $worker,
            $barrier,
            (string) count($specs),
            (string) $releaseAt,
            $s['product_id'],
            $s['user_id'],
            $s['client_id'],
            $s['client_ref'],
            $s['mode'],
        ];

        $procs[$i] = [
            'p' => proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env),
            'pipes' => $pipes,
        ];
    }

    $results = [];

    foreach ($procs as $i => $proc) {
        $stdout = stream_get_contents($proc['pipes'][1]);
        $stderr = stream_get_contents($proc['pipes'][2]);
        fclose($proc['pipes'][1]);
        fclose($proc['pipes'][2]);
        proc_close($proc['p']);

        $line = trim($stdout);
        $decoded = json_decode($line, true);

        $results[$i] = is_array($decoded)
            ? $decoded
            : ['status' => 'unparsable', 'message' => $line.' | stderr: '.trim($stderr)];
    }

    return $results;
}

// ---------------------------------------------------------------------------
// fixture
// ---------------------------------------------------------------------------

$pdo->exec('CREATE TABLE IF NOT EXISTS pos_race_barrier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    pid INT NOT NULL,
    at DOUBLE NOT NULL
)');

$categoryId = (int) $pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();

if (! $categoryId) {
    $pdo->exec("INSERT INTO categories (name, slug, is_active, sort_order, created_at, updated_at)
        VALUES ('Race', 'race', 1, 0, NOW(), NOW())");
    $categoryId = (int) $pdo->lastInsertId();
}

/**
 * Wipe anything a previous scenario left behind and put a single unit of
 * stock back on the contended product.
 */
function resetFixture(PDO $pdo, int $categoryId, string $slug): int
{
    $old = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
    $old->execute([$slug]);
    $ids = $old->fetchAll(PDO::FETCH_COLUMN);

    if ($ids) {
        $list = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE id IN (SELECT order_id FROM order_items WHERE product_id IN ($list)))");
        $pdo->exec("DELETE FROM order_items WHERE product_id IN ($list)");
        $pdo->exec("DELETE FROM stock_histories WHERE product_id IN ($list)");
        $pdo->exec('DELETE FROM orders WHERE id NOT IN (SELECT order_id FROM order_items)');
        $pdo->exec("DELETE FROM products WHERE id IN ($list)");
    }

    $pdo->exec('DELETE FROM orders');
    $pdo->exec('DELETE FROM order_items');
    $pdo->exec('DELETE FROM payments');
    $pdo->exec('DELETE FROM stock_histories');

    $ins = $pdo->prepare('INSERT INTO products
        (category_id, name, slug, price, stock, sku, is_active, created_at, updated_at)
        VALUES (?, ?, ?, 100000, 1, ?, 1, NOW(), NOW())');
    $ins->execute([$categoryId, 'Produk Rame', $slug, 'RACE-'.strtoupper(bin2hex(random_bytes(3)))]);

    return (int) $pdo->lastInsertId();
}

function makeCashier(PDO $pdo, string $label): int
{
    $email = 'race-'.strtolower($label).'-'.bin2hex(random_bytes(3)).'@test.local';

    $pdo->prepare('INSERT INTO users (name, email, password, role, points, created_at, updated_at)
        VALUES (?, ?, ?, "cashier", 0, NOW(), NOW())')
        ->execute([$label, $email, '$2y$04$abcdefghijklmnopqrstuv']);

    return (int) $pdo->lastInsertId();
}

function uuid(): string
{
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0F | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3F | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function productStock(PDO $pdo, int $id): int
{
    $s = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $s->execute([$id]);

    return (int) $s->fetchColumn();
}

function countOrders(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
}

function countConflicts(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE stock_conflict = 1')->fetchColumn();
}

function stockNetChange(PDO $pdo, int $productId): int
{
    $s = $pdo->prepare('SELECT COALESCE(SUM(quantity_change), 0) FROM stock_histories WHERE product_id = ?');
    $s->execute([$productId]);

    return (int) $s->fetchColumn();
}

function spec(int $productId, int $userId, string $clientId, string $clientRef, string $mode): array
{
    return compact('productId', 'userId', 'clientId', 'clientRef', 'mode') + [
        'product_id' => (string) $productId,
        'user_id' => (string) $userId,
        'client_id' => $clientId,
        'client_ref' => $clientRef,
        'mode' => $mode,
    ];
}

echo "POS concurrency race harness\n";
echo 'engine: ', $pdo->query('SELECT VERSION()')->fetchColumn(), "\n\n";

// ===========================================================================
// 1. CLAMP race -- two different offline sales, one unit left
// ===========================================================================

echo "--- 1. clamp: dua kasir offline rebut 1 unit (client_ref beda) ---\n";
$productId = resetFixture($pdo, $categoryId, 'race-clamp');
$userA = makeCashier($pdo, 'Kasir A');
$userB = makeCashier($pdo, 'Kasir B');
$clientId = uuid();

$r = race($pdo, 'clamp', [
    spec($productId, $userA, $clientId, uuid(), 'clamp'),
    spec($productId, $userB, $clientId, uuid(), 'clamp'),
], $php, $worker, $env);

echo '  worker results: ', json_encode($r), "\n";

$synced = array_values(array_filter($r, fn ($x) => ($x['status'] ?? '') === 'synced'));

check('clamp', 'kedua kasir tetaperved order (uang tidak hilang)', count($synced) === 2,
    'synced='.count($synced).' statuses='.implode(',', array_column($r, 'status')));
check('clamp', 'stok habis tepat (0), tidak negatif', productStock($pdo, $productId) === 0,
    'stock='.productStock($pdo, $productId));
check('clamp', 'tepat 1 order ditandai konflik', countConflicts($pdo) === 1,
    'conflicts='.countConflicts($pdo));
check('clamp', '2 order tercatat di DB', countOrders($pdo) === 2, 'orders='.countOrders($pdo));
check('clamp', 'stok hanya berkurang 1x di stock history', stockNetChange($pdo, $productId) === -1,
    'net='.stockNetChange($pdo, $productId));

// ===========================================================================
// 2. DUPLICATE race -- the same offline sale replayed twice at once
// ===========================================================================

echo "\n--- 2. duplicate: 2 proses kirim client_ref yang SAMA bersamaan ---\n";
$productId = resetFixture($pdo, $categoryId, 'race-dup');
$userA = makeCashier($pdo, 'Kasir A');
$userB = makeCashier($pdo, 'Kasir B');
$clientId = uuid();
$sharedRef = uuid();

$r = race($pdo, 'dup', [
    spec($productId, $userA, $clientId, $sharedRef, 'clamp'),
    spec($productId, $userB, $clientId, $sharedRef, 'clamp'),
], $php, $worker, $env);

echo '  worker results: ', json_encode($r), "\n";

$orderIds = array_values(array_unique(array_filter(array_column($r, 'order_id'), fn ($v) => $v !== null)));

check('duplicate', 'hanya 1 order dibuat', countOrders($pdo) === 1, 'orders='.countOrders($pdo));
check('duplicate', 'kedua proses melaporkan order yang sama', count($orderIds) === 1,
    'order_ids='.json_encode($orderIds));
check('duplicate', 'tidak ada worker yang error', ! in_array('error', array_column($r, 'status'), true),
    'statuses='.implode(',', array_column($r, 'status')));
check('duplicate', 'stok hanya berkurang 1x', productStock($pdo, $productId) === 0,
    'stock='.productStock($pdo, $productId));
check('duplicate', 'stock history mencatat 1 perubahan', stockNetChange($pdo, $productId) === -1,
    'net='.stockNetChange($pdo, $productId));

// ===========================================================================
// 3. STRICT race -- two online sales, one unit left
// ===========================================================================

echo "\n--- 3. strict: dua kasir online rebut 1 unit ---\n";
$productId = resetFixture($pdo, $categoryId, 'race-strict');
$userA = makeCashier($pdo, 'Kasir A');
$userB = makeCashier($pdo, 'Kasir B');
$clientId = uuid();

$r = race($pdo, 'strict', [
    spec($productId, $userA, $clientId, uuid(), 'strict'),
    spec($productId, $userB, $clientId, uuid(), 'strict'),
], $php, $worker, $env);

echo '  worker results: ', json_encode($r), "\n";

$synced = array_values(array_filter($r, fn ($x) => ($x['status'] ?? '') === 'synced'));
$rejected = array_values(array_filter($r, fn ($x) => ($x['status'] ?? '') === 'rejected'));

check('strict', 'hanya 1 order berhasil', count($synced) === 1, 'synced='.count($synced));
check('strict', '1 order ditolak karena stok', count($rejected) === 1, 'rejected='.count($rejected));
check('strict', 'tidak ada yang error mendadak', ! in_array('error', array_column($r, 'status'), true),
    'statuses='.implode(',', array_column($r, 'status')));
check('strict', 'stok habis, tidak negatif', productStock($pdo, $productId) === 0,
    'stock='.productStock($pdo, $productId));
check('strict', 'tidak ada order konflik di mode strict', countConflicts($pdo) === 0,
    'conflicts='.countConflicts($pdo));

// ---------------------------------------------------------------------------

$pdo->exec('DELETE FROM pos_race_barrier');

echo "\n", str_repeat('-', 60), "\n";
printf("pass=%d fail=%d\n", count($passes), count($failures));

if ($failures) {
    echo "\nGAGAL:\n";

    foreach ($failures as $f) {
        echo '  - ', $f, "\n";
    }

    exit(1);
}

echo "SEMUA INVARIAN KONKURENSI AMAN\n";
