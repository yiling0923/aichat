<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['name' => 'Glow', 'timezone' => 'Asia/Kuala_Lumpur', 'plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(5)]);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner', 'name' => 'Olive Owner']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff', 'name' => 'Sam Staff']);
    }

    public function test_the_page_has_business_and_team_tabs_roles_help_and_plan_usage(): void
    {
        $r = $this->actingAs($this->owner)->get('/admin/settings')->assertOk();
        $r->assertSee('Business and team')->assertSee('Team (2)')->assertSee('2 of 2')->assertSee('team members on your plan')->assertSee('What each role can do')
            ->assertSee('Olive Owner')->assertSee('(you)')->assertSee('Sam Staff')->assertSee('Add a team member')->assertSee('Currency shown with prices')
            ->assertSee('<optgroup label="Common">', false)->assertSee('<optgroup label="Europe">', false)->assertSee('/chat/'.$this->business->slug, false);
        $this->get('/admin/settings?tab=team')->assertOk();
    }

    public function test_profile_saves_address_currency_and_accent_and_still_works_without_the_new_fields(): void
    {
        $this->actingAs($this->owner)->post('/admin/settings/profile', ['name' => 'Glow Clinic', 'timezone' => 'Europe/Paris', 'accent' => '#2563eb', 'address' => '1 Test Road', 'currency' => 'S$'])->assertRedirect();
        $b = $this->business->fresh();
        $this->assertSame(['Glow Clinic', 'Europe/Paris', '#2563eb', '1 Test Road', 'S$'], [$b->name, $b->timezone, $b->settings['accent'], $b->settings['address'], $b->settings['currency']]);

        $this->post('/admin/settings/profile', ['name' => 'Glow Again', 'timezone' => 'Europe/Paris', 'accent' => '#2563eb'])->assertRedirect();
        $this->assertSame(['1 Test Road', 'S$'], [$this->business->fresh()->settings['address'], $this->business->fresh()->settings['currency']]);   // kept

        $this->post('/admin/settings/profile', ['name' => 'X', 'timezone' => 'Not/AZone'])->assertSessionHasErrors('timezone');
        $this->get('/admin/settings')->assertSee('Europe/Paris', false)->assertSee('S$');
    }

    public function test_staff_cannot_open_settings_but_everyone_can_open_and_use_my_account(): void
    {
        $this->actingAs($this->staff)->get('/admin/settings')->assertForbidden();
        $this->get('/admin/account')->assertOk()->assertSee('My account')->assertSee($this->staff->email);
        $this->get('/admin/today')->assertSee(route('account'), false);   // the name in the corner links to it
    }

    public function test_changing_my_name_and_password_needs_the_current_password(): void
    {
        $this->actingAs($this->staff);

        $this->post('/admin/account', ['name' => 'Samuel Staff'])->assertRedirect();
        $this->assertSame('Samuel Staff', $this->staff->fresh()->name);
        $this->assertTrue(Hash::check('password', $this->staff->fresh()->password));   // untouched

        $this->post('/admin/account', ['name' => 'Samuel Staff', 'current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertSessionHasErrors('current_password');
        $this->post('/admin/account', ['name' => 'Samuel Staff', 'current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/admin/account', ['name' => 'Samuel Staff', 'current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $this->staff->fresh()->password));

        $this->post('/admin/account', ['name' => 'Samuel Staff', 'current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertSessionHas('status');
        $this->assertTrue(Hash::check('brand-new-pass', $this->staff->fresh()->password));

        auth()->logout();
        $this->get('/admin/account')->assertRedirect(route('login'));
    }

    public function test_team_editing_still_works_and_cannot_cross_businesses(): void
    {
        $other = User::factory()->create(['business_id' => Business::factory()->create()->id, 'role' => 'staff']);
        $this->actingAs($this->owner);

        $this->post('/admin/settings/team/'.$this->staff->id, ['role' => 'manager', 'active' => 1, 'password' => ''])->assertRedirect();
        $this->assertSame('manager', $this->staff->fresh()->role);
        $this->post('/admin/settings/team/'.$other->id, ['role' => 'owner', 'active' => 1])->assertNotFound();
        $this->assertSame('staff', $other->fresh()->role);
    }

    public function test_a_warning_shows_while_the_automatic_jobs_are_not_running(): void
    {
        \Illuminate\Support\Facades\Cache::forget('scheduler-heartbeat');
        $this->actingAs($this->owner)->get('/admin/today')->assertSee('Automatic jobs are not running.')->assertSee('setup-scheduler.bat');

        \Illuminate\Support\Facades\Cache::put('scheduler-heartbeat', now(), 600);
        $this->actingAs($this->owner)->get('/admin/today')->assertDontSee('Automatic jobs are not running.');
    }
}
