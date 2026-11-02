<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $other;

    private Channel $wa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.meta.app_secret' => 'topsecret', 'services.meta.verify_token' => 'verify-me', 'services.meta.graph_version' => 'v21.0']);
        Carbon::setTestNow('2026-11-02 00:00:00');

        $this->business = Business::factory()->create(['slug' => 'demo', 'timezone' => 'Asia/Kuala_Lumpur']);
        $this->other = Business::factory()->create(['slug' => 'other']);
        $this->wa = $this->in(fn () => Channel::create(['type' => 'whatsapp', 'name' => 'Clinic WA', 'external_id' => 'PNID1', 'waba_id' => 'WABA1', 'access_token' => 'tok-wa']));
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]])]);
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

    private function hook(array $body, ?string $secret = 'topsecret')
    {
        $raw = json_encode($body);
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $server['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $raw, $secret);
        }

        return $this->call('POST', '/webhook/meta', [], [], [], $server, $raw);
    }

    private function waText(string $text, string $id = 'wamid.1', string $from = '60123456789', string $phoneId = 'PNID1'): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA1', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '15550001111', 'phone_number_id' => $phoneId],
            'contacts' => [['profile' => ['name' => 'Amy'], 'wa_id' => $from]],
            'messages' => [['from' => $from, 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];
    }

    private function waTap(string $optionId, string $id): array
    {
        $body = $this->waText('x', $id);
        $body['entry'][0]['changes'][0]['value']['messages'][0] = ['from' => '60123456789', 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => $optionId, 'title' => 'x']]];

        return $body;
    }

    /** Replace the fake Meta (a second Http::fake would never win over the first). */
    private function meta(array $stubs): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs);
    }

    /** @return list<array> the JSON bodies we sent to Meta */
    private function sent(): array
    {
        return Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), '/messages') && $p[0]->method() === 'POST')->map(fn ($p) => $p[0]->data())->values()->all();
    }

    public function test_meta_verification_handshake(): void
    {
        $this->get('/webhook/meta?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')->assertOk()->assertSee('12345');
        $this->get('/webhook/meta?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')->assertForbidden();
        config(['services.meta.verify_token' => '']);
        $this->get('/webhook/meta?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')->assertForbidden();
    }

    public function test_messages_without_a_valid_signature_are_rejected(): void
    {
        $this->hook($this->waText('hi'), null)->assertForbidden();
        $this->hook($this->waText('hi'), 'wrong-secret')->assertForbidden();
        config(['services.meta.app_secret' => '']);
        $this->hook($this->waText('hi'), '')->assertForbidden();

        $this->in(fn () => $this->assertSame(0, Conversation::count()));
        $this->assertSame([], $this->sent());
    }

    public function test_a_whatsapp_message_starts_a_chat_and_the_bot_answers_with_buttons(): void
    {
        $this->hook($this->waText('hello'))->assertOk();

        $this->in(function () {
            $c = Conversation::query()->first();
            $this->assertSame($this->wa->id, $c->channel_id);
            $this->assertSame('60123456789', $c->external_id);
            $this->assertSame('Amy', $c->contact_name);
            $this->assertSame('whatsapp', $c->channel);
            $this->assertSame(2, $c->messages()->count());
        });

        $out = $this->sent();
        $this->assertCount(1, $out);
        $this->assertSame('60123456789', $out[0]['to']);
        $this->assertSame('button', $out[0]['interactive']['type']);                    // 2 options: reply buttons
        $this->assertSame(['lang_en', 'lang_zh'], array_column(array_column($out[0]['interactive']['action']['buttons'], 'reply'), 'id'));
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok-wa') && str_contains($r->url(), 'graph.facebook.com/v21.0/PNID1/messages'));
    }

    public function test_tapping_a_button_continues_the_menu_and_long_menus_become_lists(): void
    {
        $this->hook($this->waText('hello', 'wamid.1'));
        $this->hook($this->waTap('lang_en', 'wamid.2'));

        $out = $this->sent();
        $list = end($out);
        $this->assertSame('list', $list['interactive']['type']);                         // main menu has more than 3 options
        $rows = $list['interactive']['action']['sections'][0]['rows'];
        $this->assertContains('book', array_column($rows, 'id'));
        $this->assertLessThanOrEqual(10, count($rows));
        foreach ($rows as $row) {
            $this->assertLessThanOrEqual(24, mb_strlen($row['title']));
        }
    }

    public function test_the_same_message_delivered_twice_is_handled_once(): void
    {
        $this->hook($this->waText('hello', 'wamid.same'));
        $this->hook($this->waText('hello', 'wamid.same'));

        $this->in(fn () => $this->assertSame(1, Message::query()->where('direction', 'in')->count()));
        $this->assertCount(1, $this->sent());
    }

    public function test_a_returning_customer_continues_the_same_chat(): void
    {
        $this->hook($this->waText('hello', 'wamid.1'));
        $this->hook($this->waText('menu', 'wamid.2'));

        $this->in(fn () => $this->assertSame(1, Conversation::count()));
    }

    public function test_unknown_switched_off_or_foreign_numbers_are_ignored(): void
    {
        $this->hook($this->waText('hi', 'wamid.a', '6011', 'NOT_OURS'))->assertOk();

        $this->in(fn () => $this->wa->update(['active' => false]));
        $this->hook($this->waText('hi', 'wamid.b'))->assertOk();

        $this->in(fn () => $this->assertSame(0, Conversation::count()));
        $this->assertSame([], $this->sent());
    }

    public function test_each_business_only_gets_its_own_messages(): void
    {
        $this->in(fn () => Channel::create(['type' => 'whatsapp', 'name' => 'Other WA', 'external_id' => 'PNID2', 'access_token' => 'tok2']), $this->other);

        $this->hook($this->waText('hi', 'wamid.x', '6011', 'PNID2'));

        $this->in(fn () => $this->assertSame(0, Conversation::count()));
        $this->in(fn () => $this->assertSame(1, Conversation::count()), $this->other);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok2'));
    }

    public function test_messenger_and_instagram_get_quick_replies(): void
    {
        $this->in(fn () => Channel::create(['type' => 'messenger', 'name' => 'FB', 'external_id' => 'PAGE1', 'access_token' => 'tok-fb']));
        $this->in(fn () => Channel::create(['type' => 'instagram', 'name' => 'IG', 'external_id' => 'IG1', 'waba_id' => 'PAGE1', 'access_token' => 'tok-ig']));

        $message = fn (string $object, string $account) => ['object' => $object, 'entry' => [['id' => $account, 'messaging' => [
            ['sender' => ['id' => 'USER9'], 'recipient' => ['id' => $account], 'timestamp' => now()->timestamp * 1000, 'message' => ['mid' => 'm_'.$object, 'text' => 'hi']],
        ]]]];

        $this->hook($message('page', 'PAGE1'))->assertOk();
        $this->hook($message('instagram', 'IG1'))->assertOk();

        $out = $this->sent();
        $this->assertCount(2, $out);
        foreach ($out as $body) {
            $this->assertSame('USER9', $body['recipient']['id']);
            $this->assertSame('RESPONSE', $body['messaging_type']);
            $this->assertSame(['lang_en', 'lang_zh'], array_column($body['message']['quick_replies'], 'payload'));
        }
        Http::assertSent(fn ($r) => str_contains($r->url(), '/PAGE1/messages') && $r->hasHeader('Authorization', 'Bearer tok-fb'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/IG1/messages') && $r->hasHeader('Authorization', 'Bearer tok-ig'));
    }

    public function test_quick_reply_taps_and_echoes(): void
    {
        $this->in(fn () => Channel::create(['type' => 'messenger', 'name' => 'FB', 'external_id' => 'PAGE1', 'access_token' => 't']));
        $send = fn (array $m) => $this->hook(['object' => 'page', 'entry' => [['id' => 'PAGE1', 'messaging' => [$m + ['recipient' => ['id' => 'PAGE1'], 'timestamp' => now()->timestamp * 1000]]]]]);

        $send(['sender' => ['id' => 'U1'], 'message' => ['mid' => 'm1', 'text' => 'hi']]);
        $send(['sender' => ['id' => 'U1'], 'message' => ['mid' => 'm2', 'text' => 'English', 'quick_reply' => ['payload' => 'lang_en']]]);
        $send(['sender' => ['id' => 'PAGE1'], 'message' => ['mid' => 'm3', 'text' => 'our own echo', 'is_echo' => true]]);

        $out = $this->sent();
        $this->assertCount(2, $out);                                                        // echo ignored
        $this->assertStringContainsString('How can I help', $out[1]['message']['text']);
        $this->assertLessThanOrEqual(13, count($out[1]['message']['quick_replies']));
        foreach ($out[1]['message']['quick_replies'] as $q) {
            $this->assertLessThanOrEqual(20, mb_strlen($q['title']));
        }
    }

    public function test_delivery_receipts_and_unsupported_types_are_skipped(): void
    {
        $body = $this->waText('x');
        unset($body['entry'][0]['changes'][0]['value']['messages']);
        $body['entry'][0]['changes'][0]['value']['statuses'] = [['id' => 'wamid.OUT', 'status' => 'delivered']];
        $this->hook($body)->assertOk();

        $reaction = $this->waText('x');
        $reaction['entry'][0]['changes'][0]['value']['messages'][0] = ['from' => '6011', 'id' => 'wamid.react', 'type' => 'reaction', 'reaction' => ['emoji' => '👍']];
        $this->hook($reaction)->assertOk();

        $this->in(fn () => $this->assertSame(0, Conversation::count()));
    }

    public function test_staff_replies_go_out_on_whatsapp_and_failures_are_marked(): void
    {
        $this->hook($this->waText('hi', 'wamid.1'));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->first()->id);

        $this->actingAs($staff)->postJson("/admin/api/conversations/{$id}/reply", ['text' => 'We have a slot at 3pm'])->assertOk()->assertJsonPath('message.delivery', 'sent');
        $all = $this->sent();
        $last = end($all);
        $this->assertSame('text', $last['type']);
        $this->assertSame('We have a slot at 3pm', $last['text']['body']);

        $this->meta(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Token expired']], 401)]);
        $r = $this->actingAs($staff)->postJson("/admin/api/conversations/{$id}/reply", ['text' => 'Hello again'])->assertOk();
        $this->assertSame('failed', $r->json('message.delivery'));
        $this->assertStringContainsString('Token expired', $r->json('message.delivery_error'));
        $this->in(fn () => $this->assertNotNull(Channel::query()->first()->last_error));
    }

    public function test_after_24_hours_staff_cannot_send_free_text_but_can_send_a_template(): void
    {
        $this->hook($this->waText('hi', 'wamid.1'));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->first()->id);
        $template = $this->in(fn () => MessageTemplate::create(['channel_id' => $this->wa->id, 'name' => 'booking_reminder', 'language' => 'en', 'body' => 'Hi {{1}}, see you at {{2}}.', 'status' => 'APPROVED']));
        $pending = $this->in(fn () => MessageTemplate::create(['channel_id' => $this->wa->id, 'name' => 'new_one', 'language' => 'en', 'body' => 'x', 'status' => 'PENDING']));

        Carbon::setTestNow('2026-11-03 01:00:00'); // 25 hours later
        $show = $this->actingAs($staff)->getJson("/admin/api/conversations/{$id}")->assertOk();
        $this->assertFalse($show->json('conversation.window_open'));
        $this->assertSame(['booking_reminder'], array_column($show->json('templates'), 'name'));

        $before = count($this->sent());
        $this->actingAs($staff)->postJson("/admin/api/conversations/{$id}/reply", ['text' => 'late hello'])->assertStatus(422);
        $this->assertCount($before, $this->sent());

        $this->actingAs($staff)->postJson("/admin/api/conversations/{$id}/template", ['template_id' => $pending->id, 'params' => []])->assertStatus(422);
        $this->actingAs($staff)->postJson("/admin/api/conversations/{$id}/template", ['template_id' => $template->id, 'params' => ['Amy', '3pm']])->assertOk()->assertJsonPath('message.text', 'Hi Amy, see you at 3pm.');

        $all = $this->sent();
        $sent = end($all);
        $this->assertSame('template', $sent['type']);
        $this->assertSame('booking_reminder', $sent['template']['name']);
        $this->assertSame([['type' => 'text', 'text' => 'Amy'], ['type' => 'text', 'text' => '3pm']], $sent['template']['components'][0]['parameters']);
    }

    public function test_customer_messages_in_a_staff_chat_notify_n8n_and_the_bot_stays_quiet(): void
    {
        config(['services.n8n.webhook_url' => 'http://n8n.test/hook']);
        $this->hook($this->waText('hi', 'wamid.1'));
        $this->in(fn () => Conversation::query()->first()->handOverToStaff('test'));
        $this->meta(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]]), 'n8n.test/*' => Http::response('ok')]);

        $this->hook($this->waText('anyone there?', 'wamid.2'));

        $this->assertSame([], $this->sent());
        $events = Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'n8n.test'))->map(fn ($p) => $p[0]->data()['event'])->all();
        $this->assertContains('customer.message', $events);
    }

    public function test_a_booking_can_be_made_end_to_end_on_whatsapp(): void
    {
        $this->in(function () {
            $p = Practitioner::create(['name' => 'Dr Mei']);
            Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 60])->practitioners()->sync([$p->id]);
            $this->business->update(['settings' => ['hours' => ['tue' => ['10:00', '19:00']]]]);
        });

        $n = 0;
        $tap = function (string $o) use (&$n) {
            return $this->hook($this->waTap($o, 'wamid.t'.++$n));
        };
        $this->hook($this->waText('hi', 'wamid.start'));
        $tap('lang_en');
        $tap('book');
        $serviceId = $this->in(fn () => 'svc_'.Service::query()->first()->id);
        $tap($serviceId);
        $tap('day_2026-11-03');
        $tap('slot_10:00');
        $this->hook($this->waText('Amy Tan', 'wamid.name'));
        $this->hook($this->waText('012-345 6789', 'wamid.phone'));

        $this->in(function () {
            $b = Booking::query()->with('customer')->first();
            $this->assertNotNull($b);
            $this->assertSame('Amy Tan', $b->customer->name);
        });
        $texts = array_map(fn ($o) => $o['text']['body'] ?? ($o['interactive']['body']['text'] ?? ''), $this->sent());
        $this->assertStringContainsString('You are booked, Amy Tan', implode(' ', $texts));
    }

    public function test_reminders_use_a_template_when_the_window_is_closed(): void
    {
        $this->in(function () {
            $this->wa->update(['settings' => ['reminder_template' => 'booking_reminder']]);
            MessageTemplate::create(['channel_id' => $this->wa->id, 'name' => 'booking_reminder', 'language' => 'en', 'body' => 'Hi {{1}}, your {{2}} is {{3}}.', 'status' => 'APPROVED']);
            $p = Practitioner::create(['name' => 'Dr Mei']);
            $s = Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 60]);
            $s->practitioners()->sync([$p->id]);
        });
        $this->hook($this->waText('hi', 'wamid.1'));   // customer last wrote on Monday 00:00

        $this->in(function () {
            $c = Conversation::query()->first();
            $c->forceFill(['language' => 'en'])->save();
            $customer = \App\Models\Customer::create(['name' => 'Amy', 'phone' => '0123456789']);
            Booking::create(['conversation_id' => $c->id, 'customer_id' => $customer->id, 'service_id' => Service::query()->first()->id, 'practitioner_id' => Practitioner::query()->first()->id,
                'starts_at' => '2026-11-04 02:00:00', 'ends_at' => '2026-11-04 03:00:00', 'status' => 'confirmed']);
            DB::table('bookings')->update(['created_at' => '2026-11-01 00:00:00']);
        });

        Carbon::setTestNow('2026-11-03 02:00:00'); // 24h before the visit, and 50h after the customer's last message
        $before = count($this->sent());
        $this->artisan('aichat:send-reminders');

        $out = array_slice($this->sent(), $before);
        $this->assertCount(1, $out);
        $this->assertSame('template', $out[0]['type']);
        $this->assertSame('Amy', $out[0]['template']['components'][0]['parameters'][0]['text']);
    }

    public function test_reminders_skip_the_chat_message_when_the_window_is_closed_and_no_template_is_set(): void
    {
        $this->in(function () {
            $p = Practitioner::create(['name' => 'Dr Mei']);
            Service::create(['name' => ['en' => 'Facial'], 'duration_min' => 60])->practitioners()->sync([$p->id]);
        });
        $this->hook($this->waText('hi', 'wamid.1'));
        $this->in(function () {
            $c = Conversation::query()->first();
            Booking::create(['conversation_id' => $c->id, 'service_id' => Service::query()->first()->id, 'practitioner_id' => Practitioner::query()->first()->id,
                'starts_at' => '2026-11-04 02:00:00', 'ends_at' => '2026-11-04 03:00:00', 'status' => 'confirmed']);
            DB::table('bookings')->update(['created_at' => '2026-11-01 00:00:00']);
        });

        Carbon::setTestNow('2026-11-03 02:00:00');
        $before = count($this->sent());
        $this->artisan('aichat:send-reminders');

        $this->assertCount($before, $this->sent());
        $this->in(fn () => $this->assertSame(0, Message::query()->where('body', 'like', 'Reminder:%')->count()));
    }

    public function test_channels_page_is_owner_only_hides_tokens_and_blocks_duplicates(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $otherOwner = User::factory()->create(['business_id' => $this->other->id, 'role' => 'owner']);

        $this->actingAs($manager)->get('/admin/channels')->assertForbidden();
        $this->actingAs($owner)->get('/admin/channels')->assertOk()->assertSee('Clinic WA')->assertDontSee('tok-wa')->assertSee('/webhook/meta');

        $this->actingAs($owner)->post('/admin/channels', ['type' => 'messenger', 'name' => 'FB', 'external_id' => 'PAGE7', 'access_token' => 'secret-token'])->assertRedirect();
        $this->assertSame(1, Channel::query()->withoutGlobalScopes()->where('external_id', 'PAGE7')->count());
        $raw = DB::table('channels')->where('external_id', 'PAGE7')->value('access_token');
        $this->assertStringNotContainsString('secret-token', $raw);                          // encrypted at rest

        $this->actingAs($otherOwner)->post('/admin/channels', ['type' => 'messenger', 'name' => 'Steal', 'external_id' => 'PAGE7', 'access_token' => 'x'])->assertSessionHasErrors('external_id');
        $this->actingAs($otherOwner)->post('/admin/channels', ['type' => 'whatsapp', 'name' => 'Bad', 'external_id' => 'a b<script>', 'access_token' => 'x'])->assertSessionHasErrors('external_id');

        $this->actingAs($otherOwner)->post("/admin/channels/{$this->wa->id}", ['name' => 'Hacked', 'active' => 0])->assertNotFound();
        $this->actingAs($otherOwner)->post("/admin/channels/{$this->wa->id}/delete")->assertNotFound();
    }

    public function test_the_callback_url_comes_from_app_url_not_the_address_in_the_browser(): void
    {
        config(['app.url' => 'https://abc123.lhr.life/aichat/public/']);
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);

        $this->actingAs($owner)->get('http://localhost/admin/channels')->assertOk()
            ->assertSee('https://abc123.lhr.life/aichat/public/webhook/meta', false)
            ->assertDontSee('This address only works on your own computer');
    }

    public function test_the_webhook_can_be_saved_in_meta_from_the_channels_page(): void
    {
        config(['services.meta.app_id' => '555', 'app.url' => 'https://abc123.lhr.life/aichat/public']);
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->meta(['graph.facebook.com/v21.0/555/subscriptions' => Http::response(['success' => true])]);

        $this->actingAs($owner)->post('/admin/channels/webhook')->assertSessionHas('status', fn ($s) => str_contains($s, 'WhatsApp'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/555/subscriptions')
            && $r['object'] === 'whatsapp_business_account'
            && $r['callback_url'] === 'https://abc123.lhr.life/aichat/public/webhook/meta'
            && $r['verify_token'] === 'verify-me'
            && $r['fields'] === 'messages'
            && $r['access_token'] === '555|topsecret');
    }

    public function test_saving_the_webhook_needs_the_app_settings_and_shows_meta_errors(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);

        $this->actingAs($owner)->post('/admin/channels/webhook')->assertSessionHasErrors('channel');   // no META_APP_ID

        config(['services.meta.app_id' => '555']);
        $this->meta(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Callback verification failed']], 400)]);
        $this->actingAs($owner)->post('/admin/channels/webhook')->assertSessionHasErrors('channel');

        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->actingAs($staff)->post('/admin/channels/webhook')->assertForbidden();
    }

    public function test_the_artisan_command_saves_the_webhook_and_reports_problems(): void
    {
        config(['services.meta.app_id' => '555', 'app.url' => 'https://new.lhr.life/aichat/public']);
        $this->meta(['graph.facebook.com/v21.0/555/subscriptions' => Http::response(['success' => true])]);

        $this->artisan('aichat:meta-webhook')->expectsOutputToContain('https://new.lhr.life/aichat/public/webhook/meta')->assertSuccessful();

        $this->meta(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Callback verification failed']], 400)]);
        $this->artisan('aichat:meta-webhook')->expectsOutputToContain('Callback verification failed')->assertFailed();

        config(['services.meta.app_id' => '']);
        $this->artisan('aichat:meta-webhook')->expectsOutputToContain('META_APP_ID')->assertFailed();
    }

    public function test_check_subscribe_and_test_message_buttons(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->meta([
            'graph.facebook.com/v21.0/PNID1?*' => Http::response(['display_phone_number' => '+1 555 000 1111']),
            'graph.facebook.com/v21.0/WABA1/subscribed_apps' => Http::response(['success' => true]),
            'graph.facebook.com/v21.0/PNID1/messages' => Http::response(['messages' => [['id' => 'x']]]),
        ]);

        $this->actingAs($owner)->post("/admin/channels/{$this->wa->id}/check")->assertSessionHas('status', fn ($s) => str_contains($s, '+1 555 000 1111'));
        $this->actingAs($owner)->post("/admin/channels/{$this->wa->id}/subscribe")->assertSessionHas('status');
        $this->actingAs($owner)->post("/admin/channels/{$this->wa->id}/test", ['to' => '+60 12-345 6789'])->assertSessionHas('status');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'PNID1/messages') && ($r['template']['name'] ?? '') === 'hello_world' && $r['to'] === '60123456789');

        $this->meta(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);
        $this->actingAs($owner)->post("/admin/channels/{$this->wa->id}/check")->assertSessionHasErrors('channel');
    }

    public function test_template_manager_creates_syncs_and_stays_in_the_business(): void
    {
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->actingAs($staff)->get('/admin/templates')->assertForbidden();

        $this->meta(['graph.facebook.com/v21.0/WABA1/message_templates*' => Http::sequence()
            ->push(['id' => 'T1', 'status' => 'PENDING'])
            ->push(['data' => [
                ['id' => 'T1', 'name' => 'booking_reminder', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, see you {{2}}.']]],
                ['id' => 'T2', 'name' => 'made_in_meta', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'MARKETING', 'components' => [['type' => 'BODY', 'text' => 'Big offer']]],
            ]])]);

        $this->actingAs($manager)->post('/admin/templates', ['channel_id' => $this->wa->id, 'name' => 'booking_reminder', 'language' => 'en', 'category' => 'UTILITY', 'body' => 'Hi {{1}}, see you {{2}}.'])->assertSessionHasNoErrors();
        Http::assertSent(function ($r) {
            $c = $r['components'][0] ?? [];

            return str_contains($r->url(), 'WABA1/message_templates') && $r['name'] === 'booking_reminder' && ($c['example']['body_text'][0] ?? []) === ['Example 1', 'Example 2'];
        });
        $this->in(fn () => $this->assertSame('PENDING', MessageTemplate::query()->first()->status));

        $this->actingAs($manager)->post('/admin/templates/sync')->assertSessionHasNoErrors();
        $this->in(function () {
            $this->assertSame('APPROVED', MessageTemplate::query()->where('name', 'booking_reminder')->first()->status);
            $this->assertSame('Big offer', MessageTemplate::query()->where('name', 'made_in_meta')->first()->body);
        });

        $this->actingAs($manager)->post('/admin/templates', ['channel_id' => $this->wa->id, 'name' => 'Bad Name!', 'language' => 'en', 'category' => 'UTILITY', 'body' => 'x'])->assertSessionHasErrors('name');
        $otherWa = $this->in(fn () => Channel::create(['type' => 'whatsapp', 'name' => 'Theirs', 'external_id' => 'PNID9', 'waba_id' => 'W9', 'access_token' => 't']), $this->other);
        $this->actingAs($manager)->post('/admin/templates', ['channel_id' => $otherWa->id, 'name' => 'sneaky', 'language' => 'en', 'category' => 'UTILITY', 'body' => 'x'])->assertNotFound();
    }

    public function test_a_meta_rejection_is_shown_to_the_user(): void
    {
        $manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $this->meta(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter', 'error_user_msg' => 'Template name already used']], 400)]);

        $this->actingAs($manager)->post('/admin/templates', ['channel_id' => $this->wa->id, 'name' => 'dup', 'language' => 'en', 'category' => 'UTILITY', 'body' => 'x'])
            ->assertSessionHasErrors('template');
        $this->in(fn () => $this->assertStringContainsString('Template name already used', MessageTemplate::query()->first()->last_error));
    }

    public function test_a_message_delivered_hours_late_is_kept_but_not_answered(): void
    {
        $body = $this->waText('what is the pricing for a facial', 'wamid.late');
        $body['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'] = (string) now()->subHours(3)->timestamp;

        $this->hook($body)->assertOk();

        $this->assertSame([], $this->sent());
        $this->in(function () {
            $c = Conversation::query()->first();
            $this->assertSame(1, Message::query()->where('direction', 'in')->count());
            $this->assertStringContainsString('arrived 3 hours late', Message::query()->where('sender_type', 'system')->first()->body);
            $this->assertTrue(Message::query()->where('sender_type', 'system')->first()->is_private);
            $this->assertEqualsWithDelta(now()->subHours(3)->timestamp, $c->last_inbound_at->timestamp, 2);   // the 24-hour window counts from when they wrote
        });

        // The next message is fresh and is answered as normal.
        $this->hook($this->waText('hi', 'wamid.fresh'));
        $this->assertCount(1, $this->sent());
    }

    public function test_a_message_older_than_a_day_leaves_the_window_closed(): void
    {
        $body = $this->waText('hello', 'wamid.old');
        $body['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'] = (string) now()->subHours(30)->timestamp;
        $this->hook($body)->assertOk();

        $this->in(fn () => $this->assertFalse(Conversation::query()->first()->windowOpen()));
    }

    public function test_a_timestamp_in_the_future_counts_as_now(): void
    {
        $body = $this->waText('hello', 'wamid.future');
        $body['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'] = (string) now()->addHours(5)->timestamp;
        $this->hook($body)->assertOk();

        $this->assertCount(1, $this->sent());
        $this->in(fn () => $this->assertLessThanOrEqual(now()->timestamp, Conversation::query()->first()->last_inbound_at->timestamp));
    }

    public function test_the_channels_page_shows_status_cards_website_embed_and_setup_checks(): void
    {
        config(['app.url' => 'https://abc123.lhr.life', 'services.meta.app_id' => '555', 'services.meta.app_secret' => 'sec', 'services.meta.verify_token' => 'tok']);
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        Channel::query()->withoutGlobalScopes()->where('business_id', $this->business->id)->delete();

        $r = $this->actingAs($owner)->get('/admin/channels')->assertOk();
        $r->assertSee('Website chat')->assertSee('Always on')->assertSee('Not connected')->assertSee('Nothing connected yet')
            ->assertSee('&lt;iframe src=', false)->assertSee('/chat/'.$this->business->slug, false)->assertSee('All set');

        $this->actingAs($owner)->post('/admin/channels', ['type' => 'whatsapp', 'name' => 'Front desk', 'external_id' => '15551', 'access_token' => 'tok-x'])->assertRedirect();
        $this->get('/admin/channels')->assertOk()->assertSee('Front desk')->assertSee('Connected')->assertSee('Working')->assertSee('Send me a test message')->assertDontSee('tok-x');

        Channel::query()->where('external_id', '15551')->update(['last_error' => 'Token expired']);
        $this->get('/admin/channels')->assertSee('Needs attention')->assertSee('Problem')->assertSee('Token expired');

        config(['services.meta.app_secret' => '', 'services.meta.verify_token' => '']);
        $this->get('/admin/channels')->assertSee('connect-dialog', false)->assertSee('Settings')->assertSee('2 to fix')->assertSee('Add META_APP_SECRET to .env')->assertSee('Add META_VERIFY_TOKEN to .env');
    }

    public function test_the_channels_page_says_what_the_last_call_from_meta_did(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);

        $this->actingAs($owner)->get('/admin/channels')->assertSee('Meta has not contacted this app yet');

        $this->hook($this->waText('hi'), 'wrong-secret')->assertForbidden();
        $this->actingAs($owner)->get('/admin/channels')->assertSee('the message was rejected');

        $body = $this->waText('hi');
        $body['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = 'NOT-MINE';
        $this->hook($body)->assertOk();
        $this->actingAs($owner)->get('/admin/channels')->assertSee('no channel here has that ID')->assertSee('NOT-MINE');

        $this->hook($this->waText('hello'))->assertOk();
        $this->actingAs($owner)->get('/admin/channels')->assertSee('Meta last sent 1 message(s)');
    }

    private function waMedia(string $type, array $item, string $id): array
    {
        $body = $this->waText('x', $id);
        $body['entry'][0]['changes'][0]['value']['messages'][0] = ['from' => '60123456789', 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => $type, $type => $item];

        return $body;
    }

    public function test_a_photo_is_kept_the_bot_says_thanks_and_a_person_gets_the_chat(): void
    {
        $this->hook($this->waMedia('image', ['id' => 'MEDIA1', 'mime_type' => 'image/jpeg', 'caption' => 'my rash'], 'wamid.img1'))->assertOk();

        $this->in(function () {
            $c = Conversation::query()->first();
            $this->assertSame('human', $c->mode);
            $photo = Message::query()->where('direction', 'in')->first();
            $this->assertSame('my rash', $photo->body);
            $this->assertSame('MEDIA1', $photo->payload['media']['id']);
            $this->assertSame('image', $photo->toChatArray()['media']['type']);
            $this->assertTrue($photo->toChatArray()['media']['file']);
            $this->assertStringContainsString('I received it', (string) Message::query()->where('sender_type', 'bot')->latest('id')->value('body'));
        });
    }

    public function test_a_voice_note_without_text_shows_a_label_and_a_location_keeps_its_pin_and_a_sticker_is_silent(): void
    {
        $this->hook($this->waMedia('audio', ['id' => 'AUD1', 'mime_type' => 'audio/ogg; codecs=opus'], 'wamid.a1'))->assertOk();
        $this->in(fn () => $this->assertSame('🎤 Voice message', Message::query()->where('direction', 'in')->first()->body));

        $this->hook($this->waMedia('location', ['latitude' => 3.139, 'longitude' => 101.687, 'name' => 'Plaza Example'], 'wamid.l1'))->assertOk();
        $this->in(function () {
            $loc = Message::query()->where('direction', 'in')->latest('id')->first();
            $this->assertSame('location', $loc->payload['media']['type']);
            $this->assertEqualsWithDelta(3.139, $loc->toChatArray()['media']['lat'], 0.0001);
            $this->assertSame('Plaza Example', $loc->toChatArray()['media']['place']);
        });

        $bots = $this->in(fn () => Message::query()->where('sender_type', 'bot')->count());
        $this->hook($this->waMedia('sticker', ['id' => 'ST1', 'mime_type' => 'image/webp'], 'wamid.s1'))->assertOk();
        $this->in(fn () => $this->assertSame($bots, Message::query()->where('sender_type', 'bot')->count()));
    }

    public function test_messenger_attachments_are_parsed_and_staff_can_open_a_photo_through_the_media_address(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $page = $this->in(fn () => Channel::create(['type' => 'messenger', 'name' => 'FB', 'external_id' => 'PAGE9', 'access_token' => 'tok-fb']));
        $body = ['object' => 'page', 'entry' => [['id' => 'PAGE9', 'messaging' => [['sender' => ['id' => 'PSID1'], 'timestamp' => now()->timestamp * 1000,
            'message' => ['mid' => 'm.1', 'attachments' => [['type' => 'image', 'payload' => ['url' => 'https://cdn.example/pic.jpg']]]]]]]]];
        $this->hook($body)->assertOk();

        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $msg = $this->in(fn () => Message::query()->where('direction', 'in')->first());
        $this->assertSame('https://cdn.example/pic.jpg', $msg->payload['media']['url']);

        $this->meta(['cdn.example/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);
        $r = $this->actingAs($staff)->get('/admin/api/messages/'.$msg->id.'/media')->assertOk();
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $this->assertSame('PNGDATA', $r->getContent());

        // A second request is served from the kept copy, and another business can never open it.
        $this->meta([]);
        $this->actingAs($staff)->get('/admin/api/messages/'.$msg->id.'/media')->assertOk();
        $strangerStaff = User::factory()->create(['business_id' => $this->other->id, 'role' => 'staff']);
        $this->actingAs($strangerStaff)->get('/admin/api/messages/'.$msg->id.'/media')->assertNotFound();
    }

    public function test_a_customer_file_that_could_run_code_is_only_offered_as_a_download(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->hook($this->waMedia('document', ['id' => 'DOC1', 'mime_type' => 'text/html', 'filename' => 'x.html'], 'wamid.d1'))->assertOk();
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $msg = $this->in(fn () => Message::query()->where('direction', 'in')->first());

        $this->meta(['graph.facebook.com/*/DOC1*' => Http::response(['url' => 'https://lookaside.example/doc', 'mime_type' => 'text/html']), 'lookaside.example/*' => Http::response('<script>alert(1)</script>', 200)]);
        $r = $this->actingAs($staff)->get('/admin/api/messages/'.$msg->id.'/media')->assertOk();

        $this->assertSame('application/octet-stream', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
    }

    private function png(): \Illuminate\Http\UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return \Illuminate\Http\UploadedFile::fake()->createWithContent('shop.png', $png);
    }

    public function test_staff_can_send_a_photo_and_a_pdf_on_whatsapp_and_the_customer_link_works(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['app.url' => 'https://abc.example']);
        $this->hook($this->waText('hi', 'wamid.1'));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->first()->id);

        $this->actingAs($staff)->post("/admin/api/conversations/{$id}/attach", ['file' => $this->png(), 'caption' => 'Our shop'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message.delivery', 'sent');

        $all = $this->sent();
        $last = end($all);
        $this->assertSame('image', $last['type']);
        $this->assertSame('Our shop', $last['image']['caption']);
        $this->assertMatchesRegularExpression('#^https://abc\.example/f/\d+/[A-Za-z0-9]{40}$#', $last['image']['link']);

        // The customer (Meta) opens that link without logging in; a wrong code opens nothing.
        $path = parse_url($last['image']['link'], PHP_URL_PATH);
        $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(substr($path, 0, -3).'xyz')->assertNotFound();

        $pdf = \Illuminate\Http\UploadedFile::fake()->createWithContent('price list.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($staff)->post("/admin/api/conversations/{$id}/attach", ['file' => $pdf], ['Accept' => 'application/json'])->assertOk();
        $all = $this->sent();
        $last = end($all);
        $this->assertSame('document', $last['type']);
        $this->assertSame('price list.pdf', $last['document']['filename']);
    }

    public function test_attachments_that_could_be_harmful_are_refused_and_other_businesses_cannot_use_a_chat(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->hook($this->waText('hi', 'wamid.1'));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->first()->id);

        $exe = \Illuminate\Http\UploadedFile::fake()->createWithContent('run.exe', "MZ\x90\x00\x03\x00\x00\x00");
        $this->actingAs($staff)->post("/admin/api/conversations/{$id}/attach", ['file' => $exe], ['Accept' => 'application/json'])->assertStatus(422);

        $stranger = User::factory()->create(['business_id' => $this->other->id, 'role' => 'staff']);
        $this->actingAs($stranger)->post("/admin/api/conversations/{$id}/attach", ['file' => $this->png()], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_a_photo_sent_on_messenger_goes_as_an_attachment_with_the_caption_after_it(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->in(fn () => Channel::create(['type' => 'messenger', 'name' => 'FB', 'external_id' => 'PAGE9', 'access_token' => 'tok-fb']));
        $this->hook(['object' => 'page', 'entry' => [['id' => 'PAGE9', 'messaging' => [['sender' => ['id' => 'PSID1'], 'timestamp' => now()->timestamp * 1000, 'message' => ['mid' => 'm.1', 'text' => 'hello']]]]]]);
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->where('channel', 'messenger')->first()->id);

        $this->actingAs($staff)->post("/admin/api/conversations/{$id}/attach", ['file' => $this->png(), 'caption' => 'Here it is'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message.delivery', 'sent');

        $all = $this->sent();
        $this->assertSame('image', $all[count($all) - 2]['message']['attachment']['type']);
        $this->assertSame('Here it is', end($all)['message']['text']);
    }

    public function test_an_expired_token_failure_says_how_to_fix_it(): void
    {
        $this->hook($this->waText('hi', 'wamid.1'));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->first()->id);
        $this->meta(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Authentication Error']], 401)]);

        $r = $this->actingAs($staff)->postJson("/admin/api/conversations/{$id}/reply", ['text' => 'Hello'])->assertOk();

        $this->assertSame('failed', $r->json('message.delivery'));
        $this->assertStringContainsString('Fix connection', $r->json('message.delivery_error'));
        $this->assertStringContainsString('expired', (string) $this->in(fn () => Channel::query()->first()->last_error));
    }

    private function waStatus(string $id, string $status, array $errors = []): array
    {
        $body = $this->waText('x');
        unset($body['entry'][0]['changes'][0]['value']['messages']);
        $body['entry'][0]['changes'][0]['value']['statuses'] = [['id' => $id, 'status' => $status, 'recipient_id' => '60123456789'] + ($errors ? ['errors' => $errors] : [])];

        return $body;
    }

    public function test_the_later_delivery_news_from_whatsapp_reaches_the_inbox(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->hook($this->waText('hi', 'wamid.1'));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->first()->id);
        $this->actingAs($staff)->postJson("/admin/api/conversations/{$id}/reply", ['text' => 'Hello'])->assertOk();    // the fake answers with message id wamid.OUT

        $this->hook($this->waStatus('wamid.OUT', 'delivered'))->assertOk();
        $this->in(fn () => $this->assertSame('delivered', Message::query()->where('external_id', 'wamid.OUT')->first()->payload['receipt']));

        $this->hook($this->waStatus('wamid.OUT', 'read'))->assertOk();
        $this->hook($this->waStatus('wamid.OUT', 'sent'))->assertOk();                                           // never goes backwards
        $this->in(fn () => $this->assertSame('read', Message::query()->where('external_id', 'wamid.OUT')->first()->payload['receipt']));

        $shown = $this->actingAs($staff)->getJson("/admin/api/conversations/{$id}?after=9999")->json('receipts');
        $this->assertContains('read', array_column($shown, 'receipt'));
    }

    public function test_a_message_accepted_by_whatsapp_but_never_delivered_shows_the_real_reason(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->hook($this->waText('hi', 'wamid.1'));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $id = $this->in(fn () => Conversation::query()->first()->id);
        $this->actingAs($staff)->post("/admin/api/conversations/{$id}/attach", ['file' => $this->png()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message.delivery', 'sent');

        $this->hook($this->waStatus('wamid.OUT', 'failed', [['code' => 131053, 'title' => 'Media upload error', 'message' => 'Media upload error', 'error_data' => ['details' => 'Unable to download the media']]]))->assertOk();

        $msg = $this->in(fn () => Message::query()->where('external_id', 'wamid.OUT')->first());
        $this->assertSame('failed', $msg->payload['delivery']);
        $this->assertStringContainsString('could not download the file', $msg->payload['delivery_error']);
        $this->assertStringContainsString('APP_URL', $msg->payload['delivery_error']);

        $note = $this->in(fn () => \App\Models\StaffNotification::query()->where('title', 'like', 'Message to%was not delivered')->count());
        $this->assertGreaterThan(0, $note);

        // A status for a message we do not know is ignored.
        $this->hook($this->waStatus('wamid.NOPE', 'failed', [['code' => 1]]))->assertOk();
    }

    public function test_a_messenger_channel_can_be_saved_and_its_page_id_corrected(): void
    {
        $owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $fb = $this->in(fn () => Channel::create(['type' => 'messenger', 'name' => 'FB', 'external_id' => 'WRONGPAGE', 'access_token' => 'tok-fb']));

        // The Messenger form has no reminder template field: saving must not crash, and a new Page ID replaces the wrong one.
        $this->actingAs($owner)->post('/admin/channels/'.$fb->id, ['name' => 'FB shop', 'external_id' => '1409457962254526', 'active' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $fb->refresh();
        $this->assertSame('1409457962254526', $fb->external_id);
        $this->assertSame('FB shop', $fb->name);
        $this->assertSame('tok-fb', $fb->access_token);

        // Another business's page cannot be taken over, and a bad value is refused.
        $this->in(fn () => Channel::create(['type' => 'messenger', 'name' => 'Theirs', 'external_id' => 'THEIRPAGE', 'access_token' => 'x']), $this->other);
        $this->actingAs($owner)->post('/admin/channels/'.$fb->id, ['name' => 'FB shop', 'external_id' => 'THEIRPAGE', 'active' => 1])->assertSessionHasErrors('external_id');
        $this->actingAs($owner)->post('/admin/channels/'.$fb->id, ['name' => 'FB shop', 'external_id' => 'a b<x>', 'active' => 1])->assertSessionHasErrors('external_id');
        $this->assertSame('1409457962254526', $fb->fresh()->external_id);

        // WhatsApp still keeps its reminder template.
        $this->actingAs($owner)->post('/admin/channels/'.$this->wa->id, ['name' => 'Clinic WA', 'external_id' => 'PNID1', 'active' => 1, 'reminder_template' => 'booking_reminder'])->assertRedirect();
        $this->assertSame('booking_reminder', $this->wa->fresh()->settings['reminder_template']);
    }
}
