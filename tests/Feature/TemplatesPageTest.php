<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Business;
use App\Models\Channel;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatesPageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
        $this->manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
    }

    private function in(callable $fn, ?Business $b = null): mixed
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    private function channel(string $reminder = ''): Channel
    {
        return $this->in(fn () => Channel::create(['type' => 'whatsapp', 'name' => 'Front desk', 'external_id' => '1555'.rand(100, 999), 'access_token' => 'x', 'active' => true,
            'settings' => $reminder ? ['reminder_template' => $reminder] : null]));
    }

    private function template(Channel $c, string $name, string $status, string $body = 'Hi {{1}}, see you on {{2}}') : MessageTemplate
    {
        return $this->in(fn () => MessageTemplate::create(['channel_id' => $c->id, 'name' => $name, 'language' => 'en', 'category' => 'UTILITY', 'body' => $body, 'status' => $status]));
    }

    public function test_without_a_whatsapp_number_the_page_guides_to_channels(): void
    {
        $this->actingAs($this->manager)->get('/admin/templates')->assertOk()->assertSee('Connect a WhatsApp number first')->assertDontSee('id="new-tpl"', false);
    }

    public function test_templates_show_plain_status_help_placeholders_counts_and_usage(): void
    {
        $c = $this->channel('booking_reminder');
        $this->template($c, 'booking_reminder', 'APPROVED');
        $this->template($c, 'we_miss_you', 'PENDING', 'Hello {{1}}');
        $this->template($c, 'old_offer', 'REJECTED')->forceFill(['last_error' => 'Looks like an advert'])->save();
        $this->in(fn () => Automation::create(['key' => 'survey', 'title' => 'Satisfaction survey', 'enabled' => true, 'delay_hours' => 2, 'message' => ['en' => 'x'], 'template_name' => 'we_miss_you']));

        $r = $this->actingAs($this->manager)->get('/admin/templates')->assertOk();
        $r->assertSee('Booking reminder')->assertSee('Ready to use.')->assertSee('Meta is reviewing it')->assertSee('Looks like an advert')
            ->assertSee('All (3)')->assertSee('Approved (1)')->assertSee('Waiting (1)')->assertSee('Problem (1)')
            ->assertSee('<span class="ph">1</span>', false)->assertSee('Used by: Booking reminders (Front desk)')->assertSee('Used by: Satisfaction survey')
            ->assertSee('Not used by anything yet')->assertSee('Start from an example')->assertSee('id="new-tpl"', false);
    }

    public function test_other_businesses_templates_stay_hidden_and_staff_are_kept_out(): void
    {
        $other = Business::factory()->create();
        $oc = $this->in(fn () => Channel::create(['type' => 'whatsapp', 'name' => 'Other WA', 'external_id' => '999111', 'access_token' => 'x', 'active' => true]), $other);
        $this->in(fn () => MessageTemplate::create(['channel_id' => $oc->id, 'name' => 'secret_one', 'language' => 'en', 'category' => 'UTILITY', 'body' => 'x', 'status' => 'APPROVED']), $other);
        $this->channel();
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);

        $this->actingAs($this->manager)->get('/admin/templates')->assertOk()->assertDontSee('secret_one');
        $this->actingAs($staff)->get('/admin/templates')->assertForbidden();
    }
}
