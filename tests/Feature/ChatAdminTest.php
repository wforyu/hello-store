<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_chat_conversations_list(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@hello-store.test')->firstOrFail();
        $this->actingAs($admin);

        $conversation = ChatConversation::create([
            'user_id' => null,
            'guest_session_id' => 'abc123',
            'guest_name' => 'Budi Guest',
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create([
            'sender_type' => 'customer',
            'message' => 'Halo admin, ada yang bisa dibantu?',
        ]);

        $response = $this->get('/admin/chat-conversations');

        $response->assertOk();
        $response->assertSee('Live Chat');
        $response->assertSee('Budi Guest');
    }

    public function test_non_admin_cannot_open_chat_conversations_list(): void
    {
        $this->seed();

        $customer = User::where('email', 'test@example.com')->firstOrFail();
        $this->actingAs($customer);

        $this->get('/admin/chat-conversations')->assertForbidden();
    }

    public function test_conversation_customer_label_uses_guest_name(): void
    {
        $conversation = ChatConversation::create([
            'user_id' => null,
            'guest_session_id' => 'guest-123',
            'guest_name' => 'Andi',
            'last_message_at' => now(),
        ]);

        $this->assertSame('Andi', $conversation->customer_label);
    }
}
