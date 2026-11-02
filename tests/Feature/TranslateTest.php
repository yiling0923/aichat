<?php

namespace Tests\Feature;

use App\Ai\AiProvider;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslateTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function translate(array $body)
    {
        return $this->actingAs($this->owner)->postJson('/admin/api/translate', $body);
    }

    public function test_it_says_so_when_the_ai_is_switched_off(): void
    {
        config(['aichat.ai.provider' => 'off']);

        $this->translate(['text' => 'Hello', 'from' => 'en', 'to' => 'zh'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'switched off'));
        $this->assertSame(0, AiUsage::query()->withoutGlobalScopes()->count());
    }

    public function test_it_translates_keeps_placeholders_and_counts_one_ai_call(): void
    {
        config(['aichat.ai.provider' => 'fake']);

        $r = $this->translate(['text' => "Hi {name},\nwelcome to {business}!", 'from' => 'en', 'to' => 'zh'])->assertOk();
        $this->assertStringContainsString('{name}', $r->json('translation'));
        $this->assertStringContainsString('{business}', $r->json('translation'));
        $this->assertSame(1, AiUsage::query()->withoutGlobalScopes()->where('purpose', 'translate')->count());
    }

    public function test_a_translation_that_changes_a_placeholder_is_refused(): void
    {
        app()->instance(AiProvider::class, new class implements AiProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function complete(string $system, array $messages, array $schema, int $maxTokens): array
            {
                return ['text' => json_encode(['translation' => '您好 {姓名}，欢迎光临']), 'input_tokens' => 5, 'output_tokens' => 5, 'model' => null];
            }
        });

        $this->translate(['text' => 'Hi {name}, welcome', 'from' => 'en', 'to' => 'zh'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'placeholder'));
    }

    public function test_bad_input_the_monthly_limit_and_permissions(): void
    {
        config(['aichat.ai.provider' => 'fake']);

        $this->translate(['text' => '', 'from' => 'en', 'to' => 'zh'])->assertStatus(422);
        $this->translate(['text' => 'Hello', 'from' => 'en', 'to' => 'en'])->assertStatus(422);
        $this->translate(['text' => 'Hello', 'from' => 'fr', 'to' => 'zh'])->assertStatus(422);
        $this->translate(['text' => str_repeat('a', 1501), 'from' => 'en', 'to' => 'zh'])->assertStatus(422);

        // The monthly AI limit applies to translations too.
        $this->business->forceFill(['settings' => ['ai' => ['mode' => 'off', 'monthly_cap' => 0]]])->save();
        $this->owner->unsetRelation('business');
        $this->translate(['text' => 'Hello', 'from' => 'en', 'to' => 'zh'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'limit'));
        $this->business->forceFill(['settings' => []])->save();
        $this->owner->unsetRelation('business');

        // A staff member without menu, AI or saved-reply access cannot use it; with one of them they can.
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->actingAs($staff)->postJson('/admin/api/translate', ['text' => 'Hello', 'from' => 'en', 'to' => 'zh'])->assertForbidden();
        $staff->forceFill(['permissions' => ['library']])->save();
        $this->actingAs($staff->fresh())->postJson('/admin/api/translate', ['text' => 'Hello', 'from' => 'en', 'to' => 'zh'])->assertOk();
        $this->postJson('/admin/api/translate', ['text' => 'Hello', 'from' => 'en', 'to' => 'zh'])->assertOk();
    }

    public function test_the_pages_load_the_translate_script(): void
    {
        foreach (['/admin/menu', '/admin/ai', '/admin/library'] as $url) {
            $this->actingAs($this->owner)->get($url)->assertOk()->assertSee('js/translate.js', false)->assertSee('name="translate-url"', false);
        }
    }
}
