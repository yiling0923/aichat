<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitOrderTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $staff;

    private Customer $customer;

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->customer = $this->in(fn () => Customer::create(['name' => 'Amy Tan', 'phone' => '0123456789']));
    }

    private function booking(float $price, string $status = 'confirmed'): Booking
    {
        return $this->in(function () use ($price, $status) {
            $p = Practitioner::create(['name' => 'Dr Mei']);
            $s = Service::create(['name' => ['en' => 'Facial', 'zh' => '护理'], 'duration_min' => 60, 'price' => $price]);

            return Booking::create(['customer_id' => $this->customer->id, 'service_id' => $s->id, 'practitioner_id' => $p->id, 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2), 'status' => $status]);
        });
    }

    public function test_marking_a_visit_done_creates_an_order_waiting_for_payment(): void
    {
        $b = $this->booking(188);
        $order = null;
        $this->actingAs($this->staff)->post('/admin/schedule/'.$b->id.'/mark', ['status' => 'completed'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, '188.00') && str_contains($s, 'Collect the payment'));

        $order = $this->in(fn () => Order::query()->first());
        // Straight to the payment details for that visit.
        $this->get('/admin/orders?pay='.$order->id)->assertOk()->assertSee('Collect payment')->assertSee('How did they pay?')->assertSee('/admin/orders/'.$order->id, false);
        $this->assertSame('pending', $order->status);
        $this->assertSame('188.00', (string) $order->amount);
        $this->assertSame($this->customer->id, $order->customer_id);
        $this->assertSame('BK-'.$b->id, $order->reference);
        $this->assertSame('Facial', $order->items);
    }

    public function test_it_never_makes_a_second_order_free_visit_order_or_no_show_order(): void
    {
        $b = $this->booking(188);
        $this->actingAs($this->staff)->post('/admin/schedule/'.$b->id.'/mark', ['status' => 'completed']);
        $this->post('/admin/schedule/'.$b->id.'/mark', ['status' => 'completed']);
        $this->assertSame(1, $this->in(fn () => Order::count()));

        $free = $this->booking(0);
        $this->post('/admin/schedule/'.$free->id.'/mark', ['status' => 'completed']);
        $missed = $this->booking(200);
        $this->post('/admin/schedule/'.$missed->id.'/mark', ['status' => 'no_show']);
        $this->assertSame(1, $this->in(fn () => Order::count()));
    }

    public function test_done_goes_straight_to_the_payment_screen_and_records_how_it_was_paid(): void
    {
        $b = $this->booking(300);
        $r = $this->actingAs($this->staff)->post('/admin/schedule/'.$b->id.'/mark', ['status' => 'completed']);
        $order = $this->in(fn () => Order::query()->first());
        $r->assertRedirect(route('orders', ['pay' => $order->id]));

        $this->post('/admin/orders/'.$order->id, ['status' => 'paid', 'amount' => 250, 'method' => 'card', 'notes' => 'Member discount.'])->assertSessionHasNoErrors();
        $fresh = $this->in(fn () => Order::query()->first());
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('250.00', (string) $fresh->amount);
        $this->assertSame('Member discount. Paid by card.', $fresh->notes);

        // A free visit has nothing to collect: no jump to the payment screen.
        $free = $this->booking(0);
        $this->post('/admin/schedule/'.$free->id.'/mark', ['status' => 'completed'])->assertRedirect();
        $this->assertStringNotContainsString('pay=', (string) session('_previous.url'));
    }

    public function test_mark_paid_button_and_unpaid_note_on_the_customer(): void
    {
        $b = $this->booking(250);
        $this->actingAs($this->staff)->post('/admin/schedule/'.$b->id.'/mark', ['status' => 'completed']);
        $order = $this->in(fn () => Order::query()->first());

        $this->get('/admin/orders')->assertOk()->assertSee('title="The customer has paid"', false);
        $this->get('/admin/customers/'.$this->customer->id)->assertOk()->assertSee('250.00 unpaid');

        $this->post('/admin/orders/'.$order->id, ['status' => 'paid'])->assertSessionHas('status', 'Marked as paid.');
        $this->assertSame('paid', $this->in(fn () => Order::query()->first()->status));
        $this->get('/admin/orders')->assertDontSee('title="The customer has paid"', false);
        $this->get('/admin/customers/'.$this->customer->id)->assertDontSee('unpaid');
    }
}
