<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every screen must open for the people who are meant to see it. Catches broken pages before a human does. */
class AdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_PAGES = ['', 'today', 'conversations', 'schedule', 'tasks', 'customers', 'orders', 'tags', 'automations', 'automations/activity',
        'broadcasts', 'reports', 'reports/export', 'manage', 'library', 'ai', 'templates', 'setup', 'billing', 'channels', 'menu', 'booking-setup', 'settings'];

    public function test_every_owner_page_opens_for_an_internal_business_and_a_trial_business(): void
    {
        foreach ([[], ['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(3)]] as $over) {
            $business = Business::factory()->create($over);
            $owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
            $this->actingAs($owner);

            foreach (self::OWNER_PAGES as $page) {
                $this->get('/admin/'.$page)->assertOk();
            }
        }
    }

    public function test_staff_pages_open_and_manager_pages_are_closed_to_staff(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create(['business_id' => $business->id, 'role' => 'staff']);
        $this->actingAs($staff);

        foreach (['', 'today', 'conversations', 'schedule', 'tasks', 'customers', 'orders', 'reports', 'library'] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
        foreach (['manage', 'tags', 'automations', 'broadcasts', 'ai', 'templates', 'setup', 'billing', 'channels', 'menu', 'booking-setup', 'settings'] as $page) {
            $this->get('/admin/'.$page)->assertForbidden();
        }
    }

    public function test_filter_bars_show_chips_counts_and_a_reset_link(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create(['business_id' => $business->id, 'role' => 'staff']);
        $this->actingAs($staff);

        $this->get('/admin/customers')->assertOk()->assertSee('0</b> customers', false)->assertDontSee('>Reset<', false);
        $this->get('/admin/customers?score=hot&q=amy')->assertOk()->assertSee('Lead level: Ready to book')->assertSee('Search: amy')->assertSee('>Reset<', false);
        $this->get('/admin/conversations?status=open&mode=human')->assertOk()->assertSee('Status: Open')->assertSee('Handled by: Staff')->assertSee('>Reset<', false);
        $this->get('/admin/orders?status=paid')->assertOk()->assertSee('>Reset<', false);
        $this->get('/admin/tasks?show=done&mine=1')->assertOk();
    }
}
