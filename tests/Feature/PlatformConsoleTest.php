<?php

namespace Tests\Feature;

use App\Billing\Plans;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PlatformAudit;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private Business $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->platform = User::factory()->create(['business_id' => null, 'role' => 'platform_owner', 'email' => 'platform@example.com']);
        $this->shop = Business::factory()->create(['name' => 'Rose Clinic', 'slug' => 'rose', 'plan' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_status' => 'trialing']);
        $this->owner = User::factory()->create(['business_id' => $this->shop->id, 'role' => 'owner', 'email' => 'owner@rose.test']);
    }

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->shop, $fn);
    }

    public function test_businesses_are_shown_side_by_side_with_their_numbers_and_can_be_downloaded(): void
    {
        $this->in(function () {
            $c = Customer::create(['name' => 'Amy', 'phone' => '601', 'language' => 'en']);
            Order::create(['customer_id' => $c->id, 'amount' => 250, 'status' => 'paid', 'ordered_at' => now()->subDay()]);
            \App\Models\Conversation::startFor('whatsapp');
        });

        $this->actingAs($this->platform)->get('/platform')->assertOk()->assertSee('Rose Clinic')->assertSee('Customer money')->assertSee('RM 250')->assertSee('Activity');
        $this->actingAs($this->platform)->get('/platform?days=7')->assertOk()->assertSee('Chats, last 7 days');

        $csv = $this->actingAs($this->platform)->get('/platform/export?days=30')->assertOk()->streamedContent();
        $this->assertStringContainsString('"Rose Clinic",rose,active,trial,trial', $csv);
        $this->assertStringContainsString('250.00,RM', $csv);

        // Nobody but the platform owner gets in.
        $this->actingAs($this->owner)->get('/platform')->assertForbidden();
        $this->actingAs($this->owner)->get('/platform/businesses/'.$this->shop->id)->assertForbidden();
        $this->actingAs($this->owner)->get('/platform/export')->assertForbidden();
    }

    public function test_a_business_page_shows_plan_usage_team_and_activity(): void
    {
        $this->actingAs($this->platform)->post('/platform/businesses/'.$this->shop->id.'/pause', ['reason' => 'Unpaid'])->assertRedirect();
        $this->actingAs($this->platform)->post('/platform/businesses/'.$this->shop->id.'/resume')->assertRedirect();

        $page = $this->actingAs($this->platform)->get('/platform/businesses/'.$this->shop->id)->assertOk()
            ->assertSee('Rose Clinic')->assertSee('owner@rose.test')->assertSee('Usage and limits')->assertSee('View (read-only)')->assertSee('Unpaid')->assertSee('Paused')->assertSee('Resumed');
        $this->assertSame(2, PlatformAudit::query()->where('business_id', $this->shop->id)->count());
    }

    public function test_the_plan_trial_and_paid_until_date_can_be_changed_and_are_logged(): void
    {
        $url = '/platform/businesses/'.$this->shop->id.'/plan';
        $this->actingAs($this->platform)->post($url, ['plan' => 'trial', 'extend_days' => 10])->assertRedirect();
        $this->assertTrue($this->shop->fresh()->trial_ends_at->isSameDay(now()->addDays(15)));

        $this->actingAs($this->platform)->post($url, ['plan' => 'growth', 'date' => now()->addMonth()->format('Y-m-d')])->assertRedirect();
        $b = $this->shop->fresh();
        $this->assertSame('growth', $b->plan);
        $this->assertSame('active', $b->subscription_status);
        $this->assertSame('active', app(Plans::class)->state($b));
        $this->assertSame(3000, app(Plans::class)->limit($b, 'ai'));

        $this->actingAs($this->platform)->post($url, ['plan' => 'internal'])->assertRedirect();
        $this->assertSame('internal', app(Plans::class)->state($this->shop->fresh()));
        $this->actingAs($this->platform)->post($url, ['plan' => 'gold'])->assertSessionHasErrors('plan');

        $this->assertSame(3, PlatformAudit::query()->where('action', 'plan_changed')->count());
        $this->actingAs($this->owner)->post($url, ['plan' => 'growth'])->assertForbidden();
    }

    public function test_one_business_can_have_its_own_limits(): void
    {
        $url = '/platform/businesses/'.$this->shop->id.'/limits';
        $plans = app(Plans::class);
        $this->assertSame(100, $plans->limit($this->shop, 'ai'));

        $this->actingAs($this->platform)->post($url, ['mode' => ['ai' => 'number', 'staff' => 'unlimited', 'channels' => 'default'], 'number' => ['ai' => 250]])->assertRedirect()->assertSessionHasNoErrors();
        $b = $this->shop->fresh();
        $this->assertSame(250, $plans->limit($b, 'ai'));
        $this->assertNull($plans->limit($b, 'staff'));
        $this->assertSame(1, $plans->limit($b, 'channels'));          // untouched: the plan's own limit

        $this->actingAs($this->platform)->post($url, ['mode' => ['ai' => 'number'], 'number' => ['ai' => 'lots']])->assertSessionHasErrors('limits');
        $this->assertSame(250, $plans->limit($this->shop->fresh(), 'ai'));

        $this->actingAs($this->platform)->post($url, ['mode' => ['ai' => 'default']])->assertRedirect();
        $this->assertSame(100, $plans->limit($this->shop->fresh(), 'ai'));
        $this->assertArrayNotHasKey('limit_overrides', (array) $this->shop->fresh()->settings);
        $this->assertSame(['ai: 250, staff: unlimited', 'Back to the plan limits'], PlatformAudit::query()->where('action', 'limits_changed')->orderBy('id')->pluck('details')->all());
    }

    public function test_a_business_and_its_owner_can_be_made_from_the_console(): void
    {
        $this->actingAs($this->platform)->post('/platform/businesses', ['business_name' => 'Lily Salon', 'name' => 'Lily Tan', 'email' => 'lily@salon.test', 'password' => 'secret-pass-9'])->assertRedirect();

        $lily = User::query()->where('email', 'lily@salon.test')->first();
        $this->assertSame('owner', $lily->role);
        $this->assertNotNull($lily->email_verified_at);
        $business = Business::query()->where('name', 'Lily Salon')->first();
        $this->assertSame('trial', $business->plan);
        $this->assertSame($business->id, $lily->business_id);
        $this->assertTrue(PlatformAudit::query()->where('action', 'business_created')->where('business_id', $business->id)->exists());

        $this->actingAs($lily)->get('/admin')->assertOk();                                        // can use it straight away
        $this->actingAs($this->platform)->post('/platform/businesses', ['business_name' => 'Dup', 'name' => 'X', 'email' => 'lily@salon.test', 'password' => 'secret-pass-9'])->assertSessionHasErrors('email');
        $this->actingAs($this->owner)->post('/platform/businesses', ['business_name' => 'Nope', 'name' => 'X', 'email' => 'x@y.test', 'password' => 'secret-pass-9'])->assertForbidden();
    }

    public function test_a_locked_out_person_gets_a_new_password_without_it_being_logged(): void
    {
        $this->actingAs($this->platform)->post('/platform/businesses/'.$this->shop->id.'/password', ['user_id' => $this->owner->id, 'new_password' => 'fresh-pass-77'])->assertRedirect();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('fresh-pass-77', $this->owner->fresh()->password));
        $audit = PlatformAudit::query()->where('action', 'password_set')->first();
        $this->assertSame('owner@rose.test', $audit->details);
        $this->assertStringNotContainsString('fresh-pass-77', json_encode($audit->toArray()));

        $other = User::factory()->create(['business_id' => Business::factory()->create()->id, 'role' => 'staff']);
        $this->actingAs($this->platform)->post('/platform/businesses/'.$this->shop->id.'/password', ['user_id' => $other->id, 'new_password' => 'fresh-pass-77'])->assertNotFound();
    }

    public function test_a_read_only_support_view_shows_everything_changes_nothing_and_is_logged(): void
    {
        $this->actingAs($this->platform)->post('/platform/businesses/'.$this->shop->id.'/support', ['mode' => 'read'])->assertRedirect(route('inbox'));
        $this->assertSame($this->owner->id, auth()->id());
        $this->assertSame('read', session('support.mode'));

        $this->get('/admin')->assertOk()->assertSee('Support view')->assertSee('Return to the platform console');          // the Inbox page has its own layout: the banner must be there too
        $this->get('/admin/tasks')->assertOk()->assertSee('Support view')->assertSee('read-only')->assertSee('Rose Clinic')->assertSee('Return to the platform console');
        $this->postJson('/admin/tasks', ['title' => 'Sneaky'])->assertForbidden();
        $this->post('/admin/settings/team', ['name' => 'X', 'email' => 'x@x.test', 'password' => 'abcdefgh1', 'role' => 'staff'])->assertForbidden();
        $this->assertSame(0, $this->in(fn () => \App\Models\Task::query()->count()));

        $this->post('/admin/support/stop')->assertRedirect(route('platform.show', $this->shop->id));
        $this->assertSame($this->platform->id, auth()->id());
        $this->assertNull(session('support'));
        $this->assertSame(['support_start', 'support_stop'], PlatformAudit::query()->orderBy('id')->pluck('action')->all());
        $this->get('/platform')->assertOk();
    }

    public function test_acting_as_the_owner_can_change_things_and_a_paused_business_can_still_be_opened(): void
    {
        $this->actingAs($this->platform)->post('/platform/businesses/'.$this->shop->id.'/pause', ['reason' => 'Check']);
        $this->actingAs($this->platform)->post('/platform/businesses/'.$this->shop->id.'/support', ['mode' => 'edit'])->assertRedirect();

        $this->get('/admin/tasks')->assertOk()->assertSee('you are acting as the owner');
        $this->post('/admin/tasks', ['title' => 'Call the supplier'])->assertRedirect();
        $this->assertSame(1, $this->in(fn () => \App\Models\Task::query()->count()));
        $this->post('/admin/support/stop')->assertRedirect();
    }

    public function test_support_cannot_be_started_or_stopped_by_the_wrong_people(): void
    {
        $this->actingAs($this->owner)->post('/platform/businesses/'.$this->shop->id.'/support', ['mode' => 'edit'])->assertForbidden();
        $this->actingAs($this->owner)->post('/admin/support/stop')->assertNotFound();           // not in a support session
        $this->assertNull(session('support'));

        $empty = Business::factory()->create();
        $this->actingAs($this->platform)->post('/platform/businesses/'.$empty->id.'/support', ['mode' => 'read'])->assertSessionHasErrors('support');
        $this->assertSame($this->platform->id, auth()->id());
    }
}
