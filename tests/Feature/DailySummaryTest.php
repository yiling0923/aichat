<?php

namespace Tests\Feature;

use App\Mail\DailySummaryMail;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\DailySummary;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DailySummaryTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'sum-co', 'timezone' => 'Asia/Kuala_Lumpur', 'settings' => ['currency' => 'RM']]);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    public function test_it_counts_the_day_and_only_this_business(): void
    {
        $other = Business::factory()->create();
        $this->travelTo(now('Asia/Kuala_Lumpur')->setTime(10, 0));

        $this->in(function () {
            $c = Conversation::create(['channel' => 'web', 'token' => 'a1']);
            $c->addMessage('in', 'customer', 'How much is a facial?');
            Customer::create(['name' => 'Amy']);
            Order::create(['reference' => 'O1', 'items' => 'Facial', 'amount' => 120, 'status' => 'paid', 'ordered_at' => now()]);
            Order::create(['reference' => 'O2', 'items' => 'Facial', 'amount' => 80, 'status' => 'pending', 'ordered_at' => now()]);
        });
        app(CurrentBusiness::class)->runAs($other, fn () => Conversation::create(['channel' => 'web', 'token' => 'b1']));

        $s = app(DailySummary::class)->forDay($this->business, now('Asia/Kuala_Lumpur'));

        $this->assertSame(1, $s['new_chats']);
        $this->assertSame(1, $s['new_customers']);
        $this->assertSame(1, $s['orders_paid']);
        $this->assertSame(120.0, $s['revenue']);
        $this->assertSame([['name' => 'Price question', 'count' => 1]], $s['topics']);

        $yesterday = app(DailySummary::class)->forDay($this->business, now('Asia/Kuala_Lumpur')->subDay());
        $this->assertSame(0, $yesterday['new_chats']);
        $this->assertSame(0.0, $yesterday['revenue']);
    }

    public function test_the_today_page_shows_the_summary(): void
    {
        $this->actingAs($this->owner)->get('/admin/today')->assertOk()->assertSee('Daily summary')->assertSee('Yesterday')->assertSee('Today so far')->assertSee('waiting for a person');
    }

    public function test_the_morning_email_goes_only_when_turned_on_and_once_a_day(): void
    {
        Mail::fake();
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);

        $this->artisan('aichat:daily-summary', ['--force' => true])->assertSuccessful();
        Mail::assertNothingSent();

        $this->business->update(['settings' => ['currency' => 'RM', 'daily_email' => true]]);
        $this->artisan('aichat:daily-summary', ['--force' => true])->assertSuccessful();
        Mail::assertSent(DailySummaryMail::class, 2);
        Mail::assertSent(DailySummaryMail::class, fn ($m) => $m->hasTo($manager->email));

        $this->travelTo(now('Asia/Kuala_Lumpur')->setTime(8, 5));
        $this->artisan('aichat:daily-summary')->assertSuccessful();
        Mail::assertSent(DailySummaryMail::class, 2);   // already sent today (forced run recorded it)
    }

    public function test_the_setting_saves_from_the_business_page(): void
    {
        $this->actingAs($this->owner)->get('/admin/settings')->assertOk()->assertSee('Email me yesterday');
        $this->actingAs($this->owner)->post('/admin/settings/smart', ['daily_email' => '1', 'auto_tag' => '1'])->assertRedirect();
        $this->assertTrue($this->business->fresh()->settings['daily_email']);
    }
}
