<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Practitioner;
use App\Models\QuickReply;
use App\Models\Service;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $other;

    private User $owner;

    private User $manager;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'demo', 'timezone' => 'Asia/Kuala_Lumpur']);
        $this->other = Business::factory()->create(['slug' => 'other']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function in(callable $fn, ?Business $b = null)
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    private function chat(string $text, ?Business $b = null): Conversation
    {
        return $this->in(function () use ($text) {
            $c = Conversation::startFor('web');
            $c->addMessage('in', 'customer', $text);

            return $c;
        }, $b);
    }

    public function test_every_page_opens_for_the_right_roles(): void
    {
        foreach (['/admin/today', '/admin', '/admin/conversations', '/admin/schedule', '/admin/tasks', '/admin/customers', '/admin/library'] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
        foreach (['/admin/tags', '/admin/menu', '/admin/booking-setup', '/admin/settings'] as $url) {
            $this->actingAs($this->staff)->get($url)->assertForbidden();
        }
        $this->actingAs($this->manager)->get('/admin/tags')->assertOk();
        $this->actingAs($this->manager)->get('/admin/settings')->assertForbidden();
        foreach (['/admin/tags', '/admin/menu', '/admin/booking-setup', '/admin/settings'] as $url) {
            $this->actingAs($this->owner)->get($url)->assertOk();
        }
    }

    public function test_guests_are_sent_to_login_for_new_pages(): void
    {
        foreach (['/admin/today', '/admin/conversations', '/admin/tasks', '/admin/library', '/admin/tags', '/admin/settings'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_today_shows_waiting_chats_bookings_and_tasks(): void
    {
        $c = $this->chat('hello');
        $this->in(fn () => $c->handOverToStaff('Customer chose Talk to staff'));
        $this->in(function () {
            $s = Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 60]);
            $p = Practitioner::create(['name' => 'Dr Mei']);
            Booking::create(['service_id' => $s->id, 'practitioner_id' => $p->id, 'starts_at' => now()->addMinutes(5), 'ends_at' => now()->addHour(), 'status' => 'confirmed']);
            Task::create(['title' => 'Call Amy back']);
        });

        $this->actingAs($this->staff)->get('/admin/today')->assertOk()
            ->assertSee('Customer chose Talk to staff')->assertSee('Facial')->assertSee('Call Amy back');
    }

    public function test_search_finds_text_inside_messages_but_never_private_notes(): void
    {
        $a = $this->chat('Do you have a discount for HIFU?');
        $this->chat('What time do you open?');
        $this->in(fn () => $a->addMessage('out', 'staff', 'secret internal remark', null, true));
        $this->chat('hifu in the other business', $this->other);

        $r = $this->actingAs($this->staff)->get('/admin/conversations?q=hifu')->assertOk();
        $r->assertSee('<mark>HIFU</mark>', false)->assertDontSee('other business')->assertDontSee('What time do you open');

        $this->actingAs($this->staff)->get('/admin/conversations?q=secret')->assertDontSee('secret internal remark');
    }

    public function test_search_treats_percent_signs_literally(): void
    {
        $this->chat('plain text');
        $this->actingAs($this->staff)->get('/admin/conversations?q=%25')->assertOk()->assertSee('No conversations match');
    }

    public function test_conversation_filters_and_preview_chats_are_hidden(): void
    {
        $open = $this->chat('open one');
        $done = $this->chat('resolved one');
        $this->in(fn () => $done->forceFill(['status' => 'resolved'])->save());
        $this->in(fn () => Conversation::startFor('preview')->addMessage('in', 'customer', 'preview chat text'));

        $this->actingAs($this->staff)->get('/admin/conversations?status=resolved&q=one')->assertSee('resolved one')->assertDontSee('open one');
        $this->actingAs($this->staff)->get('/admin/conversations?q=preview')->assertDontSee('preview chat text');
    }

    public function test_tasks_can_be_created_assigned_completed_and_stay_in_the_business(): void
    {
        $foreigner = User::factory()->create(['business_id' => $this->other->id, 'role' => 'staff']);
        $chat = $this->chat('hi');

        $this->actingAs($this->staff)->post('/admin/tasks', ['title' => 'Send quote', 'due_at' => '2026-12-01 10:00', 'assigned_to' => $this->owner->id])->assertRedirect();
        $this->actingAs($this->staff)->post('/admin/tasks', ['title' => 'Sneaky', 'assigned_to' => $foreigner->id])->assertRedirect();
        $this->actingAs($this->staff)->postJson('/admin/tasks', ['title' => 'From chat', 'conversation_id' => $chat->id])->assertOk();

        $this->in(function () use ($chat) {
            $t = Task::query()->where('title', 'Send quote')->first();
            $this->assertSame($this->owner->id, $t->assigned_to);
            // 10:00 Kuala Lumpur is 02:00 UTC.
            $this->assertSame('2026-12-01 02:00:00', $t->due_at->format('Y-m-d H:i:s'));
            $this->assertNull(Task::query()->where('title', 'Sneaky')->first()->assigned_to);
            $this->assertSame($chat->id, Task::query()->where('title', 'From chat')->first()->conversation_id);
        });

        $id = $this->in(fn () => Task::query()->where('title', 'Send quote')->value('id'));
        $this->actingAs($this->staff)->post("/admin/tasks/{$id}/toggle");
        $this->in(fn () => $this->assertNotNull(Task::query()->find($id)->done_at));
        $this->actingAs($this->staff)->get('/admin/tasks?show=done')->assertSee('Send quote');
        $this->actingAs($this->staff)->get('/admin/tasks')->assertDontSee('Send quote');
    }

    public function test_a_task_from_another_business_cannot_be_touched(): void
    {
        $id = $this->in(fn () => Task::create(['title' => 'Theirs'])->id, $this->other);

        $this->actingAs($this->staff)->post("/admin/tasks/{$id}/toggle")->assertNotFound();
        $this->actingAs($this->staff)->post("/admin/tasks/{$id}/delete")->assertNotFound();
        $this->in(fn () => $this->assertNotNull(Task::query()->find($id)), $this->other);
    }

    public function test_tag_management_create_rename_merge_delete(): void
    {
        $as = fn () => $this->actingAs($this->manager);

        $as()->post('/admin/tags', ['name' => 'VIP', 'type' => 'customer', 'color' => '#ff0000'])->assertRedirect();
        $as()->post('/admin/tags', ['name' => 'VIP', 'type' => 'customer'])->assertSessionHasErrors('name');
        $as()->post('/admin/tags', ['name' => 'VIP', 'type' => 'chat'])->assertSessionHasNoErrors(); // same name, other type is fine
        $as()->post('/admin/tags', ['name' => 'Gold', 'type' => 'customer']);

        [$vip, $gold] = $this->in(fn () => [Tag::query()->where('type', 'customer')->where('name', 'VIP')->first(), Tag::query()->where('name', 'Gold')->first()]);
        $cust = $this->in(function () use ($vip, $gold) {
            $c = Customer::create(['name' => 'Amy']);
            $c->tags()->attach([$vip->id, $gold->id]);

            return $c;
        });

        $as()->post("/admin/tags/{$vip->id}", ['name' => 'Gold', 'color' => '#00ff00'])->assertSessionHasErrors('name');
        $as()->post("/admin/tags/{$vip->id}", ['name' => 'Very important', 'color' => '#00ff00'])->assertRedirect();

        $as()->post("/admin/tags/{$vip->id}/merge", ['into' => $gold->id])->assertRedirect();
        $this->in(function () use ($vip, $gold, $cust) {
            $this->assertNull(Tag::query()->find($vip->id));
            $this->assertSame([$gold->id], $cust->tags()->pluck('tags.id')->all());
        });

        $as()->post("/admin/tags/{$gold->id}/delete");
        $this->in(fn () => $this->assertNull(Tag::query()->find($gold->id)));
    }

    public function test_merging_tags_of_different_types_or_businesses_is_refused(): void
    {
        [$a, $b, $theirs] = $this->in(fn () => [Tag::create(['name' => 'A', 'type' => 'customer']), Tag::create(['name' => 'B', 'type' => 'chat']), null]);
        $theirs = $this->in(fn () => Tag::create(['name' => 'T', 'type' => 'customer']), $this->other);

        $this->actingAs($this->manager)->post("/admin/tags/{$a->id}/merge", ['into' => $b->id])->assertNotFound();
        $this->actingAs($this->manager)->post("/admin/tags/{$a->id}/merge", ['into' => $theirs->id])->assertNotFound();
        $this->actingAs($this->manager)->post("/admin/tags/{$theirs->id}/delete")->assertNotFound();
    }

    public function test_customer_list_filters_bulk_tagging_and_isolation(): void
    {
        [$vip] = $this->in(fn () => [Tag::create(['name' => 'VIP', 'type' => 'customer'])]);
        [$amy, $bob] = $this->in(fn () => [
            Customer::create(['name' => 'Amy Tan', 'phone' => '0123', 'score' => 'hot', 'language' => 'en']),
            Customer::create(['name' => 'Bob Lee', 'phone' => '0456', 'score' => 'cold', 'language' => 'zh']),
        ]);
        $this->in(fn () => Customer::create(['name' => 'Secret Person']), $this->other);

        $this->actingAs($this->staff)->get('/admin/customers?score=hot')->assertSee('Amy Tan')->assertDontSee('Bob Lee');
        $this->actingAs($this->staff)->get('/admin/customers?language=zh')->assertSee('Bob Lee')->assertDontSee('Amy Tan');
        $this->actingAs($this->staff)->get('/admin/customers')->assertDontSee('Secret Person');

        $this->actingAs($this->staff)->post('/admin/customers/bulk', ['ids' => [$amy->id, $bob->id], 'tag_id' => $vip->id, 'action' => 'add'])->assertRedirect();
        $this->actingAs($this->staff)->get("/admin/customers?tag={$vip->id}")->assertSee('Amy Tan')->assertSee('Bob Lee');

        $this->actingAs($this->staff)->post('/admin/customers/bulk', ['ids' => [$bob->id], 'tag_id' => $vip->id, 'action' => 'remove']);
        $this->actingAs($this->staff)->get("/admin/customers?tag={$vip->id}")->assertDontSee('Bob Lee');
    }

    public function test_customer_page_edit_and_cross_business_protection(): void
    {
        $amy = $this->in(fn () => Customer::create(['name' => 'Amy Tan', 'phone' => '0123']));
        $theirs = $this->in(fn () => Customer::create(['name' => 'Secret Person']), $this->other);

        $this->actingAs($this->staff)->get("/admin/customers/{$amy->id}")->assertOk()->assertSee('Amy Tan');
        $this->actingAs($this->staff)->get("/admin/customers/{$theirs->id}")->assertNotFound();

        $this->actingAs($this->staff)->post("/admin/customers/{$amy->id}", ['name' => 'Amy T.', 'score' => 'hot', 'notes' => 'Prefers mornings'])->assertRedirect();
        $this->in(function () use ($amy) {
            $c = Customer::query()->find($amy->id);
            $this->assertSame('Amy T.', $c->name);
            $this->assertSame('Prefers mornings', $c->notes);
        });
        $this->actingAs($this->staff)->post("/admin/customers/{$theirs->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->in(fn () => $this->assertSame('Secret Person', Customer::query()->find($theirs->id)->name), $this->other);
    }

    public function test_library_everyone_reads_managers_edit(): void
    {
        $this->actingAs($this->manager)->post('/admin/library', ['title' => 'Hours', 'body_en' => 'We open at 10', 'body_zh' => '十点营业'])->assertRedirect();
        $this->actingAs($this->staff)->post('/admin/library', ['title' => 'Nope', 'body_en' => 'x'])->assertForbidden();

        $this->actingAs($this->staff)->getJson('/admin/api/quick-replies')->assertOk()->assertJsonPath('replies.0.body.zh', '十点营业');

        $this->in(fn () => QuickReply::create(['title' => 'Theirs', 'body' => ['en' => 'x']]), $this->other);
        $this->actingAs($this->staff)->getJson('/admin/api/quick-replies')->assertJsonCount(1, 'replies');

        $id = $this->in(fn () => QuickReply::query()->first()->id);
        $this->actingAs($this->manager)->post("/admin/library/{$id}", ['title' => 'Opening', 'body_en' => 'Open 10am'])->assertRedirect();
        $this->actingAs($this->manager)->post("/admin/library/{$id}/delete");
        $this->in(fn () => $this->assertSame(0, QuickReply::count()));
    }

    public function test_settings_profile_and_team(): void
    {
        $this->actingAs($this->owner)->post('/admin/settings/profile', ['name' => 'New Name', 'timezone' => 'Asia/Singapore', 'accent' => '#336699'])->assertRedirect();
        $b = $this->business->fresh();
        $this->assertSame('New Name', $b->name);
        $this->assertSame('#336699', $b->settings['accent']);
        $this->actingAs($this->owner)->get('/admin/settings')->assertSee('New Name');
        $this->actingAs($this->owner)->get('/admin/today')->assertSee('--teal: #336699', false);

        $this->actingAs($this->owner)->post('/admin/settings/profile', ['name' => 'X', 'timezone' => 'Asia/Singapore', 'accent' => 'red;}</style>'])->assertSessionHasErrors('accent');

        $this->actingAs($this->owner)->post('/admin/settings/team', ['name' => 'New Hire', 'email' => 'hire@example.com', 'password' => 'longenough1', 'role' => 'staff'])->assertRedirect();
        $hire = User::query()->where('email', 'hire@example.com')->first();
        $this->assertSame($this->business->id, $hire->business_id);
        $this->actingAs($this->owner)->post('/admin/settings/team', ['name' => 'Dup', 'email' => 'hire@example.com', 'password' => 'longenough1', 'role' => 'staff'])->assertSessionHasErrors('email');
    }

    public function test_the_last_owner_cannot_be_removed_and_other_businesses_users_are_untouchable(): void
    {
        $this->actingAs($this->owner)->post("/admin/settings/team/{$this->owner->id}", ['role' => 'staff', 'active' => 1])->assertSessionHasErrors('role');
        $this->assertSame('owner', $this->owner->fresh()->role);

        $foreign = User::factory()->create(['business_id' => $this->other->id, 'role' => 'staff']);
        $this->actingAs($this->owner)->post("/admin/settings/team/{$foreign->id}", ['role' => 'owner', 'active' => 1])->assertNotFound();
        $this->assertSame('staff', $foreign->fresh()->role);
    }

    public function test_a_switched_off_account_cannot_sign_in_or_keep_using_the_panel(): void
    {
        $this->actingAs($this->owner)->post("/admin/settings/team/{$this->staff->id}", ['role' => 'staff', 'active' => 0])->assertRedirect();
        $this->assertFalse($this->staff->fresh()->active);

        auth()->logout();
        $this->post('/login', ['email' => $this->staff->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->actingAs($this->staff->fresh())->get('/admin/today')->assertRedirect(route('login'));
    }

    public function test_inbox_tags_assignment_and_customer_details(): void
    {
        $chat = $this->chat('hi');
        $customer = $this->in(fn () => Customer::create(['name' => 'Amy', 'phone' => '0123']));
        $this->in(fn () => $chat->forceFill(['customer_id' => $customer->id])->save());
        $tag = $this->in(fn () => Tag::create(['name' => 'Complaint', 'type' => 'chat']));
        $custTag = $this->in(fn () => Tag::create(['name' => 'VIP', 'type' => 'customer']));
        $foreigner = User::factory()->create(['business_id' => $this->other->id]);

        $this->actingAs($this->staff);
        $this->postJson("/admin/api/conversations/{$chat->id}/tag", ['tag_id' => $tag->id, 'on' => true])->assertOk();
        $this->postJson("/admin/api/conversations/{$chat->id}/tag", ['tag_id' => $custTag->id, 'on' => true])->assertNotFound();
        $this->postJson("/admin/api/conversations/{$chat->id}/assign", ['user_id' => $this->owner->id])->assertOk();
        $this->postJson("/admin/api/conversations/{$chat->id}/assign", ['user_id' => $foreigner->id])->assertNotFound();

        $r = $this->getJson("/admin/api/conversations/{$chat->id}")->assertOk();
        $this->assertSame('Complaint', $r->json('conversation.tags.0.name'));
        $this->assertSame($this->owner->id, $r->json('conversation.assigned_to'));
        $this->assertSame('Amy', $r->json('conversation.customer.name'));
        $this->assertSame(['Complaint'], array_column($r->json('all_tags'), 'name'));
        $this->assertNotContains($foreigner->id, array_column($r->json('team'), 'id'));

        $this->postJson("/admin/api/conversations/{$chat->id}/tag", ['tag_id' => $tag->id, 'on' => false])->assertOk();
        $this->assertSame([], $this->getJson("/admin/api/conversations/{$chat->id}")->json('conversation.tags'));
    }
}
