<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GuestOrderController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:32',
            'guest_email' => 'required|email|max:255',
        ]);

        $order = Order::where('order_number', $validated['order_number'])
            ->where('guest_email', $validated['guest_email'])
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan. Periksa kembali nomor pesanan dan email.',
            ], 404);
        }

        if (! $order->isGuest()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan ini terkait akun terdaftar. Silakan login untuk melihat detail.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'token' => $order->guest_token,
                'order' => $this->formatOrder($order->load(['items', 'payment', 'address']), true),
            ],
            'message' => null,
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if (! $this->authorizeGuest($request, $order)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        $order->load(['items', 'payment', 'address']);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order, true),
            'message' => null,
        ]);
    }

    public function paymentUpload(Request $request, Order $order): JsonResponse
    {
        if (! $this->authorizeGuest($request, $order)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        $validated = $request->validate([
            'proof_image' => 'required|image|max:2048',
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
        ]);

        $payment = $order->payment;
        if (! $payment) {
            $payment = new Payment(['order_id' => $order->id]);
        } else {
            if ($payment->proof_image) {
                Storage::disk('public')->delete($payment->proof_image);
            }
        }

        $payment->method = 'manual_transfer';
        $payment->amount = $order->total;
        $payment->status = 'paid';
        $payment->paid_at = now();
        $payment->bank_name = $validated['bank_name'];
        $payment->account_name = $validated['account_name'];
        $payment->account_number = $validated['account_number'];
        $uploadedFile = $request->file('proof_image');
        $payment->proof_image = $uploadedFile ? $uploadedFile->store('payments', 'public') : $payment->proof_image;
        $payment->save();

        $order->update([
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);

        if ($order->user_id) {
            Notification::createForUser(
                $order->user_id,
                'order',
                'Pembayaran Pesanan #'.$order->order_number.' diterima',
                'Pesanan sedang diproses.',
                null,
                null
            );
        }

        Notification::createForAdmins(
            'order',
            'Pembayaran Diterima #'.$order->order_number,
            'Bukti pembayaran pesanan guest ('.($order->guest_name ?: 'guest').') diupload.',
            null,
            null
        );

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Bukti pembayaran berhasil diupload.',
        ]);
    }

    public function confirmReceived(Request $request, Order $order): JsonResponse
    {
        if (! $this->authorizeGuest($request, $order)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        if ($order->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak dalam status dikirim.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'payment_status' => 'paid',
            ]);

            if ($order->user_id) {
                $user = $order->user;
                if ($user) {
                    $user->increment('total_spent', $order->total);

                    $pointsRate = $user->getPointsRate();
                    $pointsEarned = (int) floor($order->total * $pointsRate);
                    if ($pointsEarned > 0) {
                        $user->addPoints($pointsEarned, 'Poin dari pesanan #'.$order->order_number, $order);
                    }

                    $user->autoUpgradeSegment();
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Pesanan telah diterima. Terima kasih!',
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if (! $this->authorizeGuest($request, $order)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Hanya pesanan dengan status menunggu yang dapat dibatalkan.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->load('items.product');

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            foreach ($order->items as $item) {
                if (! empty($item->product_variant_id)) {
                    $variant = ProductVariant::find($item->product_variant_id);
                    if ($variant) {
                        $variant->increment('stock', $item->quantity);
                    }
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->recordStockHistory($item->quantity, 'order', 'Pembatalan pesanan #'.$order->order_number.' - varian', Order::class, $order->id);
                    }
                } else {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                        $product->recordStockHistory($item->quantity, 'order', 'Pembatalan pesanan #'.$order->order_number, Order::class, $order->id);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Pesanan berhasil dibatalkan.',
        ]);
    }

    private function authorizeGuest(Request $request, Order $order): bool
    {
        if (! $order->isGuest() || ! $order->guest_token) {
            return false;
        }

        $token = (string) $request->input('token', $request->header('X-Guest-Token', ''));

        return hash_equals($order->guest_token, $token);
    }

    private function formatOrder($order, bool $detail = false): array
    {
        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => match ($order->status) {
                'pending' => 'Menunggu Pembayaran',
                'processing' => 'Diproses',
                'shipped' => 'Dikirim',
                'delivered' => 'Selesai',
                'cancelled' => 'Dibatalkan',
                'refunded' => 'Diretur',
                default => $order->status,
            },
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'subtotal' => (float) $order->subtotal,
            'subtotal_formatted' => 'Rp'.number_format($order->subtotal, 0, ',', '.'),
            'shipping_cost' => (float) $order->shipping_cost,
            'shipping_courier' => $order->shipping_courier,
            'total' => (float) $order->total,
            'total_formatted' => 'Rp'.number_format($order->total, 0, ',', '.'),
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'payment_status_label' => match ($order->payment_status) {
                'unpaid' => 'Belum Dibayar',
                'paid' => 'Lunas',
                'refunded' => 'Dikembalikan',
                default => $order->payment_status,
            },
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'cancelled_at' => $order->cancelled_at,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'bundle_name' => $item->bundle_name,
                'product_price' => (float) $item->product_price,
                'product_price_formatted' => 'Rp'.number_format($item->product_price, 0, ',', '.'),
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'subtotal_formatted' => 'Rp'.number_format($item->subtotal, 0, ',', '.'),
            ]),
        ];

        if ($order->relationLoaded('payment') && $order->payment) {
            $payment = $order->payment;
            $data['payment'] = [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'proof_image_url' => $payment->proof_image ? Storage::url($payment->proof_image) : null,
                'bank_name' => $payment->bank_name,
                'account_name' => $payment->account_name,
                'account_number' => $payment->account_number,
                'paid_at' => $payment->paid_at,
            ];
        }

        if ($detail && $order->relationLoaded('address') && $order->address) {
            $addr = $order->address;
            $data['address'] = [
                'label' => $addr->label,
                'recipient' => $addr->recipient,
                'phone' => $addr->phone,
                'street' => $addr->street,
                'city' => $addr->city,
                'province' => $addr->province,
                'postal_code' => $addr->postal_code,
            ];
        }

        return $data;
    }
}
