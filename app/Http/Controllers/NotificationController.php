<?php

namespace App\Http\Controllers;

use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        if ($notification->link_url) {
            $url = $notification->link_url;

            if (auth()->user()->role === 'admin' && preg_match('#/orders/(\d+)$#', $url)) {
                preg_match('#/orders/(\d+)$#', $url, $m);

                return redirect('/admin/resources/orders/'.$m[1].'/edit');
            }

            return redirect($url);
        }

        return back();
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'Semua notifikasi telah dibaca');
    }

    public function markAsReadJson(Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function indexJson()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'icon' => $n->icon,
                'link_url' => $n->link_url,
                'admin_url' => $this->computeAdminUrl($n),
                'is_read' => (bool) $n->is_read,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    private function computeAdminUrl(Notification $n): ?string
    {
        $url = $n->link_url;
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, '/admin/resources/') || str_starts_with($url, '/admin/pages/')) {
            return $url;
        }

        if (preg_match('#/orders/(\d+)/payment#', $url, $m)) {
            return '/admin/resources/orders/'.$m[1].'/edit';
        }

        if (preg_match('#/orders/(\d+)#', $url, $m)) {
            return '/admin/resources/orders/'.$m[1].'/edit';
        }

        if (preg_match('#/products/(\d+)/review#', $url, $m)) {
            return '/admin/resources/reviews/'.$m[1].'/edit';
        }

        if (str_contains($url, '/products/')) {
            return '/admin/resources/products';
        }

        if (str_contains($url, '/notifications')) {
            return null;
        }

        return $url;
    }
}
