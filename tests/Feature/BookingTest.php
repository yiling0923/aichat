<?php

namespace Tests\Feature;

use App\Booking\Availability;
use App\Booking\Bookings;
use App\Booking\SlotTakenException;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Service $facial;

    /** @var list<Practitioner> */
    private array $people = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 2 Nov 2026, 08:00 in Kuala Lumpur (00:00 UTC).
        Carbon::setTestNow('2026-11-02 00:00:00');
        $this->business = $this->makeBusiness('demo');
        $this->facial = $this->inBusiness(fn () => Service::query()->first());
        $this->people = $this->inBusiness(fn () => Practitioner::query()->orderBy('id')->get()->all());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeBusiness(string $slug, int $practitioners = 2): Business
    {
        $b = Business::factory()->create([
            'slug' => $slug,
            'timezone' => 'Asia/Kuala_Lumpur',
            'settings' => ['hours' => ['mon' => ['10:00', '19:00'], 'tue' => ['10:00', '19:00'], 'wed' => ['10:00', '19:00'], 'thu' => ['10:00', '19:00'], 'fri' => ['10:00', '19:00']]],
        ]);
        app(CurrentBusiness::class)->runAs($b, function () use ($practitioners) {
            $people = [];
            for ($i = 1; $i <= $practitioners; $i++) {
                $people[] = Practitioner::create(['name' => 'Person '.$i]);
            }
            $s = Service::create(['name' => ['en' => 'Facial', 'zh' => '护理'], 'duration_min' => 60, 'price' => 188]);
            $s->practitioners()->sync(array_map(fn ($p) => $p->id, $people));
        });

        return $b;
    }

    private function inBusiness(callable $fn, ?Business $business = null)
    {
        return app(CurrentBusiness::class)->runAs($business ?? $this->business, $fn);
    }

    private function say(string $slug, ?string $text, ?string $token = null, ?string $option = null)
    {
        return $this->postJson(route('chat.send', $slug), ['text' => $text, 'option_id' => $option, 'token' => $token]);
    }

    /** @return array<string, mixed> the last bot message of a response */
    private function lastBot($response): array
    {
        $bots = array_values(array_filter($response->json('messages'), fn ($m) => $m['from'] === 'bot'));

        return end($bots);
    }

    /** Walk the chat to the day picker. @return array{0: string, 1: mixed} */
    private function toDays(string $slug = 'demo', string $lang = 'lang_en'): array
    {
        $token = $this->say($slug, 'hi')->json('token');
        $this->say($slug, 'x', $token, $lang);
        $r = $this->say($slug, 'x', $token, 'book');
        $service = $this->say($slug, 'x', $token, $this->lastBot($r)['options'][0]['id']);

        return [$token, $service];
    }

    private function optionIds($response): array
    {
        return array_column($this->lastBot($response)['options'] ?? [], 'id');
    }

    /** Book a slot through the chat. */
    private function bookViaChat(string $slug, string $day, string $time, string $name = 'Amy Tan', string $phone = '012-345 6789'): array
    {
        [$token] = $this->toDays($slug);
        $r = $this->say($slug, 'x', $token, 'day_'.$day);
        $r = $this->say($slug, 'x', $token, 'slot_'.$time);
        $r = $this->say($slug, $name, $token);
        $r = $this->say($slug, $phone, $token);

        return [$token, $r];
    }

    public function test_customer_books_by_taps_and_a_lead_is_saved(): void
    {
        Http::fake();
        config(['services.n8n.webhook_url' => 'http://n8n.test/hook']);

        [$token, $r] = $this->bookViaChat('demo', '2026-11-03', '10:00');

        $this->assertStringContainsString('You are booked, Amy Tan', implode(' ', array_column($r->json('messages'), 'text')));

        $this->inBusiness(function () {
            $b = Booking::query()->with(['customer', 'service'])->first();
            $this->assertSame('confirmed', $b->status);
            $this->assertSame('Facial', $b->service->label('en'));
            // 10:00 Kuala Lumpur is 02:00 UTC.
            $this->assertSame('2026-11-03 02:00:00', $b->starts_at->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-03 03:00:00', $b->ends_at->format('Y-m-d H:i:s'));
            $this->assertSame('Amy Tan', $b->customer->name);
            $this->assertSame('0123456789', $b->customer->phone);
            $this->assertSame('hot', $b->customer->score);
            $this->assertSame('Facial', $b->customer->interest);
        });

        $events = Http::recorded()->map(fn ($p) => $p[0]->data()['event'])->all();
        $this->assertContains('booking.created', $events);
    }

    public function test_chinese_booking_flow(): void
    {
        [$token, $service] = $this->toDays('demo', 'lang_zh');
        $this->assertStringContainsString('哪一天方便', $this->lastBot($service)['text']);
        $this->assertStringContainsString('周二', $this->lastBot($service)['options'][1]['label'] ?? '周二');
        $r = $this->say('demo', 'x', $token, 'day_2026-11-03');
        $this->assertStringContainsString('几点方便', $this->lastBot($r)['text']);
    }

    public function test_the_second_booking_for_the_same_slot_goes_to_another_practitioner_then_the_slot_is_gone(): void
    {
        $this->bookViaChat('demo', '2026-11-03', '10:00', 'Amy', '0123456789');
        $this->bookViaChat('demo', '2026-11-03', '10:00', 'Bob', '0198765432');

        $this->inBusiness(function () {
            $this->assertSame(2, Booking::query()->distinct('practitioner_id')->count('practitioner_id'));
            $times = array_column(app(Availability::class)->slots($this->business, $this->facial, '2026-11-03'), 'time');
            $this->assertNotContains('10:00', $times);
            $this->assertContains('12:00', $times);
        });
    }

    public function test_booking_the_same_practitioner_slot_twice_fails(): void
    {
        $this->inBusiness(function () {
            $conversation = Conversation::startFor('web');
            $start = Carbon::parse('2026-11-03 10:00', 'Asia/Kuala_Lumpur');
            $bookings = app(Bookings::class);

            $bookings->book($conversation, $this->facial, $this->people[0], $start, ['name' => 'A', 'phone' => '0123456789']);

            $this->expectException(SlotTakenException::class);
            $bookings->book(Conversation::startFor('web'), $this->facial, $this->people[0], $start, ['name' => 'B', 'phone' => '0198765432']);
        });
    }

    public function test_a_slot_taken_while_the_customer_was_choosing_is_refused_politely(): void
    {
        [$token] = $this->toDays();
        $this->say('demo', 'x', $token, 'day_2026-11-03');
        $this->say('demo', 'x', $token, 'slot_10:00');
        $this->say('demo', 'Amy', $token);

        // Both practitioners get booked at 10:00 by someone else before Amy finishes.
        $this->inBusiness(function () {
            foreach ($this->people as $p) {
                app(Bookings::class)->book(Conversation::startFor('web'), $this->facial, $p, Carbon::parse('2026-11-03 10:00', 'Asia/Kuala_Lumpur'), ['name' => 'X', 'phone' => '0123456789']);
            }
        });

        $r = $this->say('demo', '0123456789', $token);
        $texts = array_column($r->json('messages'), 'text');
        $this->assertStringContainsString('just taken', implode(' ', $texts));
        $this->assertNotContains('10:00', $this->optionLabels($r));
        $this->inBusiness(fn () => $this->assertSame(2, Booking::query()->count()));
    }

    private function optionLabels($response): array
    {
        return array_column($this->lastBot($response)['options'] ?? [], 'label');
    }

    public function test_buffer_blocks_the_slot_right_after_an_appointment(): void
    {
        $b = $this->makeBusiness('solo', 1);
        $service = $this->inBusiness(fn () => Service::query()->first(), $b);
        $person = $this->inBusiness(fn () => Practitioner::query()->first(), $b);

        $this->inBusiness(function () use ($service, $person, $b) {
            app(Bookings::class)->book(Conversation::startFor('web'), $service, $person, Carbon::parse('2026-11-03 10:00', 'Asia/Kuala_Lumpur'), ['name' => 'A', 'phone' => '0123456789']);
            $times = array_column(app(Availability::class)->slots($b, $service, '2026-11-03'), 'time');
            // Appointment ends 11:00, the 10 minute gap runs to 11:10, so 11:00 is out and 11:30 is next.
            $this->assertNotContains('10:00', $times);
            $this->assertNotContains('10:30', $times);
            $this->assertNotContains('11:00', $times);
            $this->assertContains('11:30', $times);
        }, $b);
    }

    public function test_closed_days_dates_and_past_times_are_not_offered(): void
    {
        $this->inBusiness(function () {
            $av = app(Availability::class);
            $this->assertSame([], $av->slots($this->business, $this->facial, '2026-11-07')); // Saturday, no hours
            $days = $av->days($this->business, $this->facial);
            $this->assertNotContains('2026-11-07', $days);
            $this->assertNotContains('2026-11-08', $days);
            $this->assertContains('2026-11-09', $days);

            $this->business->update(['settings' => array_merge($this->business->settings, ['closed_dates' => ['2026-11-03']])]);
            $this->assertSame([], $av->slots($this->business->fresh(), $this->facial, '2026-11-03'));

            // Monday 08:00 local, 2 hour notice: first slot today is 10:00.
            $today = array_column($av->slots($this->business->fresh(), $this->facial, '2026-11-02'), 'time');
            $this->assertSame('10:00', $today[0]);
        });
    }

    public function test_services_without_active_practitioners_are_not_listed(): void
    {
        $this->inBusiness(fn () => Practitioner::query()->update(['active' => false]));

        $token = $this->say('demo', 'hi')->json('token');
        $this->say('demo', 'x', $token, 'lang_en');
        $r = $this->say('demo', 'x', $token, 'book');

        $this->assertSame('human', $r->json('mode'));
    }

    public function test_businesses_do_not_share_bookings_or_availability(): void
    {
        $other = $this->makeBusiness('other', 1);
        $this->bookViaChat('demo', '2026-11-03', '10:00');
        $this->bookViaChat('demo', '2026-11-03', '10:00', 'Bob', '0198765432');

        $this->inBusiness(function () use ($other) {
            $service = Service::query()->first();
            $this->assertContains('10:00', array_column(app(Availability::class)->slots($other, $service, '2026-11-03'), 'time'));
            $this->assertSame(0, Booking::query()->count());
        }, $other);
    }

    private function googleCredentials(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $path = sys_get_temp_dir().'/aichat-test-google-'.uniqid().'.json';
        file_put_contents($path, json_encode(['client_email' => 'bot@test.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.google_calendar.credentials' => $path]);
        Cache::forget('google-calendar-token');
    }

    private function fakeGoogle(array $busy = []): void
    {
        $this->googleCredentials();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/calendar/v3/freeBusy' => fn ($request) => Http::response([
                'calendars' => collect($request['items'])->mapWithKeys(fn ($i) => [$i['id'] => ['busy' => $busy[$i['id']] ?? []]])->all(),
            ]),
            'www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'evt123']),
            'www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response('', 204),
        ]);
    }

    public function test_google_busy_times_are_blocked_and_events_are_created_and_removed(): void
    {
        $this->inBusiness(function () {
            foreach ($this->people as $i => $p) {
                $p->update(['google_calendar_id' => 'cal'.$i.'@group.calendar.google.com']);
            }
        });
        // Both calendars are busy 10:00 to 12:00 Kuala Lumpur on Tuesday (02:00-04:00 UTC).
        $busy = [['start' => '2026-11-03T02:00:00Z', 'end' => '2026-11-03T04:00:00Z']];
        $this->fakeGoogle(['cal0@group.calendar.google.com' => $busy, 'cal1@group.calendar.google.com' => $busy]);

        $times = $this->inBusiness(fn () => array_column(app(Availability::class)->slots($this->business, $this->facial, '2026-11-03'), 'time'));
        $this->assertNotContains('10:00', $times);
        $this->assertNotContains('11:30', $times);
        $this->assertContains('12:30', $times);

        $this->bookViaChat('demo', '2026-11-03', '12:30');

        $this->inBusiness(function () {
            $b = Booking::query()->first();
            $this->assertSame('evt123', $b->google_event_id);
            app(Bookings::class)->cancel($b, 'staff');
            $this->assertSame('cancelled', $b->fresh()->status);
        });

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/events') && $r['summary'] === 'Facial - Amy Tan');
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/evt123'));
    }

    public function test_when_google_cannot_be_read_the_bot_hands_over(): void
    {
        $this->inBusiness(fn () => $this->people[0]->update(['google_calendar_id' => 'cal0@group.calendar.google.com']));
        $this->googleCredentials();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/*' => Http::response('boom', 500),
        ]);

        $token = $this->say('demo', 'hi')->json('token');
        $this->say('demo', 'x', $token, 'lang_en');
        $r = $this->say('demo', 'x', $token, 'book');
        $r = $this->say('demo', 'x', $token, $this->lastBot($r)['options'][0]['id']);

        $this->assertSame('human', $r->json('mode'));
        $this->assertStringContainsString('cannot check the calendar', implode(' ', array_column($r->json('messages'), 'text')));
        $this->inBusiness(fn () => $this->assertSame(0, Booking::query()->count()));
    }

    public function test_customer_can_view_and_cancel_a_booking_outside_the_cancellation_window(): void
    {
        [$token] = $this->bookViaChat('demo', '2026-11-05', '10:00');

        $r = $this->say('demo', 'x', $token, 'my_booking');
        $this->assertStringContainsString('Your booking', implode(' ', array_column($r->json('messages'), 'text')));
        $this->assertContains('bk_cancel', $this->optionIds($r));

        $r = $this->say('demo', 'x', $token, 'bk_cancel');
        $this->assertStringContainsString('cancelled', implode(' ', array_column($r->json('messages'), 'text')));
        $this->inBusiness(fn () => $this->assertSame('cancelled', Booking::query()->first()->status));
    }

    public function test_cancelling_inside_the_window_goes_to_staff(): void
    {
        // Tomorrow 10:00 is about 26 hours away: allowed. Today 10:00 is 2 hours away: needs staff.
        [$token] = $this->bookViaChat('demo', '2026-11-02', '10:00');

        $this->say('demo', 'x', $token, 'my_booking');
        $r = $this->say('demo', 'x', $token, 'bk_cancel');

        $this->assertSame('human', $r->json('mode'));
        $this->inBusiness(fn () => $this->assertSame('confirmed', Booking::query()->first()->status));
    }

    public function test_my_booking_with_nothing_booked(): void
    {
        $token = $this->say('demo', 'hi')->json('token');
        $this->say('demo', 'x', $token, 'lang_en');
        $r = $this->say('demo', 'x', $token, 'my_booking');

        $this->assertStringContainsString('no upcoming booking', implode(' ', array_column($r->json('messages'), 'text')));
        $this->assertSame('bot', $r->json('mode'));
    }

    public function test_reminders_are_sent_once_per_step_and_skipped_for_late_bookings(): void
    {
        Http::fake();
        config(['services.n8n.webhook_url' => 'http://n8n.test/hook']);

        // Booked on Monday for Wednesday 10:00 KL (Wed 02:00 UTC).
        [$token] = $this->bookViaChat('demo', '2026-11-04', '10:00');

        Carbon::setTestNow('2026-11-03 01:30:00'); // 24h30 before: nothing
        $this->artisan('aichat:send-reminders')->assertSuccessful();
        $this->assertSame([], $this->reminderEvents());

        Carbon::setTestNow('2026-11-03 02:00:00'); // exactly 24h before
        $this->artisan('aichat:send-reminders');
        $this->artisan('aichat:send-reminders'); // must not repeat
        $this->assertSame([24], $this->reminderEvents());

        Carbon::setTestNow('2026-11-04 00:00:00'); // 2h before
        $this->artisan('aichat:send-reminders');
        $this->assertSame([24, 2], $this->reminderEvents());

        $texts = $this->inBusiness(fn () => Conversation::query()->where('token', $token)->first()->messages()->pluck('body')->implode(' | '));
        $this->assertSame(2, substr_count($texts, 'Reminder: your Facial appointment'));
    }

    public function test_a_booking_made_inside_the_reminder_window_gets_no_24h_reminder(): void
    {
        Http::fake();
        config(['services.n8n.webhook_url' => 'http://n8n.test/hook']);

        // Booked at 00:00 UTC for 10:00 KL (02:00 UTC) the same day: only 2 hours ahead.
        $this->bookViaChat('demo', '2026-11-02', '10:00');

        Carbon::setTestNow('2026-11-02 00:30:00');
        $this->artisan('aichat:send-reminders');

        $this->assertSame([2], $this->reminderEvents());
    }

    /** @return list<int> */
    private function reminderEvents(): array
    {
        return Http::recorded()->map(fn ($p) => $p[0]->data())->filter(fn ($d) => $d['event'] === 'booking.reminder')
            ->map(fn ($d) => $d['data']['hours_before'])->values()->all();
    }

    public function test_schedule_page_lists_cancels_and_stays_inside_the_business(): void
    {
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $other = $this->makeBusiness('other', 1);
        $this->bookViaChat('demo', '2026-11-03', '10:00', 'Amy Tan');
        $this->bookViaChat('other', '2026-11-03', '10:00', 'Secret Person');
        $id = $this->inBusiness(fn () => Booking::query()->first()->id);
        $otherId = $this->inBusiness(fn () => Booking::query()->first()->id, $other);

        $this->actingAs($staff)->get('/admin/schedule')->assertOk()->assertSee('Amy Tan')->assertDontSee('Secret Person');
        $this->actingAs($staff)->post("/admin/schedule/{$otherId}/cancel")->assertNotFound();
        $this->actingAs($staff)->post("/admin/schedule/{$id}/cancel")->assertRedirect();
        $this->inBusiness(fn () => $this->assertSame('cancelled', Booking::query()->first()->status));
        $this->inBusiness(fn () => $this->assertSame('confirmed', Booking::query()->first()->status), $other);
    }

    public function test_guests_cannot_see_the_schedule(): void
    {
        $this->get('/admin/schedule')->assertRedirect(route('login'));
    }

    public function test_only_the_owner_can_change_booking_setup(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);

        $this->actingAs($staff)->get('/admin/booking-setup')->assertForbidden();
        $this->actingAs($staff)->post('/admin/booking-setup/services', ['name_en' => 'X', 'duration_min' => 30])->assertForbidden();

        $this->actingAs($owner)->get('/admin/booking-setup')->assertOk()->assertSee('Booking setup');

        $this->actingAs($owner)->post('/admin/booking-setup/services', [
            'name_en' => 'Massage', 'name_zh' => '按摩', 'duration_min' => 45, 'price' => 99, 'practitioners' => [$this->people[0]->id],
        ])->assertRedirect();
        $this->inBusiness(function () {
            $s = Service::query()->where('name->en', 'Massage')->first();
            $this->assertNotNull($s);
            $this->assertSame([$this->people[0]->id], $s->practitioners()->pluck('practitioners.id')->all());
        });

        $this->actingAs($owner)->post('/admin/booking-setup/rules', [
            'slot_step_min' => 15, 'buffer_min' => 5, 'min_notice_hours' => 1, 'max_days_ahead' => 30, 'cancel_hours' => 12,
            'closed_dates' => '2026-12-25, nonsense', 'hours' => ['mon' => ['open' => '09:00', 'close' => '17:00'], 'tue' => ['open' => '', 'close' => '']],
        ])->assertRedirect();

        $b = $this->business->fresh();
        $this->assertSame(['09:00', '17:00'], $b->settings['hours']['mon']);
        $this->assertArrayNotHasKey('tue', $b->settings['hours']);
        $this->assertSame(['2026-12-25'], $b->settings['closed_dates']);
        $this->assertSame(12, $b->settings['booking']['cancel_hours']);
    }

    public function test_owner_cannot_attach_another_businesss_practitioner(): void
    {
        $other = $this->makeBusiness('other', 1);
        $foreign = $this->inBusiness(fn () => Practitioner::query()->first()->id, $other);
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);

        $this->actingAs($owner)->post('/admin/booking-setup/services', [
            'name_en' => 'Sneaky', 'duration_min' => 30, 'practitioners' => [$foreign],
        ])->assertRedirect();

        $this->inBusiness(fn () => $this->assertSame([], Service::query()->where('name->en', 'Sneaky')->first()->practitioners()->pluck('practitioners.id')->all()));
    }

    public function test_menu_preview_never_saves_a_booking(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $call = fn (array $body) => $this->actingAs($owner)->postJson('/admin/api/menu/preview', $body);

        $call(['text' => 'hi', 'restart' => true]);
        $r = $call(['text' => 'x', 'option_id' => 'lang_en']);
        $r = $call(['text' => 'x', 'option_id' => 'book']);
        $r = $call(['text' => 'x', 'option_id' => $r->json('messages.0.options.0.id')]);
        $r = $call(['text' => 'x', 'option_id' => 'day_2026-11-03']);
        $call(['text' => 'x', 'option_id' => 'slot_10:00']);
        $call(['text' => 'Amy']);
        $r = $call(['text' => '0123456789']);

        $this->assertStringContainsString('You are booked', $r->json('messages.0.text'));
        $this->inBusiness(fn () => $this->assertSame(0, Booking::query()->count()));
        $this->inBusiness(fn () => $this->assertSame(0, Customer::query()->count()));
    }
}
