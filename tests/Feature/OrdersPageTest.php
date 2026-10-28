<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersPageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['timezone' => 'Asia/Kuala_Lumpur', 'settings' => ['currency' => 'RM']]);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function in(callable $fn, ?Business $b = null): mixed
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    private function order(array $over = []): Order
    {
        return $this->in(fn () => Order::create($over + ['amount' => 100, 'status' => 'paid', 'ordered_at' => now()]));
    }

    public function test_totals_counts_search_and_period_follow_the_filters(): void
    {
        $amy = $this->in(fn () => Customer::create(['name' => 'Amy Lim', 'phone' => '601', 'language' => 'en']));
        $this->order(['customer_id' => $amy->id, 'items' => 'Facial package', 'amount' => 300]);
        $this->order(['items' => 'Serum', 'amount' => 80, 'status' => 'pending']);
        $this->order(['items' => 'Old sale', 'amount' => 999, 'ordered_at' => now()->subMonths(3)]);
        $this->actingAs($this->staff);

        $all = $this->get('/admin/orders')->assertOk();
        $all->assertSee('<b>RM 1,299.00</b>', false)->assertSee('All (3)')->assertSee('Paid (2)')->assertSee('Waiting for payment (1)');

        $month = $this->get('/admin/orders?period=month')->assertOk();
        $month->assertSee('<b>RM 300.00</b>', false)->assertSee('Period: This month')->assertDontSee('Old sale')->assertSee('Export CSV');

        $this->get('/admin/orders?q=amy')->assertSee('Facial package')->assertDontSee('Serum');
        $this->get('/admin/orders?q=zzz')->assertSee('No orders match these filters.');
        $this->get('/admin/orders?status=pending&period=year')->assertOk()->assertSee('Serum')->assertDontSee('Facial package');
        $this->get('/admin/orders?period=nonsense&status=junk&q=%25')->assertOk();
    }

    public function test_an_order_can_be_fully_edited_and_deleted_and_status_only_updates_still_work(): void
    {
        $o = $this->order(['items' => 'Before', 'amount' => 50, 'status' => 'pending']);
        $c = $this->in(fn () => Customer::create(['name' => 'Bea', 'phone' => '602', 'language' => 'en']));
        $this->actingAs($this->staff);

        $this->post('/admin/orders/'.$o->id, ['status' => 'paid'])->assertRedirect();
        $this->assertSame(['paid', 'Before'], [$o->fresh()->status, $o->fresh()->items]);

        $this->post('/admin/orders/'.$o->id, ['customer_id' => $c->id, 'items' => 'After', 'amount' => '75.50', 'ordered_at' => '2026-10-01T10:30', 'notes' => 'Paid in cash', 'reference' => 'INV-9'])->assertSessionHasNoErrors();
        $o->refresh();
        $this->assertSame([$c->id, 'After', '75.50', 'Paid in cash', 'INV-9'], [$o->customer_id, $o->items, $o->amount, $o->notes, $o->reference]);
        $this->assertSame('2026-10-01 10:30', $o->ordered_at->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'));

        $this->post('/admin/orders/'.$o->id, ['amount' => -1])->assertSessionHasErrors('amount');
        $this->post('/admin/orders/'.$o->id, ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->get('/admin/orders')->assertSee('data-edit', false)->assertSee('INV-9');
        $this->post('/admin/orders/'.$o->id.'/delete')->assertRedirect();
        $this->assertNull(Order::query()->withoutGlobalScopes()->find($o->id));
    }

    public function test_other_businesses_orders_cannot_be_seen_edited_or_deleted_and_guests_are_sent_away(): void
    {
        $other = Business::factory()->create();
        $theirs = $this->in(fn () => Order::create(['amount' => 5, 'status' => 'paid', 'items' => 'Not yours', 'ordered_at' => now()]), $other);

        $this->actingAs($this->staff)->get('/admin/orders')->assertDontSee('Not yours');
        $this->post('/admin/orders/'.$theirs->id, ['items' => 'Hacked'])->assertNotFound();
        $this->post('/admin/orders/'.$theirs->id.'/delete')->assertNotFound();
        $this->assertSame('Not yours', Order::query()->withoutGlobalScopes()->find($theirs->id)->items);

        auth()->logout();
        $this->post('/admin/orders/'.$theirs->id.'/delete')->assertRedirect(route('login'));
    }
}
