<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Barcode</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; }

        @page {
            margin: 10mm;
            size: auto;
        }

        .label-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-start;
        }

        .label-item {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: center;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .label-item.small { width: 120px; min-height: 70px; }
        .label-item.medium { width: 180px; min-height: 100px; }
        .label-item.large { width: 240px; min-height: 130px; }

        .label-item .name {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .label-item .price {
            font-size: 8px;
            color: #666;
            margin-bottom: 3px;
        }

        .label-item .sku {
            font-size: 7px;
            color: #999;
            margin-top: 2px;
        }

        .barcode-svg svg {
            max-width: 100%;
            height: auto;
        }

        .label-item.small .barcode-svg svg { height: 25px; }
        .label-item.medium .barcode-svg svg { height: 35px; }
        .label-item.large .barcode-svg svg { height: 50px; }

        .no-print { margin-bottom: 20px; }
        @media print {
            .no-print { display: none; }
        }

        .qr-code svg { width: 60px; height: 60px; }
        .label-item.small .qr-code svg { width: 40px; height: 40px; }
        .label-item.medium .qr-code svg { width: 60px; height: 60px; }
        .label-item.large .qr-code svg { width: 80px; height: 80px; }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()"
            style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 8px; font-size: 14px; cursor: pointer;">
            🖨️ Cetak
        </button>
        <button onclick="window.close()"
            style="padding: 10px 20px; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; margin-left: 8px;">
            Tutup
        </button>
    </div>

    <div class="label-container">
        @foreach($products as $product)
            @for($i = 0; $i < 2; $i++)
                <div class="label-item {{ $labelSize }}">
                    <div class="name">{{ $product->name }}</div>
                    <div class="price">Rp {{ number_format($product->price, 0, ',', '.') }}</div>
                    <div class="barcode-svg">
                        @php
                            $barcodeValue = $product->sku ?? (string) $product->id;
                            if ($type === 'ean13') {
                                $barcodeValue = str_pad(preg_replace('/[^0-9]/', '', $barcodeValue), 12, '0', STR_PAD_LEFT);
                                $sum = 0;
                                for ($j = 0; $j < 12; $j++) {
                                    $sum += (int)$barcodeValue[$j] * ($j % 2 === 0 ? 1 : 3);
                                }
                                $check = (10 - ($sum % 10)) % 10;
                                $barcodeValue .= $check;
                            } elseif ($type === 'code128') {
                                $barcodeValue = preg_replace('/[^A-Za-z0-9\-_\/\s]/', '', substr($barcodeValue, 0, 20));
                            } elseif ($type === 'qr') {
                                $barcodeValue = route('products.show', $product->slug);
                            }
                        @endphp

                        @if($type === 'qr')
                            @php
                                $qrData = $barcodeValue;
                                $qrLen = strlen($qrData);
                                $size = $labelSize === 'small' ? 40 : ($labelSize === 'medium' ? 60 : 80);
                            @endphp
                            <div class="qr-code">
                                <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="{{ $size }}" height="{{ $size }}" fill="white"/>
                                    @php
                                        $hash = md5($qrData);
                                        $chars = str_split($hash);
                                        $step = 4;
                                        for ($row = 0; $row < $size; $row += $step) {
                                            for ($col = 0; $col < $size; $col += $step) {
                                                $idx = (($row / $step) * ($size / $step) + ($col / $step)) % count($chars);
                                                $val = hexdec($chars[$idx]);
                                                if ($val > 8) {
                                                    echo "<rect x=\"$col\" y=\"$row\" width=\"$step\" height=\"$step\" fill=\"black\"/>";
                                                }
                                            }
                                        }
                                        $mSize = 10;
                                        $markers = [[0,0], [$size-$mSize,0], [0,$size-$mSize]];
                                        foreach ($markers as $m) {
                                            echo "<rect x=\"{$m[0]}\" y=\"{$m[1]}\" width=\"$mSize\" height=\"$mSize\" fill=\"black\" rx=\"0\"/>";
                                            echo "<rect x=\"".($m[0]+2)."\" y=\"".($m[1]+2)."\" width=\"".($mSize-4)."\" height=\"".($mSize-4)."\" fill=\"white\" rx=\"0\"/>";
                                            echo "<rect x=\"".($m[0]+4)."\" y=\"".($m[1]+4)."\" width=\"".($mSize-8)."\" height=\"".($mSize-8)."\" fill=\"black\" rx=\"0\"/>";
                                        }
                                    @endphp
                                </svg>
                            </div>
                        @else
                            <svg height="{{ $labelSize === 'small' ? 25 : ($labelSize === 'medium' ? 35 : 50) }}" width="100%" xmlns="http://www.w3.org/2000/svg">
                                @php
                                    $chars = str_split($barcodeValue);
                                    $barCount = count($chars) * 3;
                                    $width = 100;
                                    $barWidth = $width / $barCount;
                                    $height = $labelSize === 'small' ? 25 : ($labelSize === 'medium' ? 35 : 50);
                                    $x = 0;
                                    foreach ($chars as $c) {
                                        $code = (ord($c) % 8) + 1;
                                        for ($b = 0; $b < 3; $b++) {
                                            $isBlack = ($code >> $b) & 1;
                                            $w = $barWidth * (1 + ($b % 2));
                                            if ($isBlack) {
                                                echo "<rect x=\"$x\" y=\"0\" width=\"$w\" height=\"$height\" fill=\"black\"/>";
                                            }
                                            $x += $w;
                                        }
                                    }
                                @endphp
                            </svg>
                            <div class="sku">{{ $barcodeValue }}</div>
                        @endif
                    </div>
                </div>
            @endfor
        @endforeach
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
