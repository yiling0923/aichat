<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HandoverTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.webhook_url' => 'http://n8n.test/hook']);
        $this->business = Business::factory()->create(['slug' => 'demo']);
        Carbon::setTestNow('2026-11-03 03:00:00');

        $this->conversation = app(CurrentBusiness::class)->runAs($this->business, function () {
            $c = Conversation::startFor('web');
            $c->handOverToStaff('test');

            return $c;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function events(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]->data()['event'])->values()->all();
    }

    public function test_escalation_follows_the_timeouts(): void
    {
        Http::fake();

        Carbon::setTestNow('2026-11-03 03:04:00');
        $this->artisan('aichat:check-handovers')->assertSuccessful();
        $this->assertSame([], $this->events());

        Carbon::setTestNow('2026-11-03 03:05:00');
        $this->artisan('aichat:check-handovers');
        $this->assertSame(['handover.holding'], $this->events());

        app(CurrentBusiness::class)->runAs($this->business, function () {
            $this->assertSame(1, $this->conversation->messages()->where('sender_type', 'bot')->count());
        });

        // Running again at the same time must not repeat the step.
        $this->artisan('aichat:check-handovers');
        $this->assertCount(1, $this->events());

        Carbon::setTestNow('2026-11-03 03:10:00');
        $this->artisan('aichat:check-handovers');
        Carbon::setTestNow('2026-11-03 03:20:00');
        $this->artisan('aichat:check-handovers');
        Carbon::setTestNow('2026-11-03 03:30:00');
        $this->artisan('aichat:check-handovers');

        $this->assertSame(['handover.holding', 'handover.reminder', 'handover.reassign', 'handover.owner_alert'], $this->events());
    }

    public function test_no_escalation_once_staff_have_replied(): void
    {
        Http::fake();
        app(CurrentBusiness::class)->runAs($this->business, function () {
            $this->conversation->addMessage('out', 'staff', 'On it!');
        });

        Carbon::setTestNow('2026-11-03 03:30:00');
        $this->artisan('aichat:check-handovers');

        $this->assertSame([], $this->events());
    }

    public function test_chats_given_back_to_the_bot_are_ignored(): void
    {
        Http::fake();
        app(CurrentBusiness::class)->runAs($this->business, fn () => $this->conversation->giveBackToBot());

        Carbon::setTestNow('2026-11-03 03:30:00');
        $this->artisan('aichat:check-handovers');

        $this->assertSame([], $this->events());
    }
}
