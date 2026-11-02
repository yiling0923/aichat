<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayAttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_today_page_puts_overdue_and_problems_at_the_top(): void
    {
        $b = Business::factory()->create(['slug' => 'att-co', 'plan' => 'starter', 'subscription_status' => 'active', 'timezone' => 'UTC']);
        $owner = User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);
        app(CurrentBusiness::class)->runAs($b, function () {
            Task::create(['title' => 'Call Amy back', 'due_at' => now()->subDays(2)]);
            Task::create(['title' => 'Later today thing', 'due_at' => now()->addMinutes(5)]);
            Channel::create(['type' => 'whatsapp', 'name' => 'WA', 'external_id' => '123', 'access_token' => 'x', 'active' => true, 'last_error' => 'token expired']);
        });

        $page = $this->actingAs($owner)->get('/admin/today')->assertOk();
        $page->assertSee('Needs your attention')->assertSee('1 task is overdue')->assertSee('1 channel has a connection problem');
        $page->assertSee('Call Amy back')->assertSee('Overdue');
    }

    public function test_a_quiet_business_sees_all_clear(): void
    {
        $b = Business::factory()->create(['slug' => 'quiet-co', 'plan' => 'starter', 'subscription_status' => 'active']);
        $owner = User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);
        $this->actingAs($owner)->get('/admin/today')->assertOk()->assertSee('All clear')->assertDontSee('Needs your attention');
    }

    public function test_sales_show_on_today_for_people_who_may_see_reports(): void
    {
        $b = Business::factory()->create(['slug' => 'sale-co', 'plan' => 'starter', 'subscription_status' => 'active', 'timezone' => 'UTC', 'settings' => ['currency' => 'RM']]);
        $owner = User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);
        $staff = User::factory()->create(['business_id' => $b->id, 'role' => 'staff', 'permissions' => ['inbox']]);
        app(CurrentBusiness::class)->runAs($b, function () {
            \App\Models\Order::create(['reference' => 'S1', 'items' => 'Facial', 'amount' => 120, 'status' => 'paid', 'ordered_at' => now()]);
            \App\Models\Order::create(['reference' => 'S2', 'items' => 'Mask', 'amount' => 80, 'status' => 'pending', 'ordered_at' => now()]);
        });

        $this->actingAs($owner)->get('/admin/today')->assertOk()->assertSee('Sales')->assertSee('RM 120')->assertSee('1 order not paid yet')->assertSee('RM 80');
        $this->actingAs($staff)->get('/admin/today')->assertOk()->assertDontSee('Open reports')->assertDontSee('RM 120');
    }
}
