<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatApiController extends Controller
{
    private function conversationForAuthUser(int $userId): ChatConversation
    {
        return ChatConversation::firstOrCreate(
            ['user_id' => $userId, 'is_open' => true],
            ['last_message_at' => now()]
        );
    }

    public function index(): JsonResponse
    {
        $conversation = $this->conversationForAuthUser(auth()->id());
        $conversation->load('messages');

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'messages' => $conversation->messages->map(fn ($m) => $this->formatMessage($m)),
            ],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $conversation = $this->conversationForAuthUser(auth()->id());

        $message = $conversation->messages()->create([
            'user_id' => auth()->id(),
            'sender_type' => 'customer',
            'message' => $validated['message'],
        ]);

        $conversation->update(['last_message_at' => now(), 'is_open' => true]);

        Notification::createForAdmins(
            'chat',
            'Pesan Baru dari '.auth()->user()->name,
            Str::limit($validated['message'], 120),
            null,
            null
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatMessage($message),
        ]);
    }

    public function poll(Request $request, ChatConversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Konten tidak ditemukan.'], 403);
        }

        $afterId = (int) $request->get('after_id', 0);
        $messages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    private function formatMessage($message): array
    {
        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'message' => $message->message,
            'created_at' => $message->created_at,
            'read' => $message->read_at !== null,
        ];
    }
}
