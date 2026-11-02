<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['timezone' => 'Asia/Kuala_Lumpur']);
        $this->user = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function in(callable $fn, ?Business $b = null): mixed
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    /** One web chat, one WhatsApp chat, two bookings of different services/practitioners. */
    private function seedData(): void
    {
        $this->in(function () {
            $web = Conversation::startFor('web');
            $wa = Conversation::startFor('whatsapp');
            $c1 = Customer::create(['name' => 'Amy', 'phone' => '601', 'language' => 'en', 'score' => 'hot']);
            $c2 = Customer::create(['name' => '=HYPERLINK("http://evil","click")', 'phone' => '602', 'language' => 'en']);
            $p1 = Practitioner::create(['name' => 'Dr One']);
            $p2 = Practitioner::create(['name' => 'Dr Two']);
            $s1 = Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 30, 'price' => 100]);
            $s2 = Service::create(['name' => ['en' => 'Laser'], 'duration_min' => 30, 'price' => 300]);
            Booking::create(['customer_id' => $c1->id, 'conversation_id' => $web->id, 'service_id' => $s1->id, 'practitioner_id' => $p1->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30), 'status' => 'confirmed']);
            Booking::create(['customer_id' => $c2->id, 'conversation_id' => $wa->id, 'service_id' => $s2->id, 'practitioner_id' => $p2->id, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addMinutes(30), 'status' => 'confirmed']);
            Order::create(['customer_id' => $c1->id, 'amount' => 50, 'status' => 'paid', 'ordered_at' => now()]);
        });
    }

    public function test_page_shows_filters_kpis_charts_and_more_sections(): void
    {
        $this->seedData();

        $this->actingAs($this->user)->get('/admin/reports')->assertOk()
            ->assertSee('Export CSV')->assertSee('Day by day')->assertSee('Busiest weekdays')->assertSee('Busiest hours')
            ->assertSee('By practitioner')->assertSee('No-show rate')->assertSee('Dr One')->assertSee('Laser')->assertSee('WhatsApp');
    }

    public function test_channel_service_and_practitioner_filters_change_the_booking_numbers(): void
    {
        $this->seedData();
        $this->actingAs($this->user);

        $this->get('/admin/reports?channel=whatsapp')->assertOk()->assertSee('<span>Laser</span>', false)->assertDontSee('<span>Facial</span>', false);
        $svc = $this->in(fn () => Service::query()->where('duration_min', 30)->orderBy('id')->first());
        $this->get('/admin/reports?service='.$svc->id)->assertOk()->assertSee('<span>Facial</span>', false)->assertDontSee('<span>Laser</span>', false);
        $pr = $this->in(fn () => Practitioner::query()->where('name', 'Dr Two')->first());
        $this->get('/admin/reports?practitioner='.$pr->id)->assertOk()->assertSee('<span>Laser</span>', false)->assertDontSee('<span>Facial</span>', false);
        $this->get('/admin/reports?channel=bogus&service=abc')->assertOk();   // junk is ignored
    }

    public function test_periods_and_custom_dates(): void
    {
        $this->seedData();
        $this->actingAs($this->user);

        $this->get('/admin/reports?period=this_month')->assertOk()->assertSee('This month');
        $this->get('/admin/reports?period=last_month')->assertOk()->assertSee('Last month');
        $this->get('/admin/reports?period=custom&from=2020-01-01&to=2020-01-31')->assertOk()->assertSee('1 Jan 2020 to 31 Jan 2020');
        $this->get('/admin/reports?period=custom&from=2020-02-01&to=2020-01-01')->assertOk()->assertSee('1 Jan 2020 to 1 Feb 2020');   // swapped dates are fixed
        $this->get('/admin/reports?period=custom&from=garbage&to=nonsense')->assertOk();
        $this->get('/admin/reports?period=custom&from=1990-01-01&to=2026-01-01')->assertOk();   // capped at a year
    }

    public function test_csv_export_has_the_right_columns_and_stops_spreadsheet_formulas(): void
    {
        $this->seedData();
        $this->actingAs($this->user);

        $res = $this->get('/admin/reports/export?section=bookings')->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Booked on",Visit,Customer,Phone,Service,Practitioner,Status', $csv);
        $this->assertStringContainsString('Amy', $csv);
        $this->assertStringContainsString("\"'=HYPERLINK", $csv);          // neutralised
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);

        $filtered = $this->get('/admin/reports/export?section=bookings&channel=whatsapp')->streamedContent();
        $this->assertStringContainsString('Laser', $filtered);
        $this->assertStringNotContainsString('Facial', $filtered);

        foreach (['summary', 'daily', 'customers', 'orders', 'surveys', 'nonsense'] as $section) {
            $this->get('/admin/reports/export?section='.$section)->assertOk();
        }
        $this->assertStringContainsString('Chats started', $this->get('/admin/reports/export')->streamedContent());   // summary is the default
        $this->assertStringContainsString('Date,"Chats started","New customers","Bookings made"', $this->get('/admin/reports/export?section=daily')->streamedContent());
    }

    public function test_sales_and_marketing_tabs_show_money_funnel_and_win_back(): void
    {
        $this->seedData();
        $this->in(function () {
            $old = Customer::create(['name' => 'Lapsed Lee', 'phone' => '699', 'language' => 'en', 'marketing_opt_in' => true]);
            $svc = Service::first();
            Booking::create(['customer_id' => $old->id, 'service_id' => $svc->id, 'practitioner_id' => Practitioner::first()->id, 'starts_at' => now()->subDays(90), 'ends_at' => now()->subDays(90)->addMinutes(30), 'status' => 'completed']);
            Order::create(['customer_id' => $old->id, 'amount' => 80, 'status' => 'pending', 'ordered_at' => now()->subDays(20)]);
        });

        $page = $this->actingAs($this->user)->get('/admin/reports?period=30d')->assertOk()
            ->assertSee('id="tab-sales"', false)->assertSee('id="tab-marketing"', false)
            ->assertSee('Money taken')->assertSee('From first message to payment')->assertSee('Customers to win back')
            ->assertSee('Lapsed Lee')->assertSee('Where customers come from');
        $this->assertStringContainsString('50.00', $page->getContent());

        foreach (['sales_summary', 'sales_service', 'sales_person', 'sales_customers', 'sales_funnel', 'mk_channels', 'mk_topics', 'mk_broadcasts', 'mk_automations', 'mk_winback'] as $section) {
            $this->get('/admin/reports/export?section='.$section)->assertOk();
        }
        $this->assertStringContainsString('Lapsed Lee', $this->get('/admin/reports/export?section=mk_winback')->streamedContent());
        $this->assertStringContainsString('Amy', $this->get('/admin/reports/export?section=sales_customers')->streamedContent());
    }

    public function test_reports_and_exports_never_include_another_business_and_need_a_login(): void
    {
        $this->seedData();
        $other = Business::factory()->create();
        $this->in(fn () => Customer::create(['name' => 'Zed Elsewhere', 'phone' => '999', 'language' => 'en']), $other);

        $csv = $this->actingAs($this->user)->get('/admin/reports/export?section=customers')->streamedContent();
        $this->assertStringContainsString('Amy', $csv);
        $this->assertStringNotContainsString('Zed Elsewhere', $csv);

        auth()->logout();
        $this->get('/admin/reports/export')->assertRedirect(route('login'));
    }

    public function test_sales_can_be_seen_per_channel_and_the_filter_limits_them(): void
    {
        $this->in(function () {
            $wa = Customer::create(['name' => 'Wanda WA', 'phone' => '6011', 'language' => 'en']);
            $web = Customer::create(['name' => 'Webb Web', 'phone' => '6022', 'language' => 'en']);
            $typed = Customer::create(['name' => 'Walkin Wally', 'phone' => '6033', 'language' => 'en']);
            Conversation::startFor('whatsapp')->forceFill(['customer_id' => $wa->id])->save();
            Conversation::startFor('web')->forceFill(['customer_id' => $web->id])->save();
            Order::create(['customer_id' => $wa->id, 'amount' => 100, 'status' => 'paid', 'ordered_at' => now()]);
            Order::create(['customer_id' => $web->id, 'amount' => 40, 'status' => 'paid', 'ordered_at' => now()]);
            Order::create(['customer_id' => $typed->id, 'amount' => 7, 'status' => 'paid', 'ordered_at' => now()]);
            \App\Models\Message::query()->create(['conversation_id' => Conversation::query()->where('channel', 'whatsapp')->first()->id, 'direction' => 'in', 'sender_type' => 'customer', 'body' => 'hello']);
        });

        $all = $this->actingAs($this->user)->get('/admin/reports?period=30d')->assertOk()->assertSee('Money by channel')->assertSee('No chat (typed in by hand)');
        $this->assertStringContainsString('147.00', $all->getContent());

        $wa = $this->actingAs($this->user)->get('/admin/reports?period=30d&channel=whatsapp')->assertOk();
        $this->assertStringContainsString('RM 100.00', $wa->getContent());
        $this->assertStringNotContainsString('Webb Web', $wa->getContent());
        $this->assertStringNotContainsString('Walkin Wally', $wa->getContent());

        $csv = $this->actingAs($this->user)->get('/admin/reports/export?section=sales_channel')->assertOk()->streamedContent();
        $this->assertStringContainsString('WhatsApp,100.00', $csv);
        $this->assertStringContainsString('Website,40.00', $csv);
        $channels = $this->actingAs($this->user)->get('/admin/reports/export?section=mk_channels')->streamedContent();
        $this->assertStringContainsString('"Messages from customers"', $channels);
    }
}
