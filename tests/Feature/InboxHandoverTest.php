<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxHandoverTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'demo']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id]);
    }

    public function test_a_staff_reply_takes_over_the_chat_and_reaches_the_visitor(): void
    {
        $token = $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])->json('token');
        $conversation = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::where('token', $token)->first());

        $this->actingAs($this->staff)
            ->postJson('/admin/api/conversations/'.$conversation->id.'/reply', ['text' => 'Hi, this is Amy'])
            ->assertOk()
            ->assertJsonPath('mode', 'human');

        $visitorView = $this->getJson('/api/chat/demo/messages?token='.$token.'&after=0')->assertOk();
        $this->assertContains('Hi, this is Amy', array_column($visitorView->json('messages'), 'text'));
        $this->assertSame('human', $visitorView->json('mode'));
    }

    public function test_a_waiting_customer_can_type_menu_to_get_the_bot_back(): void
    {
        $token = $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])->json('token');
        $conversation = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::where('token', $token)->first());
        $this->actingAs($this->staff)->postJson('/admin/api/conversations/'.$conversation->id.'/reply', ['text' => 'One moment'])->assertJsonPath('mode', 'human');

        // While staff own the chat the bot is silent.
        $silent = $this->postJson('/api/chat/demo/messages', ['text' => 'hello?', 'token' => $token])->assertOk();
        $this->assertSame('human', $silent->json('mode'));

        // "menu" (or the button) hands it back and shows the main menu again.
        $back = $this->postJson('/api/chat/demo/messages', ['text' => 'menu', 'option_id' => '__menu', 'token' => $token])->assertOk();
        $this->assertSame('bot', $back->json('mode'));
        $this->assertNotEmpty(array_filter($back->json('messages'), fn ($m) => $m['from'] === 'bot'));
    }

    public function test_giving_the_chat_back_makes_the_bot_answer_again(): void
    {
        $token = $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])->json('token');
        $conversation = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::where('token', $token)->first());

        $this->actingAs($this->staff);
        $this->postJson('/admin/api/conversations/'.$conversation->id.'/reply', ['text' => 'One moment']);
        $this->postJson('/admin/api/conversations/'.$conversation->id.'/give-back')->assertOk()->assertJsonPath('mode', 'bot');

        $reply = $this->postJson('/api/chat/demo/messages', ['text' => 'Still there?', 'token' => $token]);

        $this->assertSame('bot', $reply->json('mode'));
        $this->assertSame('bot', collect($reply->json('messages'))->last()['from']);
    }

    public function test_private_notes_are_hidden_from_the_visitor_but_visible_to_staff(): void
    {
        $token = $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])->json('token');
        $conversation = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::where('token', $token)->first());

        $this->actingAs($this->staff)->postJson('/admin/api/conversations/'.$conversation->id.'/note', ['text' => 'Call her back'])->assertOk();

        $visitor = $this->getJson('/api/chat/demo/messages?token='.$token.'&after=0')->json('messages');
        $this->assertNotContains('Call her back', array_column($visitor, 'text'));

        $staffView = $this->getJson('/admin/api/conversations/'.$conversation->id)->json('messages');
        $this->assertContains('Call her back', array_column($staffView, 'text'));
    }

    public function test_resolving_hides_a_chat_from_the_open_list(): void
    {
        $conversation = $this->conversationIn($this->business, 'Hello');

        $this->actingAs($this->staff);
        $this->postJson('/admin/api/conversations/'.$conversation->id.'/resolve')->assertOk();

        $this->assertCount(0, $this->getJson('/admin/api/conversations?status=open')->json('conversations'));
        $this->assertCount(1, $this->getJson('/admin/api/conversations?status=resolved')->json('conversations'));
        $this->assertCount(1, $this->getJson('/admin/api/conversations?status=all')->json('conversations'));
    }

    public function test_replies_must_not_be_empty(): void
    {
        $conversation = $this->conversationIn($this->business, 'Hello');

        $this->actingAs($this->staff)
            ->postJson('/admin/api/conversations/'.$conversation->id.'/reply', ['text' => ''])
            ->assertStatus(422);

        app(CurrentBusiness::class)->runAs($this->business, function () use ($conversation) {
            $this->assertSame(1, Message::where('conversation_id', $conversation->id)->count());
        });
    }
}
