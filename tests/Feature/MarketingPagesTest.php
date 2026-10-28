<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private function manager(): User
    {
        $this->business = Business::factory()->create();

        return User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    public function test_broadcast_list_has_summary_new_dialog_and_confirm_dialog(): void
    {
        $u = $this->manager();
        $this->actingAs($u);
        $this->in(fn () => Broadcast::create(['name' => 'Promo', 'message' => ['en' => 'Hi {name}'], 'audience' => [], 'status' => 'draft', 'created_by' => $u->id]));
        $this->get('/admin/broadcasts')->assertOk()
            ->assertSee('New broadcast')->assertSee('bc-dialog', false)->assertSee('send-dialog', false)
            ->assertSee('Drafts')->assertSee('Promo')->assertSee('Customer name');
    }

    public function test_audience_endpoint_counts_only_people_who_agreed(): void
    {
        $this->actingAs($this->manager());
        $this->in(function () {
            Customer::create(['name' => 'A', 'phone' => '0121111111', 'language' => 'en', 'consented_at' => now()]);
            Customer::create(['name' => 'B', 'phone' => '0122222222', 'language' => 'en', 'consented_at' => now(), 'opted_out_at' => now()]);
            Customer::create(['name' => 'C', 'phone' => '0123333333', 'language' => 'en']);
        });
        $this->getJson('/admin/broadcasts/audience')->assertOk()->assertJson(['count' => 1, 'everyone' => 3]);
        $this->getJson('/admin/broadcasts/audience?language=zh')->assertJson(['count' => 0]);
    }

    public function test_audience_endpoint_is_not_open_to_staff(): void
    {
        $b = Business::factory()->create();
        $staff = User::factory()->create(['business_id' => $b->id, 'role' => 'staff']);
        $this->actingAs($staff)->getJson('/admin/broadcasts/audience')->assertForbidden();
    }

    public function test_detail_and_activity_pages_render_with_filters(): void
    {
        $u = $this->manager();
        $this->actingAs($u);
        $b = $this->in(fn () => Broadcast::create(['name' => 'Done', 'message' => ['en' => 'Hello'], 'audience' => [], 'status' => 'sent', 'sent_at' => now(), 'sent_count' => 2, 'created_by' => $u->id]));
        $this->get('/admin/broadcasts/'.$b->id)->assertOk()->assertSee('Who got it')->assertSee('rc-filter', false)->assertSee('All broadcasts');
        $this->get('/admin/automations/activity?status=skipped')->assertOk()->assertSee('Activity log')->assertSee('Result: Skipped')->assertSee('Nothing matches these filters');
    }
}
