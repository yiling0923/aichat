<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\StaffNotification;
use App\Models\User;
use App\Services\AutoTagger;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoTagTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'tag-co']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function chat(string $text, ?Conversation $c = null): Conversation
    {
        return app(CurrentBusiness::class)->runAs($this->business, function () use ($text, $c) {
            $c ??= Conversation::create(['channel' => 'web', 'token' => str()->random(20)]);
            $c->addMessage('in', 'customer', $text);

            return $c->fresh();
        });
    }

    private function names(Conversation $c): array
    {
        return app(CurrentBusiness::class)->runAs($this->business, fn () => $c->tags()->pluck('name')->sort()->values()->all());
    }

    public function test_topics_are_found_in_english_and_chinese(): void
    {
        $t = new AutoTagger;
        $this->assertSame(['Price question'], $t->topics('How much is a facial?'));
        $this->assertSame(['Price question'], $t->topics('多少钱'));
        $this->assertSame(['Location'], $t->topics('Is there parking?'));
        $this->assertSame(['Booking help', 'Cancel or change'], $t->topics('I want to cancel and book again'));
        $this->assertSame([], $t->topics('thanks, see you'));
        $this->assertSame([], $t->topics('The paycheck bookmark'));   // whole words only
    }

    public function test_a_customer_message_tags_the_chat_once(): void
    {
        $c = $this->chat('What is the price for a facial?');
        $this->assertSame(['Price question'], $this->names($c));

        $c = $this->chat('And the cost for two people?', $c);
        $this->assertSame(['Price question'], $this->names($c));
        $this->assertSame(1, app(CurrentBusiness::class)->runAs($this->business, fn () => \App\Models\Tag::query()->where('name', 'Price question')->count()));
    }

    public function test_staff_and_bot_messages_do_not_tag(): void
    {
        $c = app(CurrentBusiness::class)->runAs($this->business, function () {
            $c = Conversation::create(['channel' => 'web', 'token' => str()->random(20)]);
            $c->addMessage('out', 'bot', 'Our prices start at RM50. Book now!');

            return $c;
        });
        $this->assertSame([], $this->names($c));
    }

    public function test_it_can_be_switched_off_and_skips_preview_chats(): void
    {
        $this->business->update(['settings' => ['auto_tag' => false]]);
        $this->assertSame([], $this->names($this->chat('how much?')));

        $this->business->update(['settings' => ['auto_tag' => true]]);
        $preview = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::create(['channel' => 'preview', 'token' => str()->random(20)]));
        $this->assertSame([], $this->names($this->chat('how much?', $preview)));
    }

    public function test_price_or_booking_questions_make_a_cold_lead_warm_but_never_lower_hot(): void
    {
        $cold = app(CurrentBusiness::class)->runAs($this->business, fn () => Customer::create(['name' => 'Cold Cat', 'score' => 'cold']));
        $hot = app(CurrentBusiness::class)->runAs($this->business, fn () => Customer::create(['name' => 'Hot Hen', 'score' => 'hot']));
        foreach ([$cold, $hot] as $customer) {
            $c = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::create(['channel' => 'web', 'token' => str()->random(20), 'customer_id' => $customer->id]));
            $this->chat('How much is it?', $c);
        }

        $this->assertSame('warm', $cold->fresh()->score);
        $this->assertSame('hot', $hot->fresh()->score);
    }

    public function test_a_complaint_alerts_owners_and_managers_once(): void
    {
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $c = $this->chat('This is terrible, I want a refund');
        $this->chat('Still waiting, this is unacceptable', $c);

        $this->assertSame(['Complaint'], $this->names($c));
        $this->assertSame(1, StaffNotification::where('user_id', $this->owner->id)->where('type', 'complaint')->count());
        $this->assertSame(0, StaffNotification::where('user_id', $staff->id)->where('type', 'complaint')->count());
    }

    public function test_the_setting_saves_from_the_business_page(): void
    {
        $this->actingAs($this->owner)->get('/admin/settings')->assertOk()->assertSee('Tag chats automatically')->assertSee('name="auto_tag"', false);
        $this->actingAs($this->owner)->post('/admin/settings/smart', [])->assertRedirect();
        $this->assertFalse($this->business->fresh()->settings['auto_tag']);
        $this->actingAs($this->owner)->post('/admin/settings/smart', ['auto_tag' => '1'])->assertRedirect();
        $this->assertTrue($this->business->fresh()->settings['auto_tag']);
    }
}
