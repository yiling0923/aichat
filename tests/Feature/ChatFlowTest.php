<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatFlowTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'demo']);
    }

    private function send(string $text, ?string $token = null)
    {
        return $this->postJson(route('chat.send', 'demo'), ['text' => $text, 'token' => $token]);
    }

    public function test_chat_page_opens_for_an_active_business(): void
    {
        $this->get('/chat/demo')->assertOk()->assertSee($this->business->name);
    }

    public function test_unknown_or_inactive_business_returns_404(): void
    {
        $this->get('/chat/nope')->assertNotFound();

        $this->business->update(['status' => 'paused']);
        $this->get('/chat/demo')->assertNotFound();
    }

    public function test_first_message_starts_a_conversation_and_gets_a_bot_reply(): void
    {
        $response = $this->send('Hello');

        $response->assertOk()->assertJsonStructure(['token', 'mode', 'messages']);
        $this->assertSame('bot', $response->json('mode'));
        $this->assertCount(2, $response->json('messages'));
        $this->assertSame('customer', $response->json('messages.0.from'));
        $this->assertSame('bot', $response->json('messages.1.from'));

        app(CurrentBusiness::class)->runAs($this->business, function () {
            $this->assertSame(1, Conversation::count());
            $this->assertSame(2, Message::count());
        });
    }

    public function test_second_message_reuses_the_same_conversation(): void
    {
        $token = $this->send('Hello')->json('token');
        $this->send('Prices please', $token)->assertOk();

        app(CurrentBusiness::class)->runAs($this->business, function () {
            $this->assertSame(1, Conversation::count());
            $this->assertSame(5, Message::count()); // 2 + customer + sorry + menu again
        });
    }

    public function test_empty_and_too_long_messages_are_rejected(): void
    {
        $this->send('')->assertStatus(422);
        $this->send(str_repeat('a', 1001))->assertStatus(422);
    }

    public function test_poll_returns_only_new_public_messages(): void
    {
        $token = $this->send('Hello')->json('token');

        $conversation = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::where('token', $token)->first());
        $staffMessage = $conversation->addMessage('out', 'staff', 'Hi from staff');
        $conversation->addMessage('out', 'staff', 'internal only', null, true);

        $all = $this->getJson(route('chat.poll', 'demo').'?token='.$token.'&after=0')->assertOk()->json('messages');
        $texts = array_column($all, 'text');
        $this->assertContains('Hi from staff', $texts);
        $this->assertNotContains('internal only', $texts);

        $after = $this->getJson(route('chat.poll', 'demo').'?token='.$token.'&after='.$staffMessage->id)->json('messages');
        $this->assertSame([], $after);
    }

    public function test_poll_with_an_unknown_token_returns_404(): void
    {
        $this->getJson(route('chat.poll', 'demo').'?token=missing')->assertNotFound();
    }

    public function test_the_bot_stays_silent_when_staff_handle_the_chat(): void
    {
        $token = $this->send('Hello')->json('token');

        $conversation = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::where('token', $token)->first());
        $conversation->handOverToStaff('Test');

        $response = $this->send('Anyone there?', $token);

        $this->assertSame('human', $response->json('mode'));
        $botMessages = collect($response->json('messages'))->where('from', 'bot');
        $this->assertCount(0, $botMessages, 'No new bot reply after handover.');
    }
}
