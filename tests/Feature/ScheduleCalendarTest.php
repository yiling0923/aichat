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
}
