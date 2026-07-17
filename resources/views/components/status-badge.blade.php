@props(['status', 'size' => 'sm'])

@php
    $statusMap = [
        'pending' => ['label' => 'Menunggu', 'bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200'],
        'confirmed' => ['label' => 'Confirmed', 'bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200'],
        'processing' => ['label' => 'Diproses', 'bg' => 'bg-cyan-50', 'text' => 'text-cyan-700', 'border' => 'border-cyan-200'],
        'shipped' => ['label' => 'Dikirim', 'bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200'],
        'delivered' => ['label' => 'Diterima', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200'],
        'refunded' => ['label' => 'Diretur', 'bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-200'],
        'completed' => ['label' => 'Selesai', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200'],
        'cancelled' => ['label' => 'Dibatalkan', 'bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-200'],
    ];
    $info = $statusMap[$status] ?? ['label' => ucfirst($status), 'bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200'];
    $sizeClasses = $size === 'lg' ? 'text-sm font-bold px-4 py-2' : 'text-xs font-bold px-3 py-1.5';
@endphp

<span class="{{ $sizeClasses }} {{ $info['bg'] }} {{ $info['text'] }} border {{ $info['border'] }} rounded-full w-fit">
    {{ $info['label'] }}
</span>
