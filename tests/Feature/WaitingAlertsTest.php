<?php

namespace Tests\Feature;

use App\Mail\WaitingChatMail;
use App\Models\Business;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\StaffNotification;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WaitingAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Conversation $conversation;

    private User $owner;

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    private function settings(array $s): void
    {
        $this->business->update(['settings' => array_merge((array) $this->business->settings, $s)]);
        $this->business->refresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 03:00:00');
        $this->business = Business::factory()->create(['slug' => 'demo', 'settings' => []]);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->conversation = $this->in(function () {
            $c = Conversation::startFor('web');
            $c->handOverToStaff('Customer asked for a person');

            return $c;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function runAt(string $time): void
    {
        Carbon::setTestNow($time);
        $this->artisan('aichat:check-handovers')->assertSuccessful();
    }

    public function test_emails_go_to_owners_at_10_and_30_minutes_only_when_switched_on(): void
    {
        Mail::fake();
        $this->runAt('2026-11-03 03:10:00');
        Mail::assertNothingSent();

        $this->settings(['alert_email' => true]);
        $this->runAt('2026-11-03 03:10:00');   // one step per run: the 10 minute step comes now
        Mail::assertSent(WaitingChatMail::class, fn ($m) => $m->hasTo($this->owner->email) && ! $m->urgent);

        $this->runAt('2026-11-03 03:20:00');
        $this->runAt('2026-11-03 03:30:00');
        Mail::assertSent(WaitingChatMail::class, fn ($m) => $m->urgent && $m->minutes === 30);
    }

    public function test_whatsapp_alert_uses_the_template_when_there_is_one_and_never_breaks_the_check(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);
        $channel = $this->in(function () {
            $c = new Channel(['type' => 'whatsapp', 'name' => 'WA', 'external_id' => '555', 'active' => true]);
            $c->access_token = 'tok';
            $c->save();
            MessageTemplate::create(['channel_id' => $c->id, 'name' => 'staff_alert', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED', 'body' => 'Hi, {{1}} waited {{2}} minutes']);

            return $c;
        });
        $this->settings(['alert_whatsapp' => '60123456789', 'alert_whatsapp_template' => 'staff_alert']);

        $this->runAt('2026-11-03 03:10:00');   // the 5 minute step
        $this->runAt('2026-11-03 03:10:00');   // the 10 minute step (one step per run, the scheduler runs every minute)

        Http::assertSent(fn ($r) => str_contains($r->url(), '555/messages') && $r['to'] === '60123456789' && $r['type'] === 'template'
            && $r['template']['name'] === 'staff_alert' && $r['template']['components'][0]['parameters'][1]['text'] === '10');

        // A broken Meta call must not stop the chat check.
        Http::fake(['*' => Http::response(['error' => ['message' => 'bad token']], 400)]);
        $this->runAt('2026-11-03 03:30:00');
        $this->assertTrue(true);
    }

    public function test_the_bot_takes_the_chat_back_after_the_chosen_time_and_staff_are_told(): void
    {
        Http::fake();
        $this->settings(['handover_return_minutes' => 30]);
        $this->in(fn () => $this->conversation->forceFill(['language' => 'en'])->save());

        $this->runAt('2026-11-03 03:20:00');
        $this->assertSame('human', $this->in(fn () => Conversation::find($this->conversation->id)->mode));

        $this->runAt('2026-11-03 03:30:00');
        $this->in(function () {
            $c = Conversation::find($this->conversation->id);
            $this->assertSame('bot', $c->mode);
            $bot = Message::query()->where('conversation_id', $c->id)->where('sender_type', 'bot')->pluck('body')->implode('|');
            $this->assertStringContainsString('back to help', $bot);
            $this->assertNotEmpty(Message::query()->where('conversation_id', $c->id)->where('sender_type', 'bot')->get()->filter(fn ($m) => ! empty($m->payload['options'] ?? []))->all(), 'the main menu is shown again');
        });
        $this->assertTrue(StaffNotification::query()->where('user_id', $this->owner->id)->where('title', 'like', '%went back to the bot%')->exists());
    }

    public function test_it_never_takes_back_a_chat_staff_have_answered_and_is_off_by_default(): void
    {
        Http::fake();
        $this->runAt('2026-11-03 06:00:00');   // 3 hours, setting off
        $this->assertSame('human', $this->in(fn () => Conversation::find($this->conversation->id)->mode));

        $this->settings(['handover_return_minutes' => 15]);
        $this->in(fn () => $this->conversation->addMessage('out', 'staff', 'Coming!'));
        $this->runAt('2026-11-03 06:30:00');
        $this->assertSame('human', $this->in(fn () => Conversation::find($this->conversation->id)->mode));
    }

    public function test_settings_save_validate_and_the_test_alert_sends_an_email(): void
    {
        Mail::fake();
        $this->actingAs($this->owner)->get('/admin/settings')->assertOk()->assertSee('When a customer waits for a person')->assertSee('{{1}}', false);

        $this->post('/admin/settings/alerts', ['return_minutes' => 7])->assertSessionHasErrors('return_minutes');
        $this->post('/admin/settings/alerts', ['return_minutes' => 60, 'alert_email' => 1, 'alert_whatsapp' => '+60 12-345 6789', 'alert_whatsapp_template' => 'staff_alert'])->assertSessionHasNoErrors();
        $s = $this->business->fresh()->settings;
        $this->assertSame(60, $s['handover_return_minutes']);
        $this->assertTrue($s['alert_email']);
        $this->assertSame('60123456789', $s['alert_whatsapp']);

        $this->post('/admin/settings/alerts', ['return_minutes' => 0, 'alert_whatsapp' => 'abc'])->assertSessionHasErrors('alert_whatsapp');

        $this->post('/admin/settings/alerts/test')->assertSessionHas('status', fn ($m) => str_contains($m, 'An email was sent'));
        Mail::assertSent(WaitingChatMail::class, fn ($m) => $m->hasTo($this->owner->email) && $m->name === 'Test customer');
    }
}
