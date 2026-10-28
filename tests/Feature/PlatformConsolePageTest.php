<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformConsolePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_has_summary_search_filters_pause_dialog_and_invite_tab(): void
    {
        $admin = User::factory()->create(['role' => 'platform_owner', 'business_id' => null]);
        Business::factory()->create(['name' => 'Trial Co', 'plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(5)]);
        Business::factory()->create(['name' => 'Stopped Co', 'status' => 'paused', 'paused_reason' => 'Spam', 'plan' => 'trial', 'trial_ends_at' => now()->addDays(5)]);
        $invite = Invite::generate(null, 'Dr Lee');

        $this->actingAs($admin)->get('/platform')->assertOk()
            ->assertSee('Trial Co')->assertSee('Stopped Co')->assertSee('Reason: Spam')
            ->assertSee('Needs attention')->assertSee('biz-q', false)->assertSee('pause-dialog', false)
            ->assertSee($invite->code)->assertSee('Copy link')->assertSee('Make a code');
    }

    public function test_pausing_with_a_reason_still_works(): void
    {
        $admin = User::factory()->create(['role' => 'platform_owner', 'business_id' => null]);
        $b = Business::factory()->create(['plan' => 'trial', 'trial_ends_at' => now()->addDays(5)]);
        $this->actingAs($admin)->post('/platform/businesses/'.$b->id.'/pause', ['reason' => 'Unpaid'])->assertRedirect();
        $this->assertSame('paused', $b->fresh()->status);
        $this->assertSame('Unpaid', $b->fresh()->paused_reason);
    }
}
