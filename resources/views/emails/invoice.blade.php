<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Pesanan #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 24px;
            color: #111827;
        }
        .card {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .header {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: #fff;
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
        }
        .header p {
            margin: 4px 0 0;
            font-size: 12px;
            opacity: 0.9;
        }
        .status {
            background: #fff;
            color: #b45309;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .body {
            padding: 24px 28px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .row .label {
            color: #6b7280;
        }
        .row .value {
            font-weight: 600;
        }
        .total {
            display: flex;
            justify-content: space-between;
            padding: 14px 28px;
            background: #fef3c7;
            font-size: 18px;
            font-weight: 700;
            color: #b45309;
        }
        .items {
            margin: 16px 0;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .items-head, .items-row {
            display: flex;
            padding: 10px 16px;
            font-size: 13px;
        }
        .items-head {
            background: #f9fafb;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            font-size: 11px;
        }
        .col-name { flex: 1; }
        .col-qty { width: 60px; text-align: center; }
        .col-price { width: 110px; text-align: right; }
        .items-row {
            border-top: 1px solid #f3f4f6;
            align-items: center;
        }
        .footer {
            text-align: center;
            padding: 16px;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <h1>Hello Store</h1>
                <p>Invoice Resmi</p>
            </div>
            <span class="status">{{ $order->status }}</span>
        </div>

        <div class="body">
            <div class="row">
                <span class="label">Nomor Pesanan</span>
                <span class="value">{{ $order->order_number }}</span>
            </div>
            <div class="row">
                <span class="label">Tanggal</span>
                <span class="value">{{ $order->created_at->format('d M Y, H:i') }}</span>
            </div>
            <div class="row">
                <span class="label">Pelanggan</span>
                <span class="value">{{ $order->customer_name }}</span>
            </div>
            <div class="row">
                <span class="label">Metode Pembayaran</span>
                <span class="value">{{ $order->payment_method === 'manual_transfer' ? 'Transfer Manual' : 'COD' }}</span>
            </div>

            <div class="items">
                <div class="items-head">
                    <span class="col-name">Produk</span>
                    <span class="col-qty">Qty</span>
                    <span class="col-price">Subtotal</span>
                </div>
                @foreach($order->items as $item)
                    <div class="items-row">
                        <span class="col-name">
                            {{ $item->product_name }}
                            @if($item->bundle_name)
                                <br><small>(Paket: {{ $item->bundle_name }})</small>
                            @endif
                        </span>
                        <span class="col-qty">{{ $item->quantity }}</span>
                        <span class="col-price">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            <div class="row">
                <span class="label">Subtotal</span>
                <span class="value">Rp{{ number_format($order->subtotal, 0, ',', '.') }}</span>
            </div>
            @php
                $ppnInvoice = 0;
                $ppnRateInvoice = 0;
                if ($order->notes && str_contains($order->notes, 'PPN ')) {
                    preg_match('/PPN (\d+)%: Rp ([\d.]+)/', $order->notes, $m);
                    if ($m) {
                        $ppnRateInvoice = (int) $m[1];
                        $ppnInvoice = (int) str_replace('.', '', $m[2]);
                    }
                }
            @endphp
            @if($ppnInvoice > 0)
                <div class="row">
                    <span class="label">PPN {{ $ppnRateInvoice }}%</span>
                    <span class="value">+Rp{{ number_format($ppnInvoice, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="row">
                <span class="label">Ongkos Kirim</span>
                <span class="value">Rp{{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
            </div>
            @if($order->discount > 0)
                <div class="row">
                    <span class="label" style="color:#059669">Diskon Kupon</span>
                    <span class="value" style="color:#059669">-Rp{{ number_format($order->discount, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <div class="total">
            <span>TOTAL</span>
            <span>Rp{{ number_format($order->total, 0, ',', '.') }}</span>
        </div>

        <div class="footer">
            Terima kasih telah berbelanja di Hello Store!<br>
            Untuk pertanyaan, hubungi kami.
        </div>
    </div>
</body>
</html>