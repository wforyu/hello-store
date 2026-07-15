<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Pesanan #{{ $order->order_number }}</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        @page {
            margin: 0;
            size: 80mm auto;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
        }
        body {
            background: #fff;
            color: #000;
            font-size: 11px;
            line-height: 1.4;
            padding: 10px 12px;
            width: 80mm;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px dashed #000;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .header p {
            font-size: 9px;
            color: #555;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .section {
            margin-bottom: 6px;
        }
        .section-title {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 3px;
            background: #eee;
            padding: 2px 4px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
        }
        .row .label {
            color: #555;
        }
        .row .value {
            font-weight: bold;
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 3px 0;
        }
        table th {
            font-size: 9px;
            text-transform: uppercase;
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 2px 0;
        }
        table td {
            padding: 2px 0;
            font-size: 10px;
        }
        table td:last-child,
        table th:last-child {
            text-align: right;
        }
        .total-row {
            font-size: 13px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 4px;
            margin-top: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 2px dashed #000;
            font-size: 9px;
            color: #555;
        }
        .barcode {
            text-align: center;
            margin: 6px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            letter-spacing: 2px;
            font-weight: bold;
        }
        .address-box {
            border: 1px solid #000;
            padding: 4px 6px;
            margin: 3px 0;
            font-size: 10px;
        }
        .status-badge {
            display: inline-block;
            border: 1px solid #000;
            padding: 1px 6px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 8px 10px;
            }
            .header h1 {
                font-size: 14px;
            }
            #qr-tracking img {
                width: 80px !important;
                height: 80px !important;
            }
            #qr-tracking canvas {
                width: 80px !important;
                height: 80px !important;
            }
        }
    </style>
</head>
<body>

    <div style="text-align:center; margin-bottom:4px;">
        <button class="no-print" onclick="window.print()" style="padding:8px 24px;font-size:14px;font-weight:bold;background:#f59e0b;border:none;border-radius:6px;color:#fff;cursor:pointer;margin-bottom:8px">
            🖨 Cetak
        </button>
    </div>

    {{-- Header --}}
    <div class="header">
        <h1>HELLO STORE</h1>
        <p>Toko Online Terpercaya</p>
        <div class="barcode">#{{ $order->order_number }}</div>
        <p>{{ $order->created_at->format('d/m/Y H:i') }}</p>
        <span class="status-badge">{{ strtoupper($order->status) }}</span>
    </div>

    {{-- Shipping Address --}}
    @if($order->address)
        <div class="section">
            <div class="section-title">Alamat Pengiriman</div>
            <div class="address-box">
                <strong>{{ $order->address->recipient }}</strong><br>
                {{ $order->address->phone }}<br>
                {{ $order->address->street }}<br>
                {{ $order->address->city }}, {{ $order->address->province }} {{ $order->address->postal_code }}
            </div>
        </div>
    @endif

    {{-- Courier --}}
    @if($order->shipping_courier)
        <div class="section">
            <div class="section-title">Kurir Pengiriman</div>
            <div class="row">
                <span class="label">Jasa Kurir</span>
                <span class="value">{{ strtoupper($order->shipping_courier) }}</span>
            </div>
            @if($order->shipping_tracking_number)
                <div class="row">
                    <span class="label">No Resi</span>
                    <span class="value" style="letter-spacing:1px">{{ $order->shipping_tracking_number }}</span>
                </div>
                <div style="text-align:center;margin:8px 0 4px 0">
                    <div id="qr-tracking" style="display:inline-block"></div>
                    <p style="font-size:8px;color:#555;margin-top:2px">Scan untuk lacak pengiriman</p>
                </div>
            @endif
        </div>
    @endif

    <div class="divider"></div>

    {{-- Items --}}
    <div class="section">
        <div class="section-title">Item Pesanan</div>
        <table>
            <thead>
                <tr>
                    <th style="width:55%">Produk</th>
                    <th style="width:15%;text-align:center">Qty</th>
                    <th style="width:30%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td style="text-align:center">{{ $item->quantity }}</td>
                        <td>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="divider"></div>

    {{-- Totals --}}
    @php
        $ppnFromReceipt = 0;
        $ppnRateReceipt = 0;
        if ($order->notes && str_contains($order->notes, 'PPN ')) {
            preg_match('/PPN (\d+)%: Rp ([\d.]+)/', $order->notes, $m);
            if ($m) {
                $ppnRateReceipt = (int) $m[1];
                $ppnFromReceipt = (int) str_replace('.', '', $m[2]);
            }
        }
    @endphp
    <div class="section">
        <div class="row">
            <span class="label">Subtotal</span>
            <span>Rp{{ number_format($order->subtotal, 0, ',', '.') }}</span>
        </div>
        <div class="row">
            <span class="label">Ongkos Kirim</span>
            <span>Rp{{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
        </div>
        @if($ppnFromReceipt > 0)
            <div class="row">
                <span class="label">PPN {{ $ppnRateReceipt }}%</span>
                <span>+Rp{{ number_format($ppnFromReceipt, 0, ',', '.') }}</span>
            </div>
        @endif
        @if($order->discount > 0)
            <div class="row" style="color:#059669;">
                <span class="label">Diskon Kupon</span>
                <span>-Rp{{ number_format($order->discount, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="row total-row">
            <span>TOTAL</span>
            <span>Rp{{ number_format($order->total, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- Payment --}}
    <div class="section">
        <div class="section-title">Pembayaran</div>
        <div class="row">
            <span class="label">Metode</span>
            <span class="value">{{ $order->payment_method === 'manual_transfer' ? 'Transfer Manual' : 'COD' }}</span>
        </div>
        <div class="row">
            <span class="label">Status</span>
            <span class="value">{{ ucfirst($order->payment_status) }}</span>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>Terima kasih telah berbelanja di Hello Store!</p>
        <p style="margin-top:2px;font-size:8px">{{ $order->order_number }} | {{ $order->created_at->format('d/m/Y H:i') }}</p>
    </div>

    @if($order->shipping_tracking_number)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('qr-tracking');
            if (el) {
                new QRCode(el, {
                    text: '{{ $order->shipping_tracking_number }}',
                    width: 100,
                    height: 100,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
    </script>
    @endif

</body>
</html>
