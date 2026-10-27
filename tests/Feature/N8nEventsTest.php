<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class N8nEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Business::factory()->create(['slug' => 'demo']);
    }

    public function test_a_new_conversation_is_sent_to_n8n_when_configured(): void
    {
        config(['services.n8n.webhook_url' => 'http://localhost:5678/webhook/aichat', 'services.n8n.secret' => 's3cret']);
        Http::fake();

        $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])->assertOk();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://localhost:5678/webhook/aichat'
                && $request->hasHeader('X-Aichat-Secret', 's3cret')
                && $request['event'] === 'conversation.started'
                && $request['data']['business'] === 'demo';
        });
        Http::assertSentCount(1);
    }

    public function test_a_second_message_does_not_send_another_event(): void
    {
        config(['services.n8n.webhook_url' => 'http://localhost:5678/webhook/aichat']);
        Http::fake();

        $token = $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])->json('token');
        $this->postJson('/api/chat/demo/messages', ['text' => 'Again', 'token' => $token])->assertOk();

        Http::assertSentCount(1);
    }

    public function test_nothing_is_sent_when_n8n_is_not_configured(): void
    {
        config(['services.n8n.webhook_url' => null]);
        Http::fake();

        $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_failing_n8n_never_breaks_the_chat(): void
    {
        config(['services.n8n.webhook_url' => 'http://localhost:5678/webhook/aichat']);
        Http::fake(fn () => throw new ConnectionException('n8n is not running'));

        $this->postJson('/api/chat/demo/messages', ['text' => 'Hello'])
            ->assertOk()
            ->assertJsonCount(2, 'messages');
    }
}
