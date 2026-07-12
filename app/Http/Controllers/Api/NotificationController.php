<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()
            ->notifications()
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    public function markAsRead(Notification $notification, Request $request): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()
            ->notifications()
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
