<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS #{{ $order->order_number }}</title>
    <style>
        @page { margin: 0; size: 80mm auto; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Courier New', monospace; }
        body { background: #fff; color: #000; font-size: 11px; line-height: 1.4; padding: 10px 12px; width: 80mm; }
        .header { text-align: center; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 2px dashed #000; }
        .header h1 { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .header p { font-size: 9px; color: #555; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .section { margin-bottom: 6px; }
        .section-title { font-weight: bold; font-size: 10px; text-transform: uppercase; margin-bottom: 3px; background: #eee; padding: 2px 4px; }
        .row { display: flex; justify-content: space-between; padding: 1px 0; }
        .row .label { color: #555; }
        .row .value { font-weight: bold; text-align: right; }
        table { width: 100%; border-collapse: collapse; margin: 3px 0; }
        table th { font-size: 9px; text-transform: uppercase; text-align: left; border-bottom: 1px solid #000; padding: 2px 0; }
        table td { padding: 2px 0; font-size: 10px; }
        table td:last-child, table th:last-child { text-align: right; }
        .grand-total { font-size: 16px; font-weight: bold; text-align: center; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; margin: 6px 0; }
        .footer { text-align: center; margin-top: 8px; padding-top: 6px; border-top: 2px dashed #000; font-size: 9px; color: #555; }
        .barcode { text-align: center; margin: 6px 0; font-family: 'Courier New', monospace; font-size: 14px; letter-spacing: 2px; font-weight: bold; }
        @media print { .no-print { display: none !important; } body { padding: 8px 10px; } }
        .order-type { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .discount-row { color: #dc2626; }
    </style>
</head>
<body>

    <div style="text-align:center; margin-bottom:4px;">
        <button class="no-print" onclick="window.print()" style="padding:8px 24px;font-size:14px;font-weight:bold;background:#f59e0b;border:none;border-radius:6px;color:#fff;cursor:pointer;margin-bottom:8px">
            🖨 Cetak
        </button>
    </div>

    @php
        $notes = $order->notes ?? '';
        $parts = explode(' | ', $notes);
        $firstPart = $parts[0] ?? '';

        // Parse order type & customer name from "Dine-in - John" or "Takeaway - John"
        $orderType = 'Takeaway';
        $customerName = 'Umum';
        if (str_starts_with($firstPart, 'Dine-in')) {
            $orderType = 'Dine-in';
            $customerName = trim(substr($firstPart, 8));
        } elseif (str_starts_with($firstPart, 'Takeaway')) {
            $orderType = 'Takeaway';
            $customerName = trim(substr($firstPart, 9));
        }
        if (!$customerName) $customerName = 'Umum';

        // Parse discount from notes
        $discountFromNotes = 0;
        $ppnFromNotes = 0;
        $ppnRate = 0;
        foreach ($parts as $part) {
            if (str_starts_with($part, 'Diskon: Rp ')) {
                $discountFromNotes = (int) str_replace(['Diskon: Rp ', '.'], '', $part);
            }
            if (str_starts_with($part, 'PPN ') && str_contains($part, ': Rp ')) {
                // Extract percentage from "PPN 11%: Rp 5.000"
                if (preg_match('/^PPN (\d+)%: Rp ([\d.]+)$/', $part, $m)) {
                    $ppnRate = (int) $m[1];
                    $ppnFromNotes = (int) str_replace('.', '', $m[2]);
                }
            }
        }

        $paymentMethodLabel = match ($order->payment_method) {
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            'debit_card' => 'Debit',
            'bank_transfer' => 'Transfer',
            default => ucfirst($order->payment_method ?? 'Tunai'),
        };
    @endphp

    <div class="header">
        <h1>HELLO STORE</h1>
        <p>Toko Online Terpercaya</p>
        <div class="barcode">#{{ $order->order_number }}</div>
        <p>{{ $order->created_at->format('d/m/Y H:i') }}</p>
        <p class="order-type">{{ $orderType }}</p>
        <p style="font-size:9px;margin-top:2px">Kasir: {{ $order->user?->name ?? 'Akun dihapus' }}</p>
    </div>

    <div class="section">
        <div class="section-title">Item</div>
        <table>
            <thead>
                <tr>
                    <th style="width:50%">Produk</th>
                    <th style="width:15%;text-align:center">Qty</th>
                    <th style="width:35%">Subtotal</th>
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

    <div class="section">
        <div class="row"><span class="label">Subtotal</span><span>Rp{{ number_format($order->subtotal, 0, ',', '.') }}</span></div>
        @if($discountFromNotes > 0)
            <div class="row discount-row"><span class="label">Diskon</span><span>-Rp{{ number_format($discountFromNotes, 0, ',', '.') }}</span></div>
        @endif
        @if($ppnFromNotes > 0)
            <div class="row"><span class="label">PPN {{ $ppnRate }}%</span><span>+Rp{{ number_format($ppnFromNotes, 0, ',', '.') }}</span></div>
        @endif
        <div class="grand-total">Rp{{ number_format($order->total, 0, ',', '.') }}</div>
    </div>

    <div class="section">
        <div class="section-title">Pembayaran</div>
        <div class="row"><span class="label">Metode</span><span class="value">{{ $paymentMethodLabel }}</span></div>
        <div class="row"><span class="label">Pelanggan</span><span class="value">{{ $customerName }}</span></div>
    </div>

    <div class="footer">
        <p>Terima kasih telah berbelanja!</p>
        <p style="margin-top:2px;font-size:8px">{{ $order->order_number }} | {{ $order->created_at->format('d/m/Y H:i') }}</p>
    </div>

</body>
</html>
