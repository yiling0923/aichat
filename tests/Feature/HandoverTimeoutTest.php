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

    public function test_staff_get_a_fresh_bell_alert_at_every_step_and_the_owner_an_urgent_one(): void
    {
        Http::fake();
        $inBiz = fn (callable $f) => app(CurrentBusiness::class)->runAs($this->business, $f);
        $staff = \App\Models\User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $owner = \App\Models\User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $titles = fn ($u) => \App\Models\StaffNotification::query()->where('user_id', $u->id)->orderBy('id')->pluck('title')->all();

        Carbon::setTestNow('2026-11-03 03:05:00');
        $this->artisan('aichat:check-handovers');
        $this->assertStringContainsString('has waited 5 minutes', implode('|', $titles($staff)));

        Carbon::setTestNow('2026-11-03 03:10:00');
        $this->artisan('aichat:check-handovers');
        $this->assertStringContainsString('has waited 10 minutes', implode('|', $titles($staff)));   // the same unread alert is refreshed, not duplicated
        $this->assertCount(1, $titles($staff));

        // 20 minutes: the customer also gets an honest second message with the way back to the menu.
        Carbon::setTestNow('2026-11-03 03:20:00');
        $this->artisan('aichat:check-handovers');
        $inBiz(function () {
            $texts = $this->conversation->messages()->where('sender_type', 'bot')->pluck('body')->implode('|');
            $this->assertStringContainsString('still busy', $texts);
            $this->assertStringContainsString('menu', $texts);
        });

        // 30 minutes: urgent, and only owners and managers get the new wording.
        Carbon::setTestNow('2026-11-03 03:30:00');
        $this->artisan('aichat:check-handovers');
        $this->assertStringContainsString('URGENT', implode('|', $titles($owner)));
        $this->assertStringNotContainsString('URGENT', implode('|', $titles($staff)));
    }

    public function test_no_alerts_once_staff_have_replied(): void
    {
        Http::fake();
        $staff = \App\Models\User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        app(CurrentBusiness::class)->runAs($this->business, fn () => $this->conversation->addMessage('out', 'staff', 'On it!'));

        Carbon::setTestNow('2026-11-03 03:10:00');
        $this->artisan('aichat:check-handovers');
        $this->assertSame(0, \App\Models\StaffNotification::query()->where('user_id', $staff->id)->count());
    }

    public function test_a_long_wait_is_written_in_days_and_hours_not_thousands_of_minutes(): void
    {
        Http::fake();
        $this->business->forceFill(['settings' => ['handover_return_minutes' => 60]])->save();
        $staff = \App\Models\User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);

        // The scheduler was off for almost four days, then woke up.
        Carbon::setTestNow(Carbon::parse('2026-11-03 03:00:00')->addMinutes(5655));
        $this->artisan('aichat:check-handovers');

        app(CurrentBusiness::class)->runAs($this->business, function () {
            $notes = $this->conversation->messages()->where('sender_type', 'system')->pluck('body')->implode('|');
            $this->assertStringContainsString('Nobody answered for 3 days 22 hours, so the chat went back to the bot.', $notes);
            $this->assertStringNotContainsString('5655', $notes);
        });
        $bodies = \App\Models\StaffNotification::query()->where('user_id', $staff->id)->pluck('body')->implode('|');
        $this->assertStringContainsString('Nobody answered for 3 days 22 hours.', $bodies);

        // In Chinese the same alert reads in days and hours too.
        $this->actingAs($staff)->post('/admin/language', ['locale' => 'zh']);
        $items = $this->actingAs($staff->fresh())->getJson('/admin/api/notifications')->json('items');
        $this->assertStringContainsString('3 天 22 小时', implode('|', array_column($items, 'body')));
    }
}
