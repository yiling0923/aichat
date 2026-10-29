<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $business = Business::factory()->create(['slug' => 'lang-co']);
        $this->owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
    }

    public function test_english_is_the_default_and_the_switch_offers_chinese(): void
    {
        $this->actingAs($this->owner)->get('/admin/today')->assertOk()->assertSee('<html lang="en">', false)->assertSee('Daily summary')->assertSee('中文')
            ->assertDontSee('每日摘要');
    }

    public function test_switching_to_chinese_is_saved_per_person_and_changes_the_pages(): void
    {
        $other = User::factory()->create(['business_id' => $this->owner->business_id, 'role' => 'staff']);

        $this->actingAs($this->owner)->post('/admin/language', ['locale' => 'zh'])->assertRedirect();
        $this->assertSame('zh', $this->owner->fresh()->locale);

        $page = $this->actingAs($this->owner)->get('/admin/today')->assertOk();
        $page->assertSee('<html lang="zh-CN">', false)->assertSee('每日摘要')->assertSee('收件箱')->assertSee('设置指南')->assertSee('English')->assertDontSee('Daily summary');

        // another person on the same business keeps English
        $this->actingAs($other)->get('/admin/today')->assertSee('Daily summary')->assertDontSee('每日摘要');

        $this->actingAs($this->owner)->post('/admin/language', ['locale' => 'en'])->assertRedirect();
        $this->actingAs($this->owner)->get('/admin/today')->assertSee('Daily summary');
    }

    public function test_an_unknown_language_is_refused(): void
    {
        $this->actingAs($this->owner)->post('/admin/language', ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->assertSame('en', $this->owner->fresh()->locale);
    }

    public function test_the_inbox_and_its_scripts_speak_chinese(): void
    {
        $this->owner->forceFill(['locale' => 'zh'])->save();
        $page = $this->actingAs($this->owner)->get('/admin')->assertOk();
        $page->assertSee('请选择一个聊天')->assertSee('回复客户')->assertSee('window.I18N', false)->assertSee('标为已解决', false);
    }

    public function test_scripts_get_no_dictionary_in_english(): void
    {
        $this->actingAs($this->owner)->get('/admin/today')->assertSee('window.I18N = []', false);
    }

    public function test_every_marked_phrase_has_a_chinese_translation_with_the_same_placeholders(): void
    {
        $zh = json_decode(file_get_contents(lang_path('zh.json')), true);
        $js = json_decode(file_get_contents(lang_path('zh.js.json')), true);
        $missing = [];
        $bad = [];

        $files = array_merge(
            glob(resource_path('views/admin/*.blade.php')) ?: [],
            glob(resource_path('views/pagination/*.php')) ?: [],
            glob(app_path('Http/Controllers/*.php')) ?: [],
            [app_path('Services/DailySummary.php')],
        );
        foreach ($files as $file) {
            preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $key = str_replace("\\'", "'", $key);
                if (! isset($zh[$key])) {
                    $missing[] = basename($file).': '.$key;
                } elseif ($this->tokens($key) !== $this->tokens($zh[$key])) {
                    $bad[] = $key;
                }
            }
        }
        foreach (glob(public_path('js/*.js')) ?: [] as $file) {
            preg_match_all("/__t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $key = str_replace("\\'", "'", $key);
                if (basename($file) !== 'i18n.js' && ! isset($js[$key])) {
                    $missing[] = basename($file).' (script): '.$key;
                } elseif (isset($js[$key]) && $this->tokens($key) !== $this->tokens($js[$key])) {
                    $bad[] = $key;
                }
            }
        }

        $this->assertSame([], $missing, "These phrases have no Chinese yet:\n".implode("\n", $missing));
        $this->assertSame([], $bad, 'Placeholders differ for: '.implode(', ', $bad));
    }

    public function test_phrases_chosen_while_the_page_runs_are_translated_too(): void
    {
        $zh = json_decode(file_get_contents(lang_path('zh.json')), true);
        $need = ['Owner', 'Manager', 'Staff', 'Price question', 'Booking help', 'Cancel or change', 'Location', 'Payment', 'Aftercare', 'Complaint',
            'confirmed', 'completed', 'no show', 'cancelled', 'paid', 'pending', 'refunded', 'open', 'resolved',
            'Overdue', 'Today', 'Tomorrow', 'This week', 'Later', 'No date', 'All time', 'This month', 'Last month', 'Last 30 days', 'Last 90 days', 'This year',
            'Cash', 'Card', 'Bank transfer or QR', 'Other', 'Ready to book', 'Interested', 'Just browsing', 'Website'];
        $missing = array_values(array_filter($need, fn ($k) => ! isset($zh[$k])));

        $this->assertSame([], $missing, 'No Chinese for: '.implode(', ', $missing));
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        preg_match_all('/:[a-z]+/', $text, $m);
        $t = $m[0];
        sort($t);

        return $t;
    }
}
