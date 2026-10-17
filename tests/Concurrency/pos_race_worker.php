<?php

/**
 * Concurrency worker: one OS process per "cashier".
 *
 * Two of these are spawned in parallel and held at a database barrier so they
 * hit PosService::checkout() at the same instant. That is the only way to
 * really exercise the InnoDB row lock and the (client_id, client_ref) unique
 * index -- a normal PHPUnit feature test runs single threaded in one process,
 * so it can never produce a real race even when it says "concurrency".
 *
 * Usage:
 *   php pos_race_worker.php <barrier> <arrivals> <releaseAt> <productId> \
 *       <userId> <clientId> <clientRef> <mode>
 *
 * Prints a single JSON object on stdout.
 */

use App\Exceptions\PosException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$self, $barrier, $arrivals, $releaseAt, $productId, $userId, $clientId, $clientRef, $mode] = $argv;

$out = ['pid' => getmypid(), 'mode' => $mode, 'client_ref' => $clientRef];

try {
    $cashier = User::findOrFail((int) $userId);
    $product = Product::findOrFail((int) $productId);

    // --- arrive at the barrier -------------------------------------------
    DB::table('pos_race_barrier')->insert([
        'name' => $barrier,
        'pid' => getmypid(),
        'at' => microtime(true),
    ]);

    $deadline = microtime(true) + 30;
    while (DB::table('pos_race_barrier')->where('name', $barrier)->count() < (int) $arrivals) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('barrier timeout: not all workers arrived');
        }
        usleep(1000);
    }

    // --- align on a shared wall-clock deadline so both fire together ------
    while (microtime(true) < (float) $releaseAt) {
        usleep(200);
    }

    // --- exactly what Api\PosController::sync() does per entry ------------
    $existing = Order::where('client_id', $clientId)->where('client_ref', $clientRef)->first();

    if ($existing) {
        $out['status'] = 'duplicate';
        $out['order_id'] = $existing->id;
    } else {
        try {
            $result = (new PosService)->checkout(
                $cashier,
                collect([[
                    'product_id' => (int) $productId,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => 1,
                    'discount' => 0,
                    'discount_type' => 'nominal',
                ]]),
                [
                    'mode' => $mode,
                    'payment_method' => 'cash',
                    'amount_paid' => (float) $product->price,
                    'order_type' => 'dine_in',
                    'client_id' => $clientId,
                    'client_ref' => $clientRef,
                ]
            );

            $out['status'] = 'synced';
            $out['order_id'] = $result['order']->id;
            $out['stock_conflict'] = $result['order']->stock_conflict;
            $out['conflicts'] = $result['conflicts'];
        } catch (QueryException $e) {
            // Two workers raced the same client_ref: the unique index settled
            // it, so report the winner instead of exploding.
            $duplicate = Order::where('client_id', $clientId)->where('client_ref', $clientRef)->first();

            if ($duplicate) {
                $out['status'] = 'duplicate';
                $out['order_id'] = $duplicate->id;
            } else {
                throw $e;
            }
        } catch (PosException $e) {
            $out['status'] = 'rejected';
            $out['message'] = $e->getMessage();
        }
    }
} catch (Throwable $e) {
    $out['status'] = 'error';
    $out['message'] = $e->getMessage();
    $out['class'] = $e::class;
}

echo json_encode($out), "\n";
