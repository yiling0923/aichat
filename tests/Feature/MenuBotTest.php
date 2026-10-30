<?php

namespace Tests\Feature;

use App\Bot\DefaultFlow;
use App\Bot\FlowRepository;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuBotTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'demo']);
    }

    private function say(?string $text, ?string $token = null, ?string $option = null)
    {
        return $this->postJson(route('chat.send', 'demo'), ['text' => $text, 'option_id' => $option, 'token' => $token]);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function last(\Illuminate\Testing\TestResponse $r): array
    {
        $bots = array_values(array_filter($r->json('messages'), fn ($m) => $m['from'] === 'bot'));
        $m = end($bots);

        return [$m['text'], $m];
    }

    private function inBusiness(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    public function test_first_message_shows_language_choice(): void
    {
        $r = $this->say('hi');
        [$text, $m] = $this->last($r);
        $this->assertStringContainsString('choose your language', $text);
        $this->assertSame(['lang_en', 'lang_zh'], array_column($m['options'], 'id'));
    }

    public function test_english_path_to_prices_and_back_to_menu(): void
    {
        $token = $this->say('hi')->json('token');
        [$text] = $this->last($this->say('English', $token, 'lang_en'));
        $this->assertStringContainsString('How can I help', $text);

        $r = $this->say('Prices', $token, 'prices');
        $texts = array_column(array_filter($r->json('messages'), fn ($m) => $m['from'] === 'bot'), 'text');
        $this->assertStringContainsString('Hydrating Facial', $texts[0]);
        $this->assertStringContainsString('How can I help', end($texts));
    }

    public function test_chinese_path_uses_chinese_text(): void
    {
        $token = $this->say('hi')->json('token');
        [$text, $m] = $this->last($this->say('中文', $token, 'lang_zh'));
        $this->assertStringContainsString('请问需要什么帮助', $text);
        $this->assertSame('价格', $m['options'][0]['label']);
    }

    public function test_typed_number_label_and_keyword_pick_options(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('1', $token);                       // English by number
        [$text] = $this->last($this->say('how much', $token)); // keyword for prices -> back at main
        $this->assertStringContainsString('How can I help', $text);

        $r = $this->say('opening hours', $token);       // label
        $this->assertStringContainsString('Mon to Fri', $r->json('messages.1.text'));
    }

    public function test_staff_option_and_keyword_hand_over(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('x', $token, 'lang_en');
        $r = $this->say('x', $token, 'staff');
        $this->assertSame('human', $r->json('mode'));

        $this->assertNull($this->say('anyone there?', $token)->json('messages.1'));

        $token2 = $this->say('human')->json('token');
        $this->assertSame('human', $this->inBusiness(fn () => Conversation::query()->where('token', $token2)->first()->mode));
    }

    public function test_repeated_misunderstanding_hands_over(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('x', $token, 'lang_en');
        $this->say('asdf', $token);
        $this->say('qwer', $token);
        $r = $this->say('zxcv', $token);
        $this->assertSame('human', $r->json('mode'));
    }

    public function test_menu_keyword_returns_to_main(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('x', $token, 'lang_en');
        $this->say('x', $token, 'hours');
        [$text] = $this->last($this->say('menu', $token));
        $this->assertStringContainsString('How can I help', $text);
    }

    public function test_stop_marks_opt_out_without_handover(): void
    {
        $token = $this->say('hi')->json('token');
        $r = $this->say('STOP', $token);
        $this->assertSame('bot', $r->json('mode'));
        $this->inBusiness(fn () => $this->assertNotNull(Conversation::query()->first()->opted_out_at));
    }

    public function test_default_flow_is_valid(): void
    {
        $result = app(\App\Bot\FlowValidator::class)->validate(DefaultFlow::make());
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_every_option_in_the_default_flow_can_be_walked(): void
    {
        $flow = DefaultFlow::make();
        foreach ($flow['nodes'] as $id => $node) {
            foreach ($node['options'] ?? [] as $o) {
                $token = $this->say('hi')->json('token');
                $this->say('x', $token, 'lang_en');
                $r = $this->say('x', $token, $o['id']);
                $r->assertOk();
                $this->assertNotEmpty($r->json('messages'), "option {$o['id']} produced no reply");
            }
        }
        $this->assertSame(1, app(FlowRepository::class)->published($this->business)->mainNodeId() === 'main' ? 1 : 0);
    }

    public function test_first_menu_has_no_back_button_and_later_menus_do(): void
    {
        $r = $this->say('hi');
        $token = $r->json('token');
        $this->assertNotContains('__back', array_column($this->last($r)[1]['options'], 'id'));

        $r = $this->say('x', $token, 'lang_en');
        $this->assertSame('__back', array_slice(array_column($this->last($r)[1]['options'], 'id'), -1)[0]);
    }

    public function test_back_returns_to_the_previous_step_so_a_wrong_tap_can_be_undone(): void
    {
        $token = $this->say('hi')->json('token');
        $r = $this->say('x', $token, 'lang_zh');           // wrong language
        $this->assertStringContainsString('请问需要什么帮助', $this->last($r)[0]);

        $r = $this->say('x', $token, '__back');             // back to the language question
        $this->assertStringContainsString('choose your language', $this->last($r)[0]);

        $r = $this->say('x', $token, 'lang_en');
        $this->assertStringContainsString('How can I help', $this->last($r)[0]);
    }

    public function test_typed_back_works_and_back_after_a_message_does_not_loop(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('x', $token, 'lang_en');
        $this->say('x', $token, 'prices');                  // shows prices, then the menu again
        [$text, $m] = $this->last($this->say('x', $token, 'hours'));
        $ids = array_column($m['options'], 'id');
        $this->assertContains('__back', $ids);

        $r = $this->say('back', $token);                    // typed
        $this->assertStringContainsString('choose your language', $this->last($r)[0]);
    }

    public function test_back_with_no_history_shows_the_main_menu_and_the_back_button_is_listed_once(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('x', $token, 'lang_en');
        $r = $this->say('x', $token, '__back');
        $ids = array_column($this->last($r)[1]['options'], 'id');
        $this->assertSame(1, count(array_keys($ids, 'lang_en')));
        $this->assertNotContains('__back', $ids);
    }

    public function test_natural_ways_of_asking_for_a_person_and_a_refund_reach_staff(): void
    {
        foreach (['Can I talk to a person?', 'I want to speak to someone please', 'I want a refund', '我要退款'] as $phrase) {
            $token = $this->say('hi')->json('token');
            $this->say('English', $token, 'lang_en');
            $r = $this->say($phrase, $token);
            $this->assertSame('human', $r->json('mode'), $phrase);
        }
    }

    public function test_the_bot_follows_the_language_the_customer_types(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('中文', $token, 'lang_zh');

        $r = $this->say('what are your prices please', $token);
        $this->assertSame('en', $this->inBusiness(fn () => Conversation::query()->where('token', $token)->first()->language));
        [$text] = $this->last($r);
        $this->assertDoesNotMatchRegularExpression('/\p{Han}/u', $text);

        $this->say('你们的价格是多少', $token);
        $this->assertSame('zh', $this->inBusiness(fn () => Conversation::query()->where('token', $token)->first()->language));
    }

    public function test_a_customer_waiting_for_a_person_still_gets_the_menu_information_they_ask_for(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('English', $token, 'lang_en');
        $this->say('x', $token, 'staff');                                  // Talk to staff: waiting for a person

        $r = $this->say('what time do you open', $token);
        $texts = implode(' | ', array_column(array_filter($r->json('messages'), fn ($m) => $m['from'] === 'bot'), 'text'));
        $this->assertStringContainsString('We are open', $texts);
        $this->assertStringContainsString('will still reply', $texts);
        $this->assertSame('human', $r->json('mode'));                      // still waiting for a person

        // Booking and "talk to a person" are not answered by the bot while a person is on the way.
        foreach (['I want to book an appointment', 'Can I talk to a person?'] as $text) {
            $r = $this->say($text, $token);
            $bots = array_filter($r->json('messages'), fn ($m) => $m['from'] === 'bot' && str_contains($m['text'], 'will still reply'));
            $this->assertCount(0, $bots, $text);                           // nothing new from the bot
        }
    }

    public function test_tapping_book_now_wakes_the_bot_even_when_a_person_has_the_chat(): void
    {
        $token = $this->say('hi')->json('token');
        $this->say('English', $token, 'lang_en');
        $this->say('x', $token, 'staff');

        $r = $this->say('Book now', $token, '__book');

        // The bot answered about booking (this test business has no services or hours, so it says there are no free times).
        [$text] = $this->last($r);
        $this->assertMatchesRegularExpression('/Which service|no free times/', $text);
    }

    public function test_saved_answers_from_teach_it_are_sent_without_any_ai(): void
    {
        $this->inBusiness(function () {
            \App\Models\KnowledgeEntry::create(['title' => 'Payment methods', 'answer' => ['en' => 'Cash, card or DuitNow.', 'zh' => '现金、刷卡或 DuitNow。'], 'active' => true]);
            \App\Models\KnowledgeEntry::create(['title' => 'Parking', 'answer' => ['en' => 'Free parking behind the shop.'], 'active' => true]);
            \App\Models\KnowledgeEntry::create(['title' => 'First visit', 'answer' => ['en' => 'Arrive 10 minutes early.'], 'active' => true]);
            \App\Models\KnowledgeEntry::create(['title' => 'Walk-ins', 'answer' => ['en' => 'Walk-ins welcome.'], 'active' => false]);
        });
        $token = $this->say('hi')->json('token');
        $this->say('English', $token, 'lang_en');

        [$text, $m] = $this->last($this->say('Payment methods', $token));
        $this->assertSame('Cash, card or DuitNow.', $text);
        $this->assertNotEmpty($m['options']);                       // the menu buttons stay under the answer

        $this->assertSame('Free parking behind the shop.', $this->last($this->say('is there parking?', $token))[0]);
        $this->assertSame('Cash, card or DuitNow.', $this->last($this->say('can i pay by card', $token))[0]);
        $this->assertSame('Arrive 10 minutes early.', $this->last($this->say('what about my first visit', $token))[0]);

        // One loose word of a longer title, a turned-off answer and unrelated words do not trigger anything.
        $this->assertStringNotContainsString('Arrive 10', $this->last($this->say('can i visit tomorrow', $token))[0]);
        $this->assertStringNotContainsString('Walk-ins welcome', $this->last($this->say('walk in please', $token))[0]);
        $this->assertStringNotContainsString('Cash', $this->last($this->say('blah blah', $token))[0]);
    }

    public function test_saved_answers_use_the_customers_language(): void
    {
        $this->inBusiness(fn () => \App\Models\KnowledgeEntry::create(['title' => 'Parking', 'answer' => ['en' => 'Free parking.', 'zh' => '有免费停车位。'], 'active' => true]));
        $token = $this->say('hi')->json('token');
        $this->say('中文', $token, 'lang_zh');

        $this->assertSame('有免费停车位。', $this->last($this->say('有停车位吗', $token))[0]);
    }
}
