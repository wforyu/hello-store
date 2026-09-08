<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    private function conversationFor(Request $request): ChatConversation
    {
        if (auth()->check()) {
            $conversation = ChatConversation::where('user_id', auth()->id())
                ->where('is_open', true)
                ->latest('last_message_at')
                ->first();
        } else {
            $sessionId = $request->session()->get('chat_guest_session');
            if (! $sessionId) {
                $sessionId = Str::random(24);
                $request->session()->put('chat_guest_session', $sessionId);
            }
            $conversation = ChatConversation::where('guest_session_id', $sessionId)
                ->where('is_open', true)
                ->latest('last_message_at')
                ->first();
        }

        if (! $conversation) {
            $conversation = ChatConversation::create([
                'user_id' => auth()->id(),
                'guest_session_id' => auth()->check() ? null : $sessionId,
                'guest_name' => auth()->check() ? null : ($request->session()->get('chat_guest_name') ?? 'Guest'),
                'last_message_at' => now(),
            ]);
        }

        return $conversation;
    }

    public function index(Request $request)
    {
        $conversation = $this->conversationFor($request);
        $messages = $conversation->messages()->latest()->limit(50)->get()->reverse()->values();

        return view('store.chat', compact('conversation', 'messages'));
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'guest_name' => 'nullable|string|max:100',
        ]);

        $conversation = $this->conversationFor($request);

        if (! auth()->check() && filled($validated['guest_name'] ?? null)) {
            $conversation->update(['guest_name' => $validated['guest_name']]);
            $request->session()->put('chat_guest_name', $validated['guest_name']);
        }

        $message = $conversation->messages()->create([
            'user_id' => auth()->id(),
            'sender_type' => 'customer',
            'message' => $validated['message'],
        ]);

        $conversation->update(['last_message_at' => now(), 'is_open' => true]);

        Notification::createForAdmins(
            'chat',
            'Pesan Baru dari '.($conversation->customerName()),
            Str::limit($validated['message'], 120),
            null,
            null
        );

        return response()->json([
            'success' => true,
            'message' => $message->load('user'),
            'conversation_id' => $conversation->id,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $conversation = $this->conversationFor($request);
        $afterId = (int) $request->get('after_id', 0);

        $messages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'conversation_id' => $conversation->id,
        ]);
    }
}
