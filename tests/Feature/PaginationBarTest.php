<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_lists_get_the_neat_previous_next_bar(): void
    {
        $b = Business::factory()->create();
        $u = User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);
        app(CurrentBusiness::class)->runAs($b, function () {
            for ($i = 0; $i < 30; $i++) {
                Order::create(['amount' => 10, 'status' => 'paid', 'ordered_at' => now()]);
            }
        });

        $this->actingAs($u)->get('/admin/orders')->assertOk()
            ->assertSee('class="pg"', false)->assertSee('Showing')->assertSee('Page 1 of 2')->assertSee('Next')->assertSee('pg-off', false);
        $this->get('/admin/orders?page=2')->assertSee('Previous')->assertSee('Page 2 of 2');
    }
}
