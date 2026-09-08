<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_chat_page(): void
    {
        $response = $this->get(route('chat.index'));

        $response->assertOk();
        $response->assertSee('Live Chat Support');
        $this->assertDatabaseHas('chat_conversations', ['user_id' => null]);
    }

    public function test_guest_can_send_message(): void
    {
        $this->seed();

        $this->get(route('chat.index'));

        $response = $this->postJson(route('chat.send'), [
            'message' => 'Halo, produk ini masih ada?',
            'guest_name' => 'Budi',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('chat_messages', ['sender_type' => 'customer', 'message' => 'Halo, produk ini masih ada?']);
        $this->assertDatabaseHas('chat_conversations', ['guest_name' => 'Budi']);
    }

    public function test_poll_returns_new_messages(): void
    {
        $this->seed();

        $this->get(route('chat.index'));

        $this->postJson(route('chat.send'), ['message' => 'Pesan pertama']);

        $conversation = ChatConversation::where('user_id', null)->firstOrFail();
        $message = $conversation->messages()->first();

        $response = $this->getJson(route('chat.poll', ['after_id' => $message->id]));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonCount(0, 'messages');
    }

    public function test_authenticated_user_chat_is_linked_to_account(): void
    {
        $this->seed();

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->actingAs($user);

        $this->get(route('chat.index'));
        $this->postJson(route('chat.send'), ['message' => 'Saya sudah login']);

        $this->assertDatabaseHas('chat_conversations', ['user_id' => $user->id]);
        $this->assertDatabaseHas('chat_messages', ['sender_type' => 'customer', 'user_id' => $user->id]);
    }
}
