<?php

namespace Tests\Feature;

use App\Booking\Bookings;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\StaffNotification;
use App\Models\User;
use App\Services\Notifier;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'notify-co', 'timezone' => 'Asia/Kuala_Lumpur']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function say(string $text, ?string $token = null)
    {
        return $this->postJson('/api/chat/notify-co/messages', ['text' => $text, 'token' => $token]);
    }

    public function test_a_handover_rings_the_bell_of_every_active_staff_member_but_not_other_businesses(): void
    {
        $other = Business::factory()->create();
        $stranger = User::factory()->create(['business_id' => $other->id, 'role' => 'owner']);
        User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff', 'active' => false]);

        $this->say('human')->assertOk();

        $this->assertSame(1, StaffNotification::where('user_id', $this->owner->id)->where('type', 'handover')->count());
        $this->assertSame(1, StaffNotification::where('user_id', $this->staff->id)->where('type', 'handover')->count());
        $this->assertSame(2, StaffNotification::count());   // not the switched-off user, not the other business
        $this->assertSame(0, StaffNotification::where('user_id', $stranger->id)->count());
    }

    public function test_more_messages_in_a_staff_chat_update_one_notification_instead_of_piling_up(): void
    {
        $token = $this->say('human')->json('token');
        $this->say('hello?', $token);
        $this->say('anyone there?', $token);

        $mine = StaffNotification::where('user_id', $this->owner->id)->where('type', 'message')->get();
        $this->assertCount(1, $mine);
        $this->assertSame('anyone there?', $mine->first()->body);

        // Once read, the next message rings again.
        $mine->first()->forceFill(['read_at' => now()])->save();
        $this->say('still waiting', $token);
        $this->assertSame(2, StaffNotification::where('user_id', $this->owner->id)->where('type', 'message')->count());
    }

    public function test_a_booking_rings_the_bell(): void
    {
        app(CurrentBusiness::class)->runAs($this->business, function () {
            $chat = Conversation::startFor('web');
            $p = Practitioner::create(['name' => 'Dr A']);
            $s = Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 30, 'price' => 10]);
            $s->practitioners()->sync([$p->id]);
            app(Bookings::class)->book($chat, $s, $p, now('Asia/Kuala_Lumpur')->addDays(2)->setTime(11, 0), ['name' => 'Nia', 'phone' => '60111222333']);
        });

        $n = StaffNotification::where('user_id', $this->staff->id)->where('type', 'booking')->first();
        $this->assertSame('New booking: Nia', $n->title);
        $this->assertStringContainsString('Facial', $n->body);
        $this->assertStringContainsString('/admin/schedule', $n->url);
    }

    public function test_the_bell_lists_only_my_notifications_and_marks_them_read(): void
    {
        $mine = StaffNotification::create(['business_id' => $this->business->id, 'user_id' => $this->owner->id, 'type' => 'task', 'title' => 'Mine', 'url' => '/admin/tasks']);
        $theirs = StaffNotification::create(['business_id' => $this->business->id, 'user_id' => $this->staff->id, 'type' => 'task', 'title' => 'Theirs']);

        $this->getJson('/admin/api/notifications')->assertUnauthorized();

        $r = $this->actingAs($this->owner)->getJson('/admin/api/notifications')->assertOk();
        $this->assertSame(1, $r->json('unread'));
        $this->assertSame(['Mine'], collect($r->json('items'))->pluck('title')->all());

        $this->postJson('/admin/api/notifications/'.$theirs->id.'/read')->assertOk();
        $this->assertNull($theirs->fresh()->read_at);                         // cannot touch someone else's
        $this->postJson('/admin/api/notifications/'.$mine->id.'/read')->assertOk();
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertSame(0, $this->getJson('/admin/api/notifications')->json('unread'));

        StaffNotification::create(['business_id' => $this->business->id, 'user_id' => $this->owner->id, 'type' => 'task', 'title' => 'Another']);
        StaffNotification::create(['business_id' => $this->business->id, 'user_id' => $this->owner->id, 'type' => 'task', 'title' => 'And more']);
        $this->postJson('/admin/api/notifications/read-all')->assertOk();
        $this->assertSame(0, StaffNotification::where('user_id', $this->owner->id)->whereNull('read_at')->count());
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_opening_one_notification_clears_the_others_about_the_same_chat(): void
    {
        $token = $this->say('human')->json('token');
        $this->say('hello?', $token);
        $this->assertSame(2, StaffNotification::where('user_id', $this->owner->id)->whereNull('read_at')->count());

        $first = StaffNotification::where('user_id', $this->owner->id)->where('type', 'handover')->first();
        $this->actingAs($this->owner)->postJson('/admin/api/notifications/'.$first->id.'/read')->assertOk();

        $this->assertSame(0, StaffNotification::where('user_id', $this->owner->id)->whereNull('read_at')->count());
        $this->assertSame(2, StaffNotification::where('user_id', $this->staff->id)->whereNull('read_at')->count());   // each person has their own
    }

    public function test_assigning_a_task_notifies_only_the_assignee_and_the_bell_is_on_the_pages(): void
    {
        $this->actingAs($this->owner)->post('/admin/tasks', ['title' => 'Call Amy', 'assigned_to' => $this->staff->id])->assertRedirect();
        $this->assertSame(1, StaffNotification::where('user_id', $this->staff->id)->where('type', 'task')->count());
        $this->assertSame(0, StaffNotification::where('user_id', $this->owner->id)->count());

        $this->post('/admin/tasks', ['title' => 'Own task', 'assigned_to' => $this->owner->id]);
        $this->assertSame(0, StaffNotification::where('user_id', $this->owner->id)->count());   // no alert for what you assigned yourself

        $this->get('/admin/today')->assertSee('id="bell"', false);
        $this->get('/admin')->assertSee('id="bell"', false);
    }

    public function test_a_notification_problem_never_breaks_the_caller(): void
    {
        $ghost = new Business(['name' => 'Ghost']);   // not saved: the query will fail or find nobody
        app(Notifier::class)->notify($ghost, 'message', 'x');
        $this->assertTrue(true);
    }

    public function test_bell_has_a_sound_switch(): void
    {
        $owner = User::factory()->create(['business_id' => Business::factory()->create()->id, 'role' => 'owner']);
        $this->actingAs($owner)->get('/admin/today')->assertOk()->assertSee('bell-sound', false)->assertSee('Sound is on');
    }
}
