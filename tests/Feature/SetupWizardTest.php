<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $biz = []): array
    {
        $b = Business::factory()->create($biz + ['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(10)]);
        $u = User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);

        return [$b, $u];
    }

    public function test_fresh_wizard_shows_progress_five_steps_and_the_paste_box(): void
    {
        [, $u] = $this->owner();
        $this->actingAs($u)->get('/admin/setup')->assertOk()
            ->assertSee('0 of 5 done')->assertSee('Describe your business')->assertSee('Draft my setup')
            ->assertSee('Go live')->assertSee('I am ready, go live')->assertSee('Copy');
    }

    public function test_draft_shows_cards_with_tick_boxes_and_a_discard_button(): void
    {
        [, $u] = $this->owner();
        $this->actingAs($u)->post('/admin/setup/draft', ['about' => "Facial - RM 188 (60 min)\nLaser: RM 450 45 min"]);
        $this->get('/admin/setup')->assertOk()->assertSee('draft-row', false)->assertSee('Approve and save')->assertSee('Discard draft')->assertSee('Facial');
    }

    public function test_going_live_marks_the_steps_done(): void
    {
        [$b, $u] = $this->owner();
        $this->actingAs($u)->post('/admin/setup/go-live')->assertRedirect();
        $this->assertNotNull($b->fresh()->setup_done_at);
        $this->get('/admin/setup')->assertSee('You are live')->assertDontSee('I am ready, go live');
    }
}
