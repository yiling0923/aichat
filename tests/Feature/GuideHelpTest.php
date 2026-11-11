<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use App\Support\SetupGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideHelpTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'gd-co', 'plan' => 'trial', 'setup_done_at' => null, 'settings' => []]);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function step(string $key): array
    {
        return collect(app(SetupGuide::class)->steps($this->business->fresh(), $this->owner))->firstWhere('key', $key);
    }

    public function test_a_new_business_starts_with_nothing_done_and_steps_tick_themselves(): void
    {
        $this->assertFalse($this->step('services')['done']);
        $this->assertFalse($this->step('hours')['done']);
        $this->assertFalse($this->step('business')['done']);

        app(CurrentBusiness::class)->runAs($this->business, fn () => Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 60, 'price' => 100, 'sort' => 1]));
        $this->business->update(['settings' => ['hours' => ['mon' => ['10:00', '19:00']], 'address' => '1 Jalan Test']]);

        $this->assertTrue($this->step('services')['done'] && $this->step('services')['auto']);
        $this->assertTrue($this->step('hours')['done']);
        $this->assertTrue($this->step('business')['done']);
        $this->assertFalse($this->step('menu')['done']);
    }

    public function test_the_page_shows_progress_and_the_next_step(): void
    {
        $this->actingAs($this->owner)->get('/admin/guide')->assertOk()->assertSee('Setup guide')->assertSee('Next up')
            ->assertSee('Add your services and prices')->assertSee('Nice to have')->assertSee('Mark as done');

        $progress = app(SetupGuide::class)->progress($this->business, $this->owner);
        $this->assertFalse($progress['complete']);
        $this->assertGreaterThan(0, $progress['minutes']);
    }

    public function test_marking_a_step_done_by_hand_works_and_can_be_undone(): void
    {
        $this->actingAs($this->owner)->post('/admin/guide/menu')->assertRedirect();
        $this->assertTrue($this->step('menu')['done']);
        $this->assertFalse($this->step('menu')['auto']);

        $this->actingAs($this->owner)->post('/admin/guide/menu')->assertRedirect();
        $this->assertFalse($this->step('menu')['done']);

        $this->actingAs($this->owner)->post('/admin/guide/nonsense')->assertNotFound();
    }

    public function test_the_side_menu_shows_the_guide_to_owners_until_everything_essential_is_done(): void
    {
        $this->actingAs($this->owner)->get('/admin/today')->assertSee('Setup guide')->assertSee('guide-badge', false);

        foreach (['business', 'services', 'hours', 'menu', 'chat', 'live'] as $key) {
            $this->actingAs($this->owner)->post('/admin/guide/'.$key);
        }
        $this->assertTrue(app(SetupGuide::class)->progress($this->business->fresh(), $this->owner)['complete']);
        \Illuminate\Support\Facades\Cache::flush();
        $this->actingAs($this->owner)->get('/admin/today')->assertDontSee('guide-badge', false);
    }

    public function test_staff_cannot_open_the_guide_but_everyone_can_open_help(): void
    {
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->actingAs($staff)->get('/admin/guide')->assertForbidden();
        $this->actingAs($staff)->post('/admin/guide/menu')->assertForbidden();
        $this->actingAs($staff)->get('/admin/help')->assertOk()->assertSee('The big picture')->assertSee('Owner and manager');

        $this->actingAs($this->owner)->get('/admin/help')->assertOk()->assertSee('Open the setup guide')->assertSee('Questions and fixes')->assertSee('Words used here');
    }

    public function test_the_help_page_links_and_the_hub_tiles_exist(): void
    {
        $r = $this->actingAs($this->owner)->get('/admin/help')->assertOk();
        foreach (['overview', 'day', 'reach', 'inbox', 'menu', 'booking', 'customers', 'orders', 'marketing', 'reports', 'alerts', 'ai', 'team', 'billing', 'data', 'faq', 'words'] as $id) {
            $r->assertSee('id="'.$id.'"', false);
            $r->assertSee('href="#'.$id.'"', false);
        }
        $this->actingAs($this->owner)->get('/admin/manage')->assertSee('Setup guide')->assertSee('Help');
    }

    public function test_help_page_is_chinese_for_chinese_staff(): void
    {
        $this->owner->forceFill(['locale' => 'zh'])->save();

        $this->actingAs($this->owner)->get('/admin/help')->assertOk()->assertSee('需要什么帮助？')->assertSee('整体介绍')->assertDontSee('How can we help?');

        $this->owner->forceFill(['locale' => 'en'])->save();
        $this->actingAs($this->owner)->get('/admin/help')->assertOk()->assertSee('How can we help?');
    }

    public function test_the_guide_leads_from_one_step_to_the_next(): void
    {
        $owner = $this->actingAs($this->owner ?? \App\Models\User::factory()->create(['business_id' => \App\Models\Business::factory()->create(['slug' => 'wiz-co'])->id, 'role' => 'owner']));
        $user = auth()->user();

        // Starting at the first step opens its page and shows the bar.
        $this->get('/admin/guide/go/business')->assertRedirect(route('settings'));
        $this->get('/admin/settings')->assertOk()->assertSee('Step 1 of', false)->assertSee('Done, next step');

        // Done: the next essential step opens by itself.
        $this->post('/admin/guide/next')->assertRedirect(route('booking.setup'));
        $this->assertTrue((bool) (($user->business->fresh()->settings['guide_done'] ?? [])['business'] ?? false));
        $this->get('/admin/booking-setup')->assertOk()->assertSee('Step 2 of', false);

        // The customer chat step is shown on the guide page, and leaving the guide hides the bar.
        $this->get('/admin/guide/go/chat')->assertRedirect(route('guide'));
        $this->get('/admin/guide')->assertOk()->assertSee('Open my chat');
        $this->post('/admin/guide/exit')->assertRedirect(route('guide'));
        $this->get('/admin/settings')->assertOk()->assertDontSee('Done, next step');

        // Unknown steps and non-owners get nothing.
        $this->get('/admin/guide/go/nonsense')->assertNotFound();
    }

    public function test_saving_a_step_takes_the_owner_straight_to_the_next_one(): void
    {
        $business = \App\Models\Business::factory()->create(['slug' => 'auto-co', 'timezone' => 'UTC', 'settings' => []]);
        $owner = \App\Models\User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $this->actingAs($owner);

        // Step 1 (business details): saving without an address does not finish it, so nothing moves on.
        $this->get('/admin/guide/go/business')->assertRedirect(route('settings'));
        $this->post('/admin/settings/profile', ['name' => 'Auto Co', 'timezone' => 'UTC', 'address' => ''])->assertRedirect();
        $this->assertStringNotContainsString('booking-setup', (string) session()->previousUrl());
        $this->assertSame('business', session('guide_active'));

        // Saving the address finishes it and opens the next step by itself.
        $this->post('/admin/settings/profile', ['name' => 'Auto Co', 'timezone' => 'UTC', 'address' => '1 Main Street'])->assertRedirect(route('booking.setup'));
        $this->assertSame('services', session('guide_active'));

        // A page the guide is not on, or someone who is not the owner, is never redirected.
        $this->post('/admin/guide/exit');
        $this->post('/admin/settings/profile', ['name' => 'Auto Co', 'timezone' => 'UTC', 'address' => '2 Main Street'])->assertRedirect()->assertSessionHas('status');
        $this->assertNull(session('guide_active'));
    }

    public function test_publishing_the_menu_finishes_the_menu_step_and_tells_the_page_where_to_go(): void
    {
        $business = \App\Models\Business::factory()->create(['slug' => 'menu-co', 'settings' => ['address' => 'x', 'hours' => ['mon' => ['09:00', '17:00']]]]);
        $owner = \App\Models\User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        app(\App\Support\CurrentBusiness::class)->runAs($business, fn () => \App\Models\Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 30, 'price' => 10]));
        $this->actingAs($owner)->get('/admin/guide/go/menu')->assertRedirect();

        $res = $this->postJson('/admin/api/menu/publish')->assertOk();
        $this->assertNotNull($res->headers->get('X-Guide-Next'));
        $this->assertTrue((bool) ($business->fresh()->settings['guide_done']['menu'] ?? false));
    }
}
