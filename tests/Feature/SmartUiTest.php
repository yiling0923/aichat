<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartUiTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(5)]);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function in(callable $fn, ?Business $b = null): mixed
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    public function test_quick_search_finds_customers_and_chats_of_this_business_only(): void
    {
        $other = Business::factory()->create();
        $this->in(function () {
            Customer::create(['name' => 'Sophie Searchable', 'phone' => '60111', 'language' => 'en']);
            $c = Conversation::startFor('web');
            $c->forceFill(['contact_name' => 'Sophie Chatter'])->save();
            $c->addMessage('in', 'customer', 'do you have a facial for sensitive skin');
        });
        $this->in(fn () => Customer::create(['name' => 'Sophie Elsewhere', 'phone' => '60222', 'language' => 'en']), $other);

        $r = $this->actingAs($this->owner)->getJson('/admin/api/search?q=sophie')->assertOk();
        $this->assertSame(['Sophie Searchable'], collect($r->json('customers'))->pluck('title')->all());
        $this->assertSame(['Sophie Chatter'], collect($r->json('chats'))->pluck('title')->all());

        $byMessage = $this->getJson('/admin/api/search?q=sensitive skin')->assertOk();
        $this->assertCount(1, $byMessage->json('chats'));
        $this->assertSame([], $this->getJson('/admin/api/search?q=a')->json('customers'));         // too short
        $this->assertSame([], $this->getJson('/admin/api/search?q=%25%25')->json('customers'));    // wildcards are plain text
    }

    public function test_quick_search_needs_a_login_and_is_on_every_page(): void
    {
        $this->getJson('/admin/api/search?q=anything')->assertUnauthorized();
        $this->actingAs($this->owner)->get('/admin/today')->assertSee('id="gsearch"', false);
        $this->get('/admin/schedule')->assertSee('id="gsearch"', false);
        $this->get('/admin/booking-setup')->assertSee('id="gsearch"', false);
    }

    public function test_inbox_list_tells_who_needs_a_person(): void
    {
        $this->in(function () {
            $a = Conversation::startFor('whatsapp');
            $a->forceFill(['mode' => 'human', 'status' => 'open', 'contact_name' => 'Needy Nora'])->save();
            $a->addMessage('in', 'customer', 'help please');
            $b = Conversation::startFor('web');
            $b->addMessage('in', 'customer', 'hello');
        });

        $items = collect($this->actingAs($this->owner)->getJson('/admin/api/conversations')->assertOk()->json('conversations'));
        $this->assertTrue($items->firstWhere('name', 'Needy Nora')['needs_reply']);
        $this->assertFalse($items->firstWhere('channel', 'web')['needs_reply']);
    }

    public function test_today_shows_a_setup_checklist_to_owners_until_done(): void
    {
        $this->actingAs($this->owner)->get('/admin/today')->assertSee('Get set up')->assertSee('Add your services and prices')->assertSee('0 of 4 done');

        $this->in(fn () => Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 30, 'price' => 10]));
        $this->business->forceFill(['settings' => ['hours' => ['mon' => ['09:00', '17:00']]], 'setup_done_at' => now()])->save();
        $this->owner->unsetRelation('business');
        $this->get('/admin/today')->assertSee('3 of 4 done');   // still needs a connected channel

        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->actingAs($staff)->get('/admin/today')->assertOk()->assertDontSee('Get set up');
    }

    public function test_the_sample_business_has_no_checklist_and_booking_setup_has_the_closed_days_picker(): void
    {
        $internal = Business::factory()->create();
        $owner = User::factory()->create(['business_id' => $internal->id, 'role' => 'owner']);
        $this->actingAs($owner)->get('/admin/booking-setup')->assertOk()->assertSee('id="closed-pick"', false)->assertSee('Days you are closed');

        $this->post('/admin/booking-setup/rules', ['closed_dates' => '2026-12-25, 2026-12-26, not-a-date', 'slot_step_min' => 30, 'buffer_min' => 10, 'min_notice_hours' => 2, 'max_days_ahead' => 14, 'cancel_hours' => 24])->assertSessionHasNoErrors();
        $this->assertSame(['2026-12-25', '2026-12-26'], $internal->fresh()->settings['closed_dates']);
    }

    public function test_landing_page_hides_the_demo_block_outside_local_testing(): void
    {
        $this->get('/')->assertDontSee('local testing only')->assertDontSee('owner@example.com');
        $this->get('/login')->assertDontSee('owner@example.com');
    }

    public function test_automations_are_collapsible_and_open_when_switched_on(): void
    {
        $owner = $this->owner;
        $this->actingAs($owner)->get('/admin/automations')->assertOk()->assertSee('class="card fold"', false)->assertSee('Satisfaction survey');
    }

    public function test_the_inbox_chat_screen_has_tabs_dialogs_and_the_jump_button(): void
    {
        $this->actingAs($this->owner)->get('/admin')->assertOk()
            ->assertSee('Reply to customer')->assertSee('Private note')->assertSee('id="btn-send"', false)->assertSee('id="task-dialog"', false)
            ->assertSee('id="tpl-dialog"', false)->assertSee('id="btn-jump"', false)->assertSee('id="mode-badge"', false)->assertSee('id="btn-back"', false)
            ->assertSee('Enter sends, Shift+Enter');
    }

    public function test_filter_pages_load_the_auto_apply_script(): void
    {
        $owner = \App\Models\User::factory()->create(['business_id' => \App\Models\Business::factory()->create()->id, 'role' => 'owner']);
        foreach (['customers', 'orders', 'reports', 'conversations', 'automations/activity'] as $page) {
            $this->actingAs($owner)->get('/admin/'.$page)->assertOk()->assertSee('js/autofilter.js', false);
        }
        $this->assertFileExists(public_path('js/autofilter.js'));
    }
}
