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

    /** A quote turned into &quot; inside a script breaks the whole page's buttons (Edit stopped working once). Checked in both languages. */
    public function test_no_page_script_contains_html_escaped_quotes_or_leftover_blade_markers(): void
    {
        $business = Business::factory()->create();
        $owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $customer = app(\App\Support\CurrentBusiness::class)->runAs($business, fn () => \App\Models\Customer::create(['name' => 'Amy', 'phone' => '60111']));

        foreach (['en', 'zh'] as $locale) {
            $owner->forceFill(['locale' => $locale])->save();
            $this->actingAs($owner);

            foreach (array_merge(self::OWNER_PAGES, ['tasks', 'account', 'help', 'customers/'.$customer->id]) as $page) {
                $html = $this->get('/admin/'.$page)->assertOk()->getContent();
                $this->assertStringNotContainsString('__raw_block', $html, $page.' '.$locale);
                preg_match_all('#<script(?![^>]*src=)[^>]*>(.*?)</script>#s', $html, $m);
                foreach ($m[1] as $js) {
                    $this->assertStringNotContainsString('&quot;', $js, 'script on '.$page.' ('.$locale.')');
                }
            }
        }
    }
}
