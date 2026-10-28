<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayPageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    private function owner(): User
    {
        $this->business = Business::factory()->create(['timezone' => 'UTC', 'plan' => null]);

        return User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner', 'name' => 'Sarah Lim']);
    }

    public function test_quiet_day_shows_greeting_actions_and_friendly_empty_states(): void
    {
        $u = $this->owner();
        $this->actingAs($u)->get('/admin/today')->assertOk()
            ->assertSee('Sarah')->assertSee('Open inbox')->assertSee('Copy chat link')->assertSee('A quiet day so far')
            ->assertSee('The bot is handling everything')->assertSee('No bookings today')->assertSee('This week');
    }

    public function test_busy_day_shows_waiting_chats_timeline_tasks_and_sales(): void
    {
        $u = $this->owner();
        $this->in(function () use ($u) {
            $c = Customer::create(['name' => 'Mia Wong', 'phone' => '0121234567']);
            $p = Practitioner::create(['name' => 'Dr Mei']);
            $s = Service::create(['name' => ['en' => 'Facial', 'zh' => '护理'], 'duration_min' => 60, 'price' => 100]);
            Booking::create(['customer_id' => $c->id, 'service_id' => $s->id, 'practitioner_id' => $p->id, 'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3), 'status' => 'confirmed']);
            $conv = Conversation::startFor('web');
            $conv->forceFill(['customer_id' => $c->id, 'mode' => 'human', 'handover_reason' => 'Asked about a refund', 'handover_at' => now()->subMinutes(10)])->save();
            Task::create(['title' => 'Call Mia back', 'due_at' => now()->subDay(), 'assigned_to' => $u->id]);
            Order::create(['customer_id' => $c->id, 'amount' => 250, 'status' => 'paid', 'ordered_at' => now()]);
        });

        $this->actingAs($u)->get('/admin/today')->assertOk()
            ->assertSee('1 chat needs you')->assertSee('Asked about a refund')->assertSee('Reply')
            ->assertSee('Mia Wong')->assertSee('Facial')->assertSee('Call Mia back')->assertSee('Overdue')->assertSee('250');
    }
}
