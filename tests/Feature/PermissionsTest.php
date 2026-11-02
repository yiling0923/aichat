<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function team(User $user, array $fields): void
    {
        $this->actingAs($this->owner)->post('/admin/settings/team/'.$user->id, $fields + ['role' => $user->role, 'active' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $user->refresh();
    }

    public function test_roles_keep_their_usual_access_until_the_owner_chooses(): void
    {
        $this->assertNull($this->staff->fresh()->permissions);
        foreach (['/admin', '/admin/schedule', '/admin/customers', '/admin/tasks', '/admin/orders', '/admin/reports'] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
        foreach (['/admin/automations', '/admin/broadcasts', '/admin/ai', '/admin/tags', '/admin/menu', '/admin/booking-setup', '/admin/channels', '/admin/settings'] as $url) {
            $this->actingAs($this->staff)->get($url)->assertForbidden();
        }

        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        foreach (['/admin/automations', '/admin/ai', '/admin/tags', '/admin/manage'] as $url) {
            $this->actingAs($manager)->get($url)->assertOk();
        }
        foreach (['/admin/menu', '/admin/booking-setup', '/admin/channels', '/admin/settings'] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
        }
    }

    public function test_the_owner_can_give_and_take_away_areas_for_one_person(): void
    {
        $this->team($this->staff, ['custom' => 1, 'areas' => ['inbox', 'marketing', 'menu', 'tasks']]);
        $this->assertEqualsCanonicalizing(['inbox', 'marketing', 'menu', 'tasks'], $this->staff->fresh()->permissions);

        // Newly allowed.
        $this->actingAs($this->staff)->get('/admin/automations')->assertOk()->assertSee('Marketing');
        $this->actingAs($this->staff)->get('/admin/menu')->assertOk();
        // Taken away (the usual staff access is replaced, not added to).
        $this->actingAs($this->staff)->get('/admin/schedule')->assertForbidden();
        $this->actingAs($this->staff)->get('/admin/orders')->assertForbidden();
        $this->actingAs($this->staff)->get('/admin/reports')->assertForbidden();
        $side = $this->actingAs($this->staff)->get('/admin/tasks')->assertOk()->getContent();
        $this->assertStringContainsString(route('automations'), $side);
        $this->assertStringNotContainsString('href="'.route('schedule').'"', $side);
        $this->assertStringNotContainsString('href="'.route('reports').'"', $side);

        // Back to the role's usual access.
        $this->team($this->staff, []);
        $this->assertNull($this->staff->fresh()->permissions);
        $this->actingAs($this->staff)->get('/admin/schedule')->assertOk();
        $this->actingAs($this->staff)->get('/admin/automations')->assertForbidden();
    }

    public function test_owner_only_areas_cannot_be_given_and_staff_cannot_change_access(): void
    {
        $this->team($this->staff, ['custom' => 1, 'areas' => ['inbox', 'channels', 'settings', 'billing', 'backup', 'nonsense']]);
        $this->assertSame(['inbox'], $this->staff->fresh()->permissions);
        foreach (['/admin/channels', '/admin/settings', '/admin/billing', '/admin/backup'] as $url) {
            $this->actingAs($this->staff)->get($url)->assertForbidden();
        }

        $this->actingAs($this->staff)->post('/admin/settings/team/'.$this->staff->id, ['role' => 'staff', 'active' => 1, 'custom' => 1, 'areas' => ['marketing']])->assertForbidden();
        $this->assertSame(['inbox'], $this->staff->fresh()->permissions);
    }

    public function test_a_person_with_no_areas_still_gets_today_alerts_and_their_account(): void
    {
        $this->team($this->staff, ['custom' => 1]);
        $this->assertSame([], $this->staff->fresh()->permissions);

        foreach (['/admin/today', '/admin/help', '/admin/account', '/admin/library'] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
        $this->actingAs($this->staff)->getJson('/admin/api/notifications')->assertOk();
        $this->actingAs($this->staff)->get('/admin')->assertForbidden();
        $this->actingAs($this->staff)->getJson('/admin/api/conversations')->assertForbidden()->assertJsonStructure(['message']);
        $this->actingAs($this->staff)->get('/admin/customers')->assertForbidden();
    }

    public function test_changing_a_role_to_owner_clears_the_choice_and_owners_always_have_everything(): void
    {
        $this->team($this->staff, ['custom' => 1, 'areas' => ['inbox']]);
        $this->team($this->staff, ['role' => 'owner', 'custom' => 1, 'areas' => ['inbox']]);
        $this->assertNull($this->staff->fresh()->permissions);
        $this->assertSame('owner', $this->staff->fresh()->role);
        $this->assertTrue($this->staff->fresh()->allows('booking'));
    }

    public function test_new_team_members_can_be_given_chosen_areas_from_the_start(): void
    {
        $this->actingAs($this->owner)->post('/admin/settings/team', ['name' => 'Nora New', 'email' => 'nora@example.com', 'password' => 'secret-pass-1', 'role' => 'staff', 'custom' => 1, 'areas' => ['schedule', 'customers']])->assertSessionHasNoErrors();
        $nora = User::where('email', 'nora@example.com')->first();
        $this->assertEqualsCanonicalizing(['schedule', 'customers'], $nora->permissions);
        $this->assertNotNull($nora->email_verified_at);          // the owner vouches for them: no "confirm your email" detour
        $this->actingAs($nora)->get('/admin/schedule')->assertOk();
        $this->actingAs($nora)->get('/admin/tasks')->assertForbidden();
    }

    public function test_the_team_page_offers_the_access_boxes(): void
    {
        $this->actingAs($this->owner)->get('/admin/settings?tab=team')->assertOk()
            ->assertSee('Choose exactly what this person can use')->assertSee('Menu builder')->assertSee('Booking setup')->assertSee('data-custom="0"', false);
    }
}
