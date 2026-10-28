<?php

namespace Tests\Feature;

use App\Models\AutomationLog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Survey;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyTestSendTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'demo']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function customerWithChat(array $attrs = []): Customer
    {
        return $this->in(function () use ($attrs) {
            $c = Customer::create($attrs + ['name' => 'Amy', 'phone' => '0123456789', 'language' => 'en', 'consented_at' => now()]);
            $conv = Conversation::startFor('web');
            $conv->forceFill(['customer_id' => $c->id])->save();

            return $c;
        });
    }

    public function test_run_now_explains_why_nothing_was_sent(): void
    {
        $this->actingAs($this->owner)->post('/admin/automations/run')->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'no automation is switched on'));

        $this->post('/admin/automations/survey', ['enabled' => 1, 'delay_hours' => 2, 'message_en' => 'Rate {service}'])->assertSessionHasNoErrors();
        $this->post('/admin/automations/run')->assertSessionHas('status', fn ($s) => str_contains($s, 'Nobody is due yet') && str_contains($s, 'Send a test'));
    }

    public function test_the_test_button_sends_a_survey_the_customer_can_answer(): void
    {
        $c = $this->customerWithChat();
        $this->actingAs($this->owner)->get('/admin/automations')->assertOk()->assertSee('Send a test now to')->assertSee('Amy');

        $this->post('/admin/automations/survey/test', ['customer_id' => $c->id])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Test survey sent'));

        $this->in(function () use ($c) {
            $survey = Survey::query()->where('customer_id', $c->id)->first();
            $this->assertNotNull($survey);
            $this->assertNull($survey->booking_id);
            $this->assertSame(1, Message::query()->where('direction', 'out')->count());
            $this->assertSame('sent', AutomationLog::query()->where('customer_id', $c->id)->first()->status);
        });
    }

    public function test_the_test_respects_stop_and_missing_consent(): void
    {
        $c = $this->customerWithChat(['consented_at' => null]);
        $this->actingAs($this->owner)->get('/admin/automations')->assertDontSee('Send a test now to');
        $this->post('/admin/automations/survey/test', ['customer_id' => $c->id])->assertSessionHas('status', fn ($s) => str_contains($s, 'not sent') && str_contains($s, 'no consent'));
    }
}
