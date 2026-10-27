<?php

namespace Tests\Feature;

use App\Bot\BusinessHours;
use App\Bot\DefaultFlow;
use App\Bot\FlowRepository;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'demo']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    public function test_only_the_owner_can_use_the_menu_builder(): void
    {
        $this->get('/admin/menu')->assertRedirect(route('login'));
        $this->actingAs($this->staff)->get('/admin/menu')->assertForbidden();
        $this->actingAs($this->staff)->getJson('/admin/api/menu')->assertForbidden();
        $this->actingAs($this->owner)->get('/admin/menu')->assertOk()->assertSee('Menu builder');
    }

    public function test_owner_loads_the_sample_menu_with_no_problems(): void
    {
        $r = $this->actingAs($this->owner)->getJson('/admin/api/menu')->assertOk();
        $this->assertSame('language', $r->json('flow.start'));
        $this->assertSame([], $r->json('errors'));
        $this->assertFalse($r->json('unpublished'));
    }

    public function test_editing_the_draft_does_not_change_the_live_menu_until_publish(): void
    {
        $flow = DefaultFlow::make();
        $flow['nodes']['main']['text']['en'] = 'What can we do for you?';

        $this->actingAs($this->owner)->putJson('/admin/api/menu', ['flow' => $flow])
            ->assertOk()->assertJsonPath('unpublished', true);

        $token = $this->postJson(route('chat.send', 'demo'), ['text' => 'hi'])->json('token');
        $r = $this->postJson(route('chat.send', 'demo'), ['text' => 'x', 'option_id' => 'lang_en', 'token' => $token]);
        $this->assertStringContainsString('How can I help', $r->json('messages.1.text'));

        $this->actingAs($this->owner)->postJson('/admin/api/menu/publish')->assertOk()->assertJsonPath('unpublished', false);

        $token = $this->postJson(route('chat.send', 'demo'), ['text' => 'hi'])->json('token');
        $r = $this->postJson(route('chat.send', 'demo'), ['text' => 'x', 'option_id' => 'lang_en', 'token' => $token]);
        $this->assertStringContainsString('What can we do for you?', $r->json('messages.1.text'));
    }

    public function test_a_broken_menu_cannot_be_published(): void
    {
        $flow = DefaultFlow::make();
        $flow['nodes']['main']['options'][0]['goto'] = 'does_not_exist';

        $this->actingAs($this->owner)->putJson('/admin/api/menu', ['flow' => $flow])->assertOk();
        $r = $this->actingAs($this->owner)->postJson('/admin/api/menu/publish')->assertStatus(422);
        $this->assertNotEmpty($r->json('errors'));

        $live = app(CurrentBusiness::class)->runAs($this->business, fn () => app(FlowRepository::class)->published($this->business));
        $this->assertSame('prices', $live->node('main')['options'][0]['goto']);
    }

    public function test_unknown_keys_are_dropped_when_saving(): void
    {
        $flow = DefaultFlow::make();
        $flow['nodes']['main']['evil'] = '<script>alert(1)</script>';
        $flow['hack'] = 'x';

        $r = $this->actingAs($this->owner)->putJson('/admin/api/menu', ['flow' => $flow])->assertOk();
        $this->assertArrayNotHasKey('evil', $r->json('flow.nodes.main'));
        $this->assertArrayNotHasKey('hack', $r->json('flow'));
    }

    public function test_reset_restores_the_sample_draft(): void
    {
        $this->actingAs($this->owner)->putJson('/admin/api/menu', ['flow' => ['start' => 'x', 'languages' => ['en'], 'nodes' => []]])->assertOk();
        $r = $this->actingAs($this->owner)->postJson('/admin/api/menu/reset')->assertOk();
        $this->assertSame('language', $r->json('flow.start'));
    }

    public function test_businesses_have_separate_menus(): void
    {
        $other = Business::factory()->create(['slug' => 'other']);
        $otherOwner = User::factory()->create(['business_id' => $other->id, 'role' => 'owner']);

        $flow = DefaultFlow::make();
        $flow['nodes']['main']['text']['en'] = 'Other shop menu';
        $this->actingAs($otherOwner)->putJson('/admin/api/menu', ['flow' => $flow])->assertOk();

        $r = $this->actingAs($this->owner)->getJson('/admin/api/menu')->assertOk();
        $this->assertSame('How can I help you today?', $r->json('flow.nodes.main.text.en'));
    }

    public function test_preview_uses_the_draft_and_saves_no_lead_or_handover(): void
    {
        $flow = DefaultFlow::make();
        $flow['nodes']['main']['text']['en'] = 'Draft menu text';
        $this->actingAs($this->owner)->putJson('/admin/api/menu', ['flow' => $flow]);

        $call = fn (array $body) => $this->actingAs($this->owner)->postJson('/admin/api/menu/preview', $body);
        $call(['text' => 'hi', 'restart' => true])->assertOk();
        $r = $call(['text' => 'x', 'option_id' => 'lang_en']);
        $this->assertStringContainsString('Draft menu text', $r->json('messages.0.text'));

        foreach (['book', 'facial'] as $opt) {
            $call(['text' => 'x', 'option_id' => $opt]);
        }
        $call(['text' => 'Amy']);
        $call(['text' => '0123456789']);
        $call(['text' => 'Sat']);
        $call(['text' => 'x', 'option_id' => 'staff']);

        app(CurrentBusiness::class)->runAs($this->business, function () {
            $this->assertSame(0, Customer::count());
            $this->assertSame(0, Conversation::query()->where('mode', 'human')->count());
        });
        $this->actingAs($this->owner)->getJson('/admin/api/conversations')->assertJsonCount(0, 'conversations');
    }

    public function test_customers_page_lists_only_this_business(): void
    {
        $other = Business::factory()->create(['slug' => 'other']);
        app(CurrentBusiness::class)->runAs($this->business, fn () => Customer::create(['name' => 'Amy Tan', 'phone' => '0123']));
        app(CurrentBusiness::class)->runAs($other, fn () => Customer::create(['name' => 'Secret Person', 'phone' => '999']));

        $this->actingAs($this->staff)->get('/admin/customers')->assertOk()->assertSee('Amy Tan')->assertDontSee('Secret Person');
        $this->actingAs($this->staff)->get('/admin/customers?q=0123')->assertSee('Amy Tan');
        $this->actingAs($this->staff)->get('/admin/customers?q=zzz')->assertDontSee('Amy Tan');
    }

    public function test_guests_cannot_open_the_customers_page(): void
    {
        $this->get('/admin/customers')->assertRedirect(route('login'));
    }

    public function test_business_hours(): void
    {
        $b = Business::factory()->make([
            'timezone' => 'Asia/Kuala_Lumpur',
            'settings' => ['hours' => ['mon' => ['10:00', '19:00']], 'closed_dates' => ['2026-11-09']],
        ]);

        // Monday 2026-11-02, 11:00 in Kuala Lumpur = 03:00 UTC.
        $this->assertTrue(BusinessHours::isOpen($b, Carbon::parse('2026-11-02 03:00', 'UTC')));
        $this->assertFalse(BusinessHours::isOpen($b, Carbon::parse('2026-11-02 12:00', 'UTC')));  // 20:00 local
        $this->assertFalse(BusinessHours::isOpen($b, Carbon::parse('2026-11-03 03:00', 'UTC')));  // Tuesday: closed
        $this->assertFalse(BusinessHours::isOpen($b, Carbon::parse('2026-11-09 03:00', 'UTC')));  // closed date

        $b->settings = [];
        $this->assertTrue(BusinessHours::isOpen($b));
    }

    public function test_after_hours_handover_says_we_are_closed(): void
    {
        $this->business->update(['settings' => ['hours' => ['mon' => ['10:00', '11:00']]]]);
        Carbon::setTestNow('2026-11-03 03:00:00'); // Tuesday

        $token = $this->postJson(route('chat.send', 'demo'), ['text' => 'hi'])->json('token');
        $this->postJson(route('chat.send', 'demo'), ['text' => 'x', 'option_id' => 'lang_en', 'token' => $token]);
        $r = $this->postJson(route('chat.send', 'demo'), ['text' => 'x', 'option_id' => 'staff', 'token' => $token]);

        $this->assertStringContainsString('not available right now', $r->json('messages.1.text'));
        Carbon::setTestNow();
    }
}
