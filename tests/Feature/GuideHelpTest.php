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
}
