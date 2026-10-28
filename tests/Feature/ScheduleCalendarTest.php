<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['timezone' => 'Asia/Kuala_Lumpur']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function make(string $name, string $practitioner, string $service, string $at, string $status = 'confirmed', ?Business $b = null): Booking
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, function () use ($name, $practitioner, $service, $at, $status) {
            $c = Customer::create(['name' => $name, 'phone' => '60'.rand(1000000, 9999999), 'language' => 'en']);
            $p = Practitioner::firstOrCreate(['name' => $practitioner]);
            $s = Service::firstOrCreate(['duration_min' => 60, 'price' => 10], ['name' => ['en' => $service]]);
            $start = \Carbon\Carbon::parse($at, 'Asia/Kuala_Lumpur')->utc();

            return Booking::create(['customer_id' => $c->id, 'service_id' => $s->id, 'practitioner_id' => $p->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'status' => $status]);
        });
    }

    public function test_month_view_is_the_default_and_shows_bookings_with_a_detail_popup(): void
    {
        $day = now('Asia/Kuala_Lumpur')->addDay()->setTime(11, 0)->format('Y-m-d H:i');
        $b = $this->make('Calendar Carol', 'Dr A', 'Facial', $day);

        $this->actingAs($this->staff)->get('/admin/schedule?date='.substr($day, 0, 10))->assertOk()
            ->assertSee('Calendar Carol')->assertSee('data-open="b-'.$b->id.'"', false)->assertSee('id="b-'.$b->id.'"', false)
            ->assertSee('Month')->assertSee('Week')->assertSee('List')->assertSee('Cancel booking');
    }

    public function test_week_view_places_bookings_and_handles_overlaps(): void
    {
        $monday = now('Asia/Kuala_Lumpur')->addWeek()->startOfWeek();
        $this->make('Early Eve', 'Dr A', 'Facial', $monday->copy()->setTime(7, 30)->format('Y-m-d H:i'));
        $this->make('Same Time Sam', 'Dr B', 'Facial', $monday->copy()->setTime(7, 30)->format('Y-m-d H:i'));

        $r = $this->actingAs($this->staff)->get('/admin/schedule?view=week&date='.$monday->format('Y-m-d'))->assertOk();
        $r->assertSee('Early Eve')->assertSee('Same Time Sam')->assertSee('07:00')->assertSee('width: calc(100% / 2 - 4px)', false);   // the grid starts early enough and the two sit side by side
    }

    public function test_practitioner_filter_and_cancelled_bookings(): void
    {
        $day = now('Asia/Kuala_Lumpur')->addDay()->setTime(14, 0)->format('Y-m-d H:i');
        $this->make('Alice Mine', 'Dr A', 'Facial', $day);
        $this->make('Bob Theirs', 'Dr B', 'Facial', $day);
        $this->make('Cancelled Cat', 'Dr A', 'Facial', $day, 'cancelled');
        $dr = app(CurrentBusiness::class)->runAs($this->business, fn () => Practitioner::where('name', 'Dr A')->first());

        $r = $this->actingAs($this->staff)->get('/admin/schedule?date='.substr($day, 0, 10).'&practitioner='.$dr->id)->assertOk();
        $r->assertSee('Alice Mine')->assertDontSee('Bob Theirs')->assertDontSee('Cancelled Cat');
    }

    public function test_navigation_junk_input_and_other_businesses(): void
    {
        $other = Business::factory()->create();
        $this->make('Secret Stranger', 'Dr X', 'Facial', now()->addDay()->format('Y-m-d H:i'), 'confirmed', $other);
        $this->actingAs($this->staff);

        foreach (['month', 'week', 'list', 'nonsense'] as $view) {
            $this->get('/admin/schedule?view='.$view)->assertOk()->assertDontSee('Secret Stranger');
        }
        $this->get('/admin/schedule?date=garbage')->assertOk();
        $this->get('/admin/schedule?view=week&date=2031-02-30')->assertOk();
        $this->get('/admin/schedule?practitioner=abc')->assertOk();
        $this->get('/admin/schedule?view=month&date='.now()->addMonth()->format('Y-m-d'))->assertOk()->assertSee(now()->addMonth()->format('F Y'));
    }

    private function local(string $at): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($at, 'Asia/Kuala_Lumpur');
    }

    public function test_moving_a_booking_changes_its_time_and_resets_reminders(): void
    {
        $b = $this->make('Move Mia', 'Dr A', 'Facial', now('Asia/Kuala_Lumpur')->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));
        $b->forceFill(['reminder_24_sent_at' => now()])->save();
        $new = now('Asia/Kuala_Lumpur')->addDays(3)->setTime(15, 30);

        $this->actingAs($this->staff)->postJson('/admin/schedule/'.$b->id.'/move', ['starts_at' => $new->format('Y-m-d H:i')])->assertOk()->assertJson(['ok' => true]);

        $b = app(CurrentBusiness::class)->runAs($this->business, fn () => Booking::find($b->id));
        $this->assertSame($new->format('Y-m-d H:i'), $b->starts_at->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'));
        $this->assertSame($new->copy()->addHour()->format('H:i'), $b->ends_at->copy()->setTimezone('Asia/Kuala_Lumpur')->format('H:i'));   // the service length is kept
        $this->assertNull($b->reminder_24_sent_at);
    }

    public function test_moving_onto_another_booking_of_the_same_practitioner_is_refused_but_other_people_are_fine(): void
    {
        $day = now('Asia/Kuala_Lumpur')->addDays(2)->setTime(10, 0);
        $a = $this->make('First', 'Dr A', 'Facial', $day->format('Y-m-d H:i'));
        $b = $this->make('Second', 'Dr A', 'Facial', $day->copy()->setTime(14, 0)->format('Y-m-d H:i'));
        $c = $this->make('Third', 'Dr B', 'Facial', $day->copy()->setTime(16, 0)->format('Y-m-d H:i'));
        $this->actingAs($this->staff);

        $this->postJson('/admin/schedule/'.$b->id.'/move', ['starts_at' => $day->copy()->setTime(10, 30)->format('Y-m-d H:i')])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame($day->copy()->setTime(14, 0)->format('H:i'), app(CurrentBusiness::class)->runAs($this->business, fn () => Booking::find($b->id))->starts_at->copy()->setTimezone('Asia/Kuala_Lumpur')->format('H:i'));
        $this->postJson('/admin/schedule/'.$c->id.'/move', ['starts_at' => $day->format('Y-m-d H:i')])->assertOk();   // another practitioner, same time: fine
        $this->postJson('/admin/schedule/'.$a->id.'/move', ['starts_at' => $day->copy()->addMinutes(15)->format('Y-m-d H:i')])->assertOk();   // overlapping itself is fine
    }

    public function test_cannot_move_into_the_past_a_cancelled_booking_or_another_business_and_input_is_checked(): void
    {
        $b = $this->make('Past Pat', 'Dr A', 'Facial', now('Asia/Kuala_Lumpur')->addDays(2)->format('Y-m-d H:i'));
        $cancelled = $this->make('Gone', 'Dr A', 'Facial', now('Asia/Kuala_Lumpur')->addDays(3)->format('Y-m-d H:i'), 'cancelled');
        $other = Business::factory()->create();
        $theirs = $this->make('Theirs', 'Dr Z', 'Facial', now()->addDays(2)->format('Y-m-d H:i'), 'confirmed', $other);
        $this->actingAs($this->staff);
        $future = now('Asia/Kuala_Lumpur')->addDays(5)->format('Y-m-d H:i');

        $this->postJson('/admin/schedule/'.$b->id.'/move', ['starts_at' => now('Asia/Kuala_Lumpur')->subHour()->format('Y-m-d H:i')])->assertStatus(422);
        $this->postJson('/admin/schedule/'.$cancelled->id.'/move', ['starts_at' => $future])->assertStatus(422);
        $this->postJson('/admin/schedule/'.$theirs->id.'/move', ['starts_at' => $future])->assertNotFound();
        $this->postJson('/admin/schedule/'.$b->id.'/move', ['starts_at' => 'tomorrow'])->assertStatus(422);
        $this->postJson('/admin/schedule/'.$b->id.'/move', [])->assertStatus(422);

        auth()->logout();
        $this->postJson('/admin/schedule/'.$b->id.'/move', ['starts_at' => $future])->assertUnauthorized();
    }

    public function test_the_customer_is_told_in_the_chat_only_when_asked_and_possible(): void
    {
        $b = $this->make('Chatty Cleo', 'Dr A', 'Facial', now('Asia/Kuala_Lumpur')->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));
        $chat = app(CurrentBusiness::class)->runAs($this->business, function () use ($b) {
            $chat = \App\Models\Conversation::startFor('web');
            $chat->forceFill(['last_inbound_at' => now(), 'language' => 'en'])->save();
            Booking::find($b->id)->forceFill(['conversation_id' => $chat->id])->save();

            return $chat;
        });
        $this->actingAs($this->staff);
        $when = now('Asia/Kuala_Lumpur')->addDays(4)->setTime(11, 0)->format('Y-m-d H:i');

        $this->postJson('/admin/schedule/'.$b->id.'/move', ['starts_at' => $when])->assertOk();
        $this->assertSame(0, app(CurrentBusiness::class)->runAs($this->business, fn () => \App\Models\Message::where('conversation_id', $chat->id)->where('body', 'like', '%moved%')->count()));

        $res = $this->postJson('/admin/schedule/'.$b->id.'/move', ['starts_at' => now('Asia/Kuala_Lumpur')->addDays(5)->setTime(11, 0)->format('Y-m-d H:i'), 'notify' => true])->assertOk();
        $this->assertStringContainsString('customer was told', $res->json('message'));
        $this->assertSame(1, app(CurrentBusiness::class)->runAs($this->business, fn () => \App\Models\Message::where('conversation_id', $chat->id)->where('body', 'like', '%moved%')->count()));
    }

    public function test_the_change_time_form_in_the_popup_works_without_javascript(): void
    {
        $b = $this->make('Form Fay', 'Dr A', 'Facial', now('Asia/Kuala_Lumpur')->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));
        $when = now('Asia/Kuala_Lumpur')->addDays(6)->setTime(9, 15)->format('Y-m-d H:i');

        $this->actingAs($this->staff)->post('/admin/schedule/'.$b->id.'/move', ['starts_at' => $when])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->staff)->post('/admin/schedule/'.$b->id.'/move', ['starts_at' => '2001-01-01 10:00'])->assertSessionHasErrors('starts_at');
        $this->get('/admin/schedule?date='.substr($when, 0, 10))->assertSee('Change time')->assertSee('draggable="true"', false);
    }

    public function test_the_view_buttons_link_to_the_other_views_and_keep_the_practitioner_filter(): void
    {
        $this->make('Link Lee', 'Dr A', 'Facial', now('Asia/Kuala_Lumpur')->addDay()->format('Y-m-d H:i'));
        $dr = app(CurrentBusiness::class)->runAs($this->business, fn () => Practitioner::first());
        $this->actingAs($this->staff);

        $month = $this->get('/admin/schedule?practitioner='.$dr->id)->assertOk();
        $month->assertSee('view=week', false)->assertSee('view=list', false)->assertSee('practitioner='.$dr->id, false);
        $this->assertMatchesRegularExpression('/<a [^>]*href="[^"]*view=week[^"]*"[^>]*>Week<\/a>/', $month->getContent());
        $this->assertMatchesRegularExpression('/<a [^>]*href="[^"]*view=list[^"]*"[^>]*>List<\/a>/', $month->getContent());

        $week = $this->get('/admin/schedule?view=week')->assertOk();
        $this->assertMatchesRegularExpression('/<a [^>]*href="[^"]*view=month[^"]*"[^>]*>Month<\/a>/', $week->getContent());
        $this->get('/admin/schedule?view=list')->assertOk()->assertSee('Upcoming');
    }
}
