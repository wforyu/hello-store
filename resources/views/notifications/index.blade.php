@extends('layouts.account')

@section('title', 'Notifikasi')

@section('account-content')
<div class="max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-900">Notifikasi</h1>
        @if($notifications->where('is_read', false)->count() > 0)
            <form action="{{ route('notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm font-medium text-amber-600 hover:text-amber-700 transition">
                    Tandai Semua Dibaca
                </button>
            </form>
        @endif
    </div>

    @if($notifications->count() > 0)
        <div class="space-y-2">
            @foreach($notifications as $notification)
                <div class="bg-white rounded-xl border {{ $notification->is_read ? 'border-gray-100' : 'border-amber-200 bg-amber-50/30' }} p-4 transition hover:shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center
                            @switch($notification->type)
                                @case('order') bg-blue-100 text-blue-600 @break
                                @case('promo') bg-pink-100 text-pink-600 @break
                                @case('voucher') bg-purple-100 text-purple-600 @break
                                @case('review') bg-green-100 text-green-600 @break
                                @case('stock') bg-orange-100 text-orange-600 @break
                                @default bg-gray-100 text-gray-600
                            @endswitch">
                            @switch($notification->type)
                                @case('order')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    @break
                                @case('promo')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                    </svg>
                                    @break
                                @case('voucher')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                    @break
                                @default
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                            @endswitch
                        </div>
                        <div class="flex-1 min-w-0">
                            @if($notification->link_url)
                                <a href="{{ route('notifications.read', $notification) }}" class="block">
                            @endif
                                <p class="text-sm font-medium text-gray-900">{{ $notification->title }}</p>
                                @if($notification->body)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $notification->body }}</p>
                                @endif
                                <p class="text-xs text-gray-400 mt-1.5">{{ $notification->created_at->diffForHumans() }}</p>
                            @if($notification->link_url)
                                </a>
                            @endif
                        </div>
                        @if(!$notification->is_read)
                            <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0 mt-1.5"></span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @else
        <div class="text-center py-16 bg-white rounded-2xl border border-gray-100">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Tidak Ada Notifikasi</h2>
            <p class="text-sm text-gray-400">Kami akan memberitahu kamu jika ada update.</p>
        </div>
    @endif
</div>
@endsection
