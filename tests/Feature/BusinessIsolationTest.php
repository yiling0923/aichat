<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * One business must never see or change another business's data.
 */
class BusinessIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Business $a;

    private Business $b;

    private User $staffA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Business::factory()->create(['slug' => 'biz-a']);
        $this->b = Business::factory()->create(['slug' => 'biz-b']);
        $this->staffA = User::factory()->create(['business_id' => $this->a->id, 'role' => 'staff']);
    }

    public function test_staff_list_only_shows_their_own_businesss_conversations(): void
    {
        $this->conversationIn($this->a, 'Hello from A');
        $this->conversationIn($this->b, 'Hello from B');

        $response = $this->actingAs($this->staffA)->getJson('/admin/api/conversations')->assertOk();

        $this->assertCount(1, $response->json('conversations'));
        $this->assertSame('Hello from A', $response->json('conversations.0.preview'));
    }

    public function test_staff_cannot_open_or_change_another_businesss_conversation(): void
    {
        $other = $this->conversationIn($this->b, 'Secret B');

        $this->actingAs($this->staffA);
        $this->getJson('/admin/api/conversations/'.$other->id)->assertNotFound();
        $this->postJson('/admin/api/conversations/'.$other->id.'/reply', ['text' => 'hi'])->assertNotFound();
        $this->postJson('/admin/api/conversations/'.$other->id.'/note', ['text' => 'hi'])->assertNotFound();
        $this->postJson('/admin/api/conversations/'.$other->id.'/give-back')->assertNotFound();
        $this->postJson('/admin/api/conversations/'.$other->id.'/resolve')->assertNotFound();

        app(CurrentBusiness::class)->runAs($this->b, function () use ($other) {
            $fresh = Conversation::find($other->id);
            $this->assertSame('bot', $fresh->mode);
            $this->assertSame('open', $fresh->status);
            $this->assertSame(1, Message::count());
        });
    }

    public function test_a_visitor_token_from_one_business_does_not_work_in_another(): void
    {
        $conversationB = $this->conversationIn($this->b, 'Hello B');

        $this->getJson('/api/chat/biz-a/messages?token='.$conversationB->token)->assertNotFound();

        $this->postJson('/api/chat/biz-a/messages', ['text' => 'Hi', 'token' => $conversationB->token])->assertOk();

        app(CurrentBusiness::class)->runAs($this->b, function () use ($conversationB) {
            $this->assertSame(1, Message::where('conversation_id', $conversationB->id)->count(), 'B conversation is untouched.');
        });
        app(CurrentBusiness::class)->runAs($this->a, function () {
            $this->assertSame(1, Conversation::count(), 'A new conversation was started in A instead.');
        });
    }

    public function test_queries_return_nothing_when_no_business_is_set(): void
    {
        $this->conversationIn($this->a, 'Hello');

        $this->assertNull(app(CurrentBusiness::class)->get());
        $this->assertSame(0, Conversation::count());
        $this->assertSame(0, Message::count());
    }

    public function test_creating_business_data_without_a_business_fails(): void
    {
        $this->expectException(LogicException::class);

        Conversation::startFor('web');
    }

    public function test_a_user_without_a_business_cannot_use_the_inbox(): void
    {
        $platformOwner = User::factory()->create(['business_id' => null, 'role' => 'platform_owner']);

        $this->actingAs($platformOwner)->getJson('/admin/api/conversations')->assertForbidden();
    }

    public function test_a_paused_business_blocks_its_staff(): void
    {
        $this->a->update(['status' => 'paused']);

        $this->actingAs($this->staffA)->getJson('/admin/api/conversations')->assertForbidden();
    }
}
