<?php

namespace Tests\Feature;

use App\Marketing\AutomationDefaults;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Business;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $other;

    private Service $service;

    private Practitioner $person;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 08:00:00');
        $this->business = Business::factory()->create(['slug' => 'demo', 'timezone' => 'Asia/Kuala_Lumpur', 'name' => 'Lumiere']);
        $this->other = Business::factory()->create(['slug' => 'other']);
        $this->in(function () {
            $this->person = Practitioner::create(['name' => 'Dr Mei']);
            $this->service = Service::create(['name' => ['en' => 'Facial', 'zh' => '护理'], 'duration_min' => 60]);
            $this->service->practitioners()->sync([$this->person->id]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function in(callable $fn, ?Business $b = null)
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    /** A customer who agreed to be contacted, with a website chat. @return array{0: Customer, 1: Conversation} */
    private function customer(array $attrs = [], ?Business $b = null): array
    {
        return $this->in(function () use ($attrs) {
            $c = Customer::create($attrs + ['name' => 'Amy', 'phone' => '012'.random_int(1000000, 9999999), 'language' => 'en', 'consented_at' => now()->subDays(60)]);
            $chat = Conversation::startFor('web');
            $chat->forceFill(['customer_id' => $c->id])->save();

            return [$c, $chat];
        }, $b);
    }

    private function booking(Customer $c, Conversation $chat, string $status, string $endsAt, ?string $updatedAt = null): Booking
    {
        return $this->in(function () use ($c, $chat, $status, $endsAt, $updatedAt) {
            $end = Carbon::parse($endsAt);
            $b = Booking::create(['customer_id' => $c->id, 'conversation_id' => $chat->id, 'service_id' => $this->service->id, 'practitioner_id' => $this->person->id,
                'starts_at' => $end->copy()->subHour(), 'ends_at' => $end, 'status' => $status]);
            if ($updatedAt) {
                \DB::table('bookings')->where('id', $b->id)->update(['updated_at' => $updatedAt]);
            }

            return $b;
        });
    }

    private function enable(string $key, array $changes = []): void
    {
        $this->in(function () use ($key, $changes) {
            AutomationDefaults::ensure();
            $a = Automation::query()->where('key', $key)->first();
            $a->forceFill(['enabled' => true] + $changes)->save();
        });
    }

    private function runAutomations(?Business $b = null): array
    {
        return app(\App\Marketing\AutomationRunner::class)->runForBusiness($b ?? $this->business);
    }

    private function botMessages(Conversation $chat): array
    {
        return $this->in(fn () => Message::query()->where('conversation_id', $chat->id)->where('sender_type', 'bot')->orderBy('id')->get()->all());
    }

    private function chatSend(Conversation $chat, ?string $text, ?string $option = null)
    {
        return $this->postJson(route('chat.send', 'demo'), ['text' => $text, 'option_id' => $option, 'token' => $chat->token]);
    }

    public function test_every_automation_exists_but_starts_switched_off_and_nothing_is_sent(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 03:00:00');

        $this->runAutomations();

        $this->in(function () {
            $this->assertSame(AutomationDefaults::KEYS, Automation::query()->orderBy('id')->pluck('key')->sort()->values()->all() === [] ? [] : AutomationDefaults::KEYS);
            $this->assertSame(5, Automation::count());
            $this->assertSame(0, Automation::query()->where('enabled', true)->count());
            $this->assertSame(0, AutomationLog::count());
        });
        $this->assertSame([], $this->botMessages($chat));
    }

    public function test_survey_goes_out_after_the_delay_with_rating_buttons_and_only_once(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');          // ended 3 hours ago
        $this->booking($c, $chat, 'completed', '2026-11-10 07:30:00');          // ended 30 minutes ago: too soon
        $this->booking($c, $chat, 'cancelled', '2026-11-10 04:00:00');          // cancelled: no survey
        $this->enable('survey');

        $sent = $this->runAutomations();
        $this->runAutomations();

        $this->assertSame(1, $sent['survey']);
        $messages = $this->botMessages($chat);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Hi Amy, thank you for visiting Lumiere! How was your Facial?', $messages[0]->body);
        $this->assertSame(['rate_5', 'rate_4', 'rate_3', 'rate_2', 'rate_1'], array_column($messages[0]->payload['options'], 'id'));
        $this->in(function () use ($chat) {
            $this->assertSame(1, Survey::count());
            $this->assertNotNull(Conversation::query()->find($chat->id)->state['survey']);
            $this->assertSame(1, AutomationLog::query()->where('automation_key', 'survey')->where('status', 'sent')->count());
        });
    }

    public function test_nobody_is_messaged_without_consent_or_after_stop_or_while_staff_handle_the_chat(): void
    {
        [$optedOut, $chatA] = $this->customer(['opted_out_at' => now()->subDay()]);
        [$noConsent, $chatB] = $this->customer(['consented_at' => null]);
        [$busy, $chatC] = $this->customer();
        foreach ([[$optedOut, $chatA], [$noConsent, $chatB], [$busy, $chatC]] as [$c, $chat]) {
            $this->booking($c, $chat, 'completed', '2026-11-10 04:00:00');
        }
        $this->in(fn () => $chatC->handOverToStaff('test'));
        $this->enable('survey');

        $sent = $this->runAutomations();

        $this->assertSame(0, $sent['survey']);
        foreach ([$chatA, $chatB, $chatC] as $chat) {
            $this->assertSame([], $this->botMessages($chat));
        }
        $reasons = $this->in(fn () => AutomationLog::query()->orderBy('id')->pluck('reason')->all());
        $this->assertSame(['opted out', 'no consent', 'staff are handling the chat'], $reasons);
    }

    public function test_a_survey_rating_is_recorded_and_the_customer_is_thanked(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');
        $this->runAutomations();

        $r = $this->chatSend($chat, 'x', 'rate_5');

        $texts = implode(' | ', array_column($r->json('messages'), 'text'));
        $this->assertStringContainsString('Thank you for your feedback', $texts);
        $this->assertStringContainsString('How can I help', $texts);              // back to the main menu
        $this->in(function () use ($chat) {
            $s = Survey::query()->first();
            $this->assertSame(5, $s->rating);
            $this->assertNotNull($s->answered_at);
            $this->assertArrayNotHasKey('survey', (array) Conversation::query()->find($chat->id)->state);
            $this->assertSame(0, Task::count());
        });
    }

    public function test_a_typed_digit_counts_and_a_second_answer_does_nothing(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');
        $this->runAutomations();

        $this->chatSend($chat, '4');
        $this->chatSend($chat, '1');

        $this->in(fn () => $this->assertSame(4, Survey::query()->first()->rating));
    }

    public function test_a_low_score_creates_a_task_and_alerts_n8n(): void
    {
        config(['services.n8n.webhook_url' => 'http://n8n.test/hook']);
        Http::fake(['n8n.test/*' => Http::response('ok')]);
        [$c, $chat] = $this->customer(['name' => 'Bob']);
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');
        $this->runAutomations();

        $r = $this->chatSend($chat, 'x', 'rate_1');

        $this->assertStringContainsString('A manager will contact you', implode(' ', array_column($r->json('messages'), 'text')));
        $this->in(function () use ($c) {
            $t = Task::query()->first();
            $this->assertStringContainsString('Low survey score (1/5): call Bob', $t->title);
            $this->assertSame($c->id, $t->customer_id);
        });
        $events = Http::recorded()->map(fn ($p) => $p[0]->data()['event'] ?? null)->all();
        $this->assertContains('survey.low_score', $events);
    }

    public function test_a_good_score_sends_the_review_link_when_review_requests_are_on(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');
        $this->enable('review_request', ['settings' => ['review_url' => 'https://g.page/r/lumiere', 'min_rating' => 4]]);
        $this->runAutomations();

        $r = $this->chatSend($chat, 'x', 'rate_4');

        $this->assertStringContainsString('https://g.page/r/lumiere', implode(' ', array_column($r->json('messages'), 'text')));
        $this->in(function () {
            $this->assertNotNull(Survey::query()->first()->review_sent_at);
            $this->assertSame(1, AutomationLog::query()->where('automation_key', 'review_request')->where('status', 'sent')->count());
        });
    }

    public function test_no_review_link_for_a_middling_score_or_when_it_is_off(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');
        $this->enable('review_request', ['settings' => ['review_url' => 'https://g.page/r/lumiere', 'min_rating' => 4]]);
        $this->runAutomations();

        $r = $this->chatSend($chat, 'x', 'rate_3');

        $this->assertStringNotContainsString('g.page', implode(' ', array_column($r->json('messages'), 'text')));
        $this->in(fn () => $this->assertNull(Survey::query()->first()->review_sent_at));
    }

    public function test_replying_with_something_else_just_carries_on_and_leaves_the_survey_open(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');
        $this->runAutomations();

        $this->in(fn () => $chat->forceFill(['language' => 'en', 'state' => array_merge((array) $chat->fresh()->state, ['node' => 'main', 'awaiting' => 'options'])])->save());

        $r = $this->chatSend($chat, 'opening hours');

        $this->assertStringContainsString('Mon to Fri', implode(' ', array_column($r->json('messages'), 'text')));
        $this->in(function () {
            $this->assertNull(Survey::query()->first()->rating);
        });
    }

    public function test_no_show_recovery_offers_a_new_time_and_book_now_starts_booking(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'no_show', '2026-11-10 03:00:00', '2026-11-10 04:00:00');       // marked 4 hours ago
        $this->booking($c, $chat, 'no_show', '2026-11-10 07:00:00', '2026-11-10 07:30:00');       // marked 30 minutes ago: too soon
        $this->enable('no_show');

        $sent = $this->runAutomations();
        $this->runAutomations();

        $this->assertSame(1, $sent['no_show']);
        $messages = $this->botMessages($chat);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('we missed you at your Facial appointment', $messages[0]->body);
        $this->assertSame('__book', $messages[0]->payload['options'][0]['id']);

        $r = $this->chatSend($chat, 'x', '__book');
        $this->assertStringContainsString('Which service', implode(' ', array_column($r->json('messages'), 'text')));
    }

    public function test_rebooking_reminds_people_who_have_not_been_back_and_not_those_with_a_booking(): void
    {
        [$away, $chatA] = $this->customer(['name' => 'Away']);
        [$booked, $chatB] = $this->customer(['name' => 'Booked']);
        [$recent, $chatC] = $this->customer(['name' => 'Recent']);
        $this->booking($away, $chatA, 'completed', '2026-09-20 05:00:00');                      // 51 days ago
        $this->booking($booked, $chatB, 'completed', '2026-09-20 05:00:00');
        $this->booking($booked, $chatB, 'confirmed', '2026-11-20 05:00:00');                    // already has a visit coming
        $this->booking($recent, $chatC, 'completed', '2026-11-01 05:00:00');                    // only 9 days ago
        $this->enable('rebooking', ['settings' => ['rebook_days' => 30]]);

        $this->assertSame(1, $this->runAutomations()['rebooking']);
        $this->assertSame(0, $this->runAutomations()['rebooking']);                                         // not again within 60 days

        $this->assertCount(1, $this->botMessages($chatA));
        $this->assertStringContainsString('Hi Away, it has been a while since your Facial', $this->botMessages($chatA)[0]->body);
        $this->assertSame([], $this->botMessages($chatB));
        $this->assertSame([], $this->botMessages($chatC));
    }

    public function test_hot_leads_who_never_booked_get_one_follow_up(): void
    {
        [$hot, $chatA] = $this->customer(['name' => 'Hot', 'score' => 'hot', 'interest' => 'HIFU']);
        [$hotBooked, $chatB] = $this->customer(['name' => 'HotBooked', 'score' => 'hot']);
        [$warm, $chatC] = $this->customer(['name' => 'Warm', 'score' => 'warm']);
        [$fresh, $chatD] = $this->customer(['name' => 'Fresh', 'score' => 'hot']);
        $this->in(function () use ($hot, $hotBooked, $warm, $fresh) {
            foreach ([$hot, $hotBooked, $warm] as $c) {
                \DB::table('customers')->where('id', $c->id)->update(['created_at' => '2026-11-08 08:00:00']);   // two days ago
            }
            \DB::table('customers')->where('id', $fresh->id)->update(['created_at' => '2026-11-10 07:00:00']);     // one hour ago
        });
        $this->booking($hotBooked, $chatB, 'completed', '2026-11-09 05:00:00');
        $this->enable('hot_lead', ['delay_hours' => 24]);

        $this->assertSame(1, $this->runAutomations()['hot_lead']);
        $this->assertSame(0, $this->runAutomations()['hot_lead']);

        $this->assertStringContainsString('thanks for your interest in HIFU', $this->botMessages($chatA)[0]->body);
        foreach ([$chatB, $chatC, $chatD] as $chat) {
            $this->assertSame([], $this->botMessages($chat));
        }
    }

    public function test_chinese_customers_get_chinese_text(): void
    {
        [$c, $chat] = $this->customer(['language' => 'zh']);
        $this->in(fn () => $chat->forceFill(['language' => 'zh'])->save());
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');

        $this->runAutomations();

        $m = $this->botMessages($chat)[0];
        $this->assertStringContainsString('感谢您光临Lumiere', $m->body);
        $this->assertSame('5 - 非常满意', $m->payload['options'][0]['label']);
    }

    private function whatsappChat(Customer $c, string $inboundAt): Conversation
    {
        $channel = $this->in(fn () => Channel::create(['type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN1', 'waba_id' => 'W1', 'access_token' => 't']));

        return $this->in(function () use ($c, $channel, $inboundAt) {
            $chat = Conversation::startFor('whatsapp');
            $chat->forceFill(['customer_id' => $c->id, 'channel_id' => $channel->id, 'external_id' => '60123', 'last_inbound_at' => $inboundAt])->save();

            return $chat;
        });
    }

    public function test_after_24_hours_whatsapp_messages_are_skipped_unless_an_approved_template_is_set(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);
        [$c] = $this->customer(['name' => 'Amy']);
        $this->in(fn () => Conversation::query()->delete());
        $chat = $this->whatsappChat($c, '2026-11-07 08:00:00');                                 // three days ago
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');

        $this->assertSame(0, $this->runAutomations()['survey']);
        Http::assertNothingSent();
        $reason = $this->in(fn () => AutomationLog::query()->first()->reason);
        $this->assertStringContainsString('24-hour window is closed', $reason);

        // Now with an approved template.
        $this->in(function () {
            MessageTemplate::create(['channel_id' => Channel::query()->first()->id, 'name' => 'visit_survey', 'language' => 'en', 'body' => 'Hi {{1}}, how was your {{2}}?', 'status' => 'APPROVED']);
            AutomationLog::query()->delete();
            Automation::query()->where('key', 'survey')->update(['template_name' => 'visit_survey']);
        });

        $this->assertSame(1, $this->runAutomations()['survey']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'PN1/messages') && $r['type'] === 'template' && $r['template']['name'] === 'visit_survey'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Amy' && $r['template']['components'][0]['parameters'][1]['text'] === 'Facial');
    }

    public function test_the_run_now_button_and_the_command_cover_every_business_separately(): void
    {
        [$c, $chat] = $this->customer();
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');

        [$oc, $ochat] = $this->customer(['name' => 'Other customer'], $this->other);
        $this->in(function () use ($oc, $ochat) {
            $s = Service::create(['name' => ['en' => 'Massage'], 'duration_min' => 60]);
            $p = Practitioner::create(['name' => 'X']);
            Booking::create(['customer_id' => $oc->id, 'conversation_id' => $ochat->id, 'service_id' => $s->id, 'practitioner_id' => $p->id,
                'starts_at' => '2026-11-10 04:00:00', 'ends_at' => '2026-11-10 05:00:00', 'status' => 'completed']);
            AutomationDefaults::ensure();                                                       // other business: everything stays off
        }, $this->other);

        $this->artisan('aichat:run-automations')->expectsOutput('1 message(s) sent.')->assertSuccessful();

        $this->assertCount(1, $this->botMessages($chat));
        $this->assertSame([], $this->in(fn () => Message::query()->where('conversation_id', $ochat->id)->where('sender_type', 'bot')->get()->all(), $this->other));
    }

    public function test_automation_settings_are_for_owners_and_managers_and_validated(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);

        $this->actingAs($staff)->get('/admin/automations')->assertForbidden();
        $this->actingAs($staff)->post('/admin/automations/survey', ['enabled' => 1, 'message_en' => 'x'])->assertForbidden();
        $this->actingAs($manager)->get('/admin/automations')->assertOk()->assertSee('Satisfaction survey')->assertSee('Hot lead follow-up');

        $this->actingAs($manager)->post('/admin/automations/survey', ['enabled' => 1, 'delay_hours' => 5, 'message_en' => 'Rate us {name}', 'message_zh' => '请评分'])->assertSessionHasNoErrors();
        $this->in(function () {
            $a = Automation::query()->where('key', 'survey')->first();
            $this->assertTrue($a->enabled);
            $this->assertSame(5, $a->delay_hours);
            $this->assertSame('Rate us {name}', $a->message['en']);
        });

        $this->actingAs($manager)->post('/admin/automations/review_request', ['enabled' => 1, 'message_en' => 'x', 'review_url' => ''])->assertSessionHasErrors('automation');
        $this->actingAs($manager)->post('/admin/automations/review_request', ['enabled' => 1, 'message_en' => 'x', 'review_url' => 'not a url'])->assertSessionHasErrors('review_url');
        $this->actingAs($manager)->post('/admin/automations/survey', ['enabled' => 0, 'message_en' => 'x', 'template_name' => 'Bad Name'])->assertSessionHasErrors('template_name');
        $this->actingAs($manager)->post('/admin/automations/nonsense', ['enabled' => 1, 'message_en' => 'x'])->assertNotFound();

        $this->actingAs($owner)->post('/admin/automations/run')->assertRedirect();
        $this->actingAs($owner)->get('/admin/automations/activity')->assertOk();
    }

    public function test_the_activity_page_lists_sent_and_skipped_messages(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        [$c, $chat] = $this->customer(['name' => 'Visible Vera']);
        [$o, $ochat] = $this->customer(['name' => 'Secret Sam'], $this->other);
        $this->booking($c, $chat, 'completed', '2026-11-10 05:00:00');
        $this->enable('survey');
        $this->runAutomations();
        $this->in(fn () => AutomationLog::create(['automation_key' => 'survey', 'customer_id' => $o->id, 'status' => 'sent', 'created_at' => now()]), $this->other);

        $this->actingAs($owner)->get('/admin/automations/activity')->assertOk()->assertSee('Visible Vera')->assertDontSee('Secret Sam');
        // The log table is filtered (no failed messages); the survey list below it is not.
        $this->actingAs($owner)->get('/admin/automations/activity?status=failed')->assertSee('Nothing yet');
    }

    private function consented(string $name, array $attrs = [], ?Business $b = null): array
    {
        return $this->customer(['name' => $name] + $attrs, $b);
    }

    public function test_a_broadcast_reaches_only_people_who_agreed_and_only_once(): void
    {
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        [$a] = $this->consented('Agreed A');
        [$b] = $this->consented('Agreed B');
        [$stopped] = $this->consented('Stopped', ['opted_out_at' => now()->subDay()]);
        [$never] = $this->consented('Never agreed', ['consented_at' => null]);
        [$theirs, $theirChat] = $this->consented('Other business', [], $this->other);

        $this->actingAs($manager)->post('/admin/broadcasts', ['name' => 'Summer offer', 'message_en' => 'Hi {name}, 10% off this week at {business}!', 'message_zh' => '您好 {name}'])->assertSessionHasNoErrors();
        $id = $this->in(fn () => Broadcast::query()->first()->id);

        $this->actingAs($manager)->get('/admin/broadcasts')->assertSee('2 can receive it');
        $this->actingAs($manager)->post("/admin/broadcasts/{$id}/send")->assertRedirect();
        $this->actingAs($manager)->post("/admin/broadcasts/{$id}/send");                        // double click

        $this->in(function () use ($id, $a, $b, $stopped, $never) {
            $bc = Broadcast::query()->find($id);
            $this->assertSame('sent', $bc->status);
            $this->assertSame([2, 0, 0], [$bc->sent_count, $bc->skipped_count, $bc->failed_count]);
            $this->assertEqualsCanonicalizing([$a->id, $b->id], BroadcastRecipient::query()->where('broadcast_id', $id)->pluck('customer_id')->all());
            $this->assertSame(2, Message::query()->where('body', 'like', 'Hi %10% off this week at Lumiere!')->count());
        });
        $this->assertSame([], $this->in(fn () => Message::query()->where('conversation_id', $theirChat->id)->get()->all(), $this->other));
    }

    public function test_broadcast_audience_filters_and_skipped_whatsapp_customers(): void
    {
        Http::fake();
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $tag = $this->in(fn () => Tag::create(['name' => 'VIP', 'type' => 'customer']));
        [$vip] = $this->consented('Vip', ['score' => 'hot']);
        [$plain] = $this->consented('Plain', ['score' => 'hot']);
        $this->in(fn () => $vip->tags()->attach($tag->id));
        [$wa] = $this->consented('Wa', ['score' => 'hot']);
        $this->in(fn () => $wa->tags()->attach($tag->id));
        $this->in(fn () => Conversation::query()->where('customer_id', $wa->id)->delete());
        $this->whatsappChat($wa, '2026-11-01 08:00:00');                                        // window closed

        $this->actingAs($manager)->post('/admin/broadcasts', ['name' => 'VIPs', 'message_en' => 'Hello {name}', 'tag_id' => $tag->id, 'score' => 'hot'])->assertSessionHasNoErrors();
        $id = $this->in(fn () => Broadcast::query()->first()->id);
        $this->actingAs($manager)->post("/admin/broadcasts/{$id}/send");

        $this->in(function () use ($id, $plain) {
            $bc = Broadcast::query()->find($id);
            $this->assertSame([1, 1, 0], [$bc->sent_count, $bc->skipped_count, $bc->failed_count]);
            $this->assertNull(BroadcastRecipient::query()->where('customer_id', $plain->id)->first());
            $this->assertStringContainsString('24-hour window', BroadcastRecipient::query()->where('status', 'skipped')->first()->reason);
        });
        Http::assertNothingSent();
        $this->actingAs($manager)->get("/admin/broadcasts/{$id}")->assertOk()->assertSee('Wa')->assertSee('Vip');
    }

    public function test_broadcast_pages_are_for_owners_and_managers_and_tags_stay_in_the_business(): void
    {
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $theirTag = $this->in(fn () => Tag::create(['name' => 'Theirs', 'type' => 'customer']), $this->other);

        $this->actingAs($staff)->get('/admin/broadcasts')->assertForbidden();
        $this->actingAs($staff)->post('/admin/broadcasts', ['name' => 'x', 'message_en' => 'x'])->assertForbidden();
        $this->actingAs($manager)->post('/admin/broadcasts', ['name' => 'x', 'message_en' => 'x', 'tag_id' => $theirTag->id])->assertNotFound();

        $foreign = $this->in(fn () => Broadcast::create(['name' => 'Foreign', 'message' => ['en' => 'x'], 'status' => 'draft']), $this->other);
        $this->actingAs($manager)->post("/admin/broadcasts/{$foreign->id}/send")->assertNotFound();
        $this->actingAs($manager)->get("/admin/broadcasts/{$foreign->id}")->assertNotFound();
    }

    public function test_only_drafts_can_be_deleted(): void
    {
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $draft = $this->in(fn () => Broadcast::create(['name' => 'Draft', 'message' => ['en' => 'x'], 'status' => 'draft']));
        $sentOne = $this->in(fn () => Broadcast::create(['name' => 'Done', 'message' => ['en' => 'x'], 'status' => 'sent']));

        $this->actingAs($manager)->post("/admin/broadcasts/{$draft->id}/delete")->assertRedirect();
        $this->actingAs($manager)->post("/admin/broadcasts/{$sentOne->id}/delete")->assertStatus(422);

        $this->in(function () use ($draft, $sentOne) {
            $this->assertNull(Broadcast::query()->find($draft->id));
            $this->assertNotNull(Broadcast::query()->find($sentOne->id));
        });
    }

    public function test_orders_can_be_added_filtered_and_updated_within_the_business(): void
    {
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        [$c] = $this->consented('Amy Tan');
        [$theirs] = $this->consented('Secret', [], $this->other);

        $this->actingAs($staff)->post('/admin/orders', ['customer_id' => $c->id, 'items' => '3 facial package', 'amount' => 450, 'status' => 'paid', 'ordered_at' => '2026-11-09 10:00'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post('/admin/orders', ['customer_id' => $c->id, 'items' => 'Serum', 'amount' => 80, 'status' => 'pending'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post('/admin/orders', ['customer_id' => $theirs->id, 'amount' => 1, 'status' => 'paid'])->assertNotFound();
        $this->actingAs($staff)->post('/admin/orders', ['amount' => -5, 'status' => 'paid'])->assertSessionHasErrors('amount');

        $this->actingAs($staff)->get('/admin/orders')->assertOk()->assertSee('3 facial package')->assertSee('450.00')->assertSee('Paid total shown: <b>450.00</b>', false);
        $this->actingAs($staff)->get('/admin/orders?status=pending')->assertSee('Serum')->assertDontSee('3 facial package');

        $pending = $this->in(fn () => Order::query()->where('items', 'Serum')->first());
        $this->actingAs($staff)->post("/admin/orders/{$pending->id}", ['status' => 'paid'])->assertRedirect();
        $this->in(fn () => $this->assertSame('paid', Order::query()->find($pending->id)->status));
        $this->actingAs($staff)->post("/admin/orders/{$pending->id}", ['status' => 'weird'])->assertSessionHasErrors('status');

        $other = $this->in(fn () => Order::create(['amount' => 5, 'status' => 'paid', 'items' => 'Not yours']), $this->other);
        $this->actingAs($staff)->post("/admin/orders/{$other->id}", ['status' => 'cancelled'])->assertNotFound();
        $this->actingAs($staff)->get('/admin/orders')->assertDontSee('Not yours');
    }

    public function test_reports_show_the_numbers_for_this_business_and_period(): void
    {
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        [$c, $chat] = $this->customer(['name' => 'Amy', 'score' => 'hot']);
        $b = $this->booking($c, $chat, 'completed', '2026-11-09 05:00:00');
        $this->in(function () use ($c, $chat, $b) {
            Survey::create(['booking_id' => $b->id, 'customer_id' => $c->id, 'conversation_id' => $chat->id, 'rating' => 4, 'sent_at' => now(), 'answered_at' => now()]);
            Order::create(['customer_id' => $c->id, 'amount' => 200, 'status' => 'paid', 'ordered_at' => now()]);
            \App\Models\BotEvent::create(['conversation_id' => $chat->id, 'type' => 'handover', 'created_at' => now()]);
            $chat->forceFill(['handover_at' => now()->subMinutes(10)])->save();
            $chat->addMessage('out', 'staff', 'Hello', null, false);
            \App\Models\BotEvent::create(['conversation_id' => $chat->id, 'type' => 'option', 'node' => 'main', 'option_id' => 'prices', 'created_at' => now()]);
        });
        $this->customer(['name' => 'Elsewhere'], $this->other);

        $r = $this->actingAs($staff)->get('/admin/reports')->assertOk();
        $r->assertSee('chats started')->assertSee('4.0 / 5')->assertSee('200.00')->assertSee('Facial')->assertSee('Prices');
        $this->actingAs($staff)->get('/admin/reports?days=7')->assertOk()->assertSee('The last 7 days');
        $this->actingAs($staff)->get('/admin/reports?days=999')->assertOk()->assertSee('The last 30 days');
    }

    public function test_guests_cannot_see_the_new_pages(): void
    {
        foreach (['/admin/reports', '/admin/orders', '/admin/automations', '/admin/broadcasts'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
