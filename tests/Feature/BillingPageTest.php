<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $biz): User
    {
        $b = Business::factory()->create($biz);

        return User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);
    }

    public function test_trial_shows_days_left_usage_bars_and_plan_cards(): void
    {
        $u = $this->owner(['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(2)]);
        $this->actingAs($u)->get('/admin/billing')->assertOk()
            ->assertSee('Your plan')->assertSee('Trial: ')->assertSee('Your trial ends in')->assertSee('class="progress', false)
            ->assertSee('Choose Starter')->assertSee('Most popular')->assertSee('No payments yet');
    }

    public function test_active_plan_is_marked_current_and_other_is_an_upgrade(): void
    {
        $u = $this->owner(['plan' => 'starter', 'subscription_status' => 'active', 'current_period_ends_at' => now()->addDays(20)]);
        $this->actingAs($u)->get('/admin/billing')->assertOk()
            ->assertSee('Current plan')->assertSee('Upgrade to Growth')->assertSee('Renews')->assertSee('Cancel renewal');
    }

    public function test_expired_trial_explains_what_happens(): void
    {
        $u = $this->owner(['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->subDay()]);
        $this->actingAs($u)->get('/admin/billing')->assertOk()->assertSee('Trial ended')->assertSee('The AI is paused');
    }
}
