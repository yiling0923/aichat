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
}
