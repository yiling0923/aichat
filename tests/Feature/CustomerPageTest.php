<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['timezone' => 'Asia/Kuala_Lumpur', 'settings' => ['currency' => 'RM']]);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function in(callable $fn, ?Business $b = null): mixed
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    public function test_the_page_shows_profile_stats_timeline_followups_and_actions(): void
    {
        [$c, $chat] = $this->in(function () {
            $c = Customer::create(['name' => 'Amy Lim', 'phone' => '+60 12-345 6789', 'email' => 'amy@example.com', 'language' => 'en', 'score' => 'hot', 'interest' => 'Facial', 'notes' => 'Prefers mornings', 'consented_at' => now()]);
            $tag = Tag::create(['name' => 'VIP', 'type' => 'customer', 'color' => '#b45309']);
            $c->tags()->attach($tag->id);
            $chat = Conversation::startFor('whatsapp');
            $chat->forceFill(['customer_id' => $c->id])->save();
            $p = Practitioner::create(['name' => 'Dr Mei']);
            $s = Service::create(['name' => ['en' => 'Hydrating Facial'], 'duration_min' => 60, 'price' => 188]);
            Booking::create(['customer_id' => $c->id, 'service_id' => $s->id, 'practitioner_id' => $p->id, 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(10)->addHour(), 'status' => 'completed']);
            Booking::create(['customer_id' => $c->id, 'service_id' => $s->id, 'practitioner_id' => $p->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'status' => 'confirmed']);
            Order::create(['customer_id' => $c->id, 'amount' => 540, 'status' => 'paid', 'items' => 'Facial package', 'ordered_at' => now()->subDays(9)]);
            Order::create(['customer_id' => $c->id, 'amount' => 60, 'status' => 'pending', 'ordered_at' => now()]);
            Survey::create(['customer_id' => $c->id, 'rating' => 5, 'sent_at' => now(), 'answered_at' => now()->subDays(9)]);
            Task::create(['customer_id' => $c->id, 'title' => 'Call about packages']);

            return [$c, $chat];
        });

        $r = $this->actingAs($this->staff)->get('/admin/customers/'.$c->id)->assertOk();
        $r->assertSee('Amy Lim')->assertSee('amy@example.com')->assertSee('Ready to book')->assertSee('VIP')->assertSee('Can receive promotions')
            ->assertSee('RM 540.00')->assertSee('1 paid order')->assertSee('5.0 / 5')->assertSee('Hydrating Facial (completed)')->assertSee('Hydrating Facial (confirmed)')
            ->assertSee('Upcoming · ')->assertSee('Order 540.00 (paid)')->assertSee('Rated the visit 5 out of 5')->assertSee('WhatsApp chat')->assertSee('Became a customer')
            ->assertSee('Call about packages')->assertSee('Prefers mornings')->assertSee('Asked about')
            ->assertSee('href="https://wa.me/60123456789"', false)->assertSee(route('inbox', ['c' => $chat->id]), false)->assertSee('Open chat')
            ->assertDontSee('Delete their data');   // staff cannot erase
    }

    public function test_managers_see_the_data_menu_and_an_empty_customer_still_renders(): void
    {
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $c = $this->in(fn () => Customer::create(['language' => 'en']));

        $this->actingAs($manager)->get('/admin/customers/'.$c->id)->assertOk()->assertSee('No name yet')->assertSee('Download their data')->assertSee('Delete their data')
            ->assertSee('none coming up')->assertSee('no visit yet')->assertSee('Nothing to follow up.')->assertSee('No notes yet.')->assertDontSee('Open chat')->assertDontSee('wa.me');
    }

    public function test_editing_still_saves_everything_including_tags_and_other_businesses_are_closed(): void
    {
        $tag = $this->in(fn () => Tag::create(['name' => 'Regular', 'type' => 'customer', 'color' => '#2563eb']));
        $c = $this->in(fn () => Customer::create(['name' => 'Old', 'language' => 'en']));
        $other = Business::factory()->create();
        $theirs = $this->in(fn () => Customer::create(['name' => 'Elsewhere', 'language' => 'en']), $other);
        $this->actingAs($this->staff);

        $this->post('/admin/customers/'.$c->id, ['name' => 'New Name', 'phone' => '601', 'email' => 'n@example.com', 'language' => 'zh', 'score' => 'warm', 'notes' => 'Updated', 'tags' => [$tag->id]])->assertRedirect();
        $c->refresh();
        $this->assertSame(['New Name', 'zh', 'warm', 'Updated'], [$c->name, $c->language, $c->score, $c->notes]);
        $this->assertSame([$tag->id], $c->tags()->pluck('tags.id')->all());

        $this->get('/admin/customers/'.$c->id)->assertSee('id="edit-dialog"', false)->assertSee('New Name');
        $this->get('/admin/customers/'.$theirs->id)->assertNotFound();
    }
}
