<?php

namespace Tests\Feature;

use App\Bot\DefaultFlow;
use App\Bot\Strings;
use App\Bot\Text;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Setup\SetupApplier;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChineseWordingTest extends TestCase
{
    use RefreshDatabase;

    private function commands(string $key, string $text): bool
    {
        return Text::isCommand(Text::normalize($text), config('aichat.keywords.'.$key));
    }

    private function menuOption(string $id): array
    {
        $options = DefaultFlow::make()['nodes']['main']['options'];

        return collect($options)->firstWhere('id', $id);
    }

    private function optionMatches(string $id, string $text): bool
    {
        $normalized = Text::normalize($text);

        return collect($this->menuOption($id)['keywords'] ?? [])->contains(fn ($k) => Text::containsKeyword($normalized, $k));
    }

    public function test_full_width_typing_is_understood(): void
    {
        $this->assertSame('0', Text::normalize('０'));
        $this->assertSame('human', Text::normalize('ＨＵＭＡＮ！'));
        $this->assertTrue($this->commands('menu', '０'));
        $this->assertTrue($this->commands('human', '人工。'));
    }

    public function test_traditional_and_common_phrases_work_as_commands(): void
    {
        $this->assertTrue($this->commands('human', '轉人工'));
        $this->assertTrue($this->commands('human', '我要找客服'));
        $this->assertTrue($this->commands('menu', '返回菜單'));
        $this->assertTrue($this->commands('back', '返回上一頁'));
        $this->assertTrue($this->commands('opt_out', '不要再發'));

        foreach (['human', 'menu', 'back', 'opt_out'] as $key) {
            $this->assertFalse($this->commands($key, '我想問大約要多久'), $key.' must not fire on an ordinary question');
        }
    }

    public function test_menu_keywords_cover_traditional_and_do_not_overreach(): void
    {
        $this->assertTrue($this->optionMatches('prices', '請問多少錢'));
        $this->assertTrue($this->optionMatches('book', '我想預約'));
        $this->assertTrue($this->optionMatches('hours', '幾點開門'));
        $this->assertTrue($this->optionMatches('location', '有停車嗎'));
        $this->assertTrue($this->optionMatches('promo', '有優惠嗎'));

        $this->assertFalse($this->optionMatches('book', '大約三十分鐘嗎'));
        $this->assertFalse($this->optionMatches('location', '哪里可以预约'));
    }

    public function test_the_words_the_bot_asks_people_to_type_really_work(): void
    {
        $this->assertTrue($this->commands('human', '人工'));      // sorry_choose, handover_return
        $this->assertTrue($this->commands('menu', '菜单'));       // handover_sorry
        $this->assertTrue($this->commands('back', '返回'));       // booking_ask_name, booking_ask_phone
        $this->assertStringContainsString('“人工”', Strings::get('sorry_choose', 'zh'));
        $this->assertStringContainsString('“返回”', Strings::get('booking_ask_name', 'zh'));
        $this->assertStringContainsString('“菜单”', Strings::get('handover_sorry', 'zh'));
    }

    public function test_every_english_string_has_a_chinese_one_with_the_same_placeholders(): void
    {
        $map = (new \ReflectionClass(Strings::class))->getReflectionConstant('MAP')->getValue();

        foreach ($map['en'] as $key => $en) {
            $this->assertArrayHasKey($key, $map['zh'], $key.' has no Chinese text');
            preg_match_all('/\{[a-z_]+\}/', $en, $a);
            preg_match_all('/\{[a-z_]+\}/', $map['zh'][$key], $b);
            $this->assertEqualsCanonicalizing($a[0], $b[0], $key.' placeholders differ');
        }
    }

    public function test_the_setup_menu_text_is_fully_chinese_in_chinese(): void
    {
        $business = Business::factory()->create(['settings' => ['currency' => 'RM', 'hours' => ['mon' => ['10:00', '19:00'], 'sat' => ['10:00', '17:00']]]]);
        User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        app(CurrentBusiness::class)->runAs($business, function () use ($business) {
            Service::create(['name' => ['en' => 'Facial', 'zh' => '面部护理'], 'duration_min' => 60, 'price' => 188, 'sort' => 1]);
            Service::create(['name' => ['en' => 'Consultation'], 'duration_min' => 30, 'price' => 0, 'sort' => 2]);
            app(SetupApplier::class)->updateMenuTexts($business, null);
        });

        $nodes = app(\App\Bot\FlowRepository::class)->published($business)->nodes();
        $prices = $nodes['prices']['text']['zh'];
        $hours = $nodes['hours']['text']['zh'];

        $this->assertStringContainsString('- 面部护理：RM 188（60 分钟）', $prices);
        $this->assertStringContainsString('请咨询', $prices);
        $this->assertStringNotContainsString('min', $prices);
        $this->assertStringContainsString('周一 10:00 - 19:00', $hours);
        $this->assertStringContainsString('周日 休息', $hours);
        $this->assertStringNotContainsString('closed', $hours);
        $this->assertStringContainsString('Mon 10:00 - 19:00', $nodes['hours']['text']['en']);   // English unchanged
    }

    public function test_traditional_is_read_as_simplified_so_old_saved_menus_understand_it_too(): void
    {
        if (! class_exists(\Transliterator::class)) {
            $this->markTestSkipped('PHP intl is not installed');
        }

        $this->assertSame(Text::normalize('多少钱'), Text::normalize('多少錢'));
        $this->assertSame(['Price question', 'Complaint'], (new \App\Services\AutoTagger)->topics('多少錢？我要退款'));
    }
}
