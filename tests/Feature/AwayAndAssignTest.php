<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwayAndAssignTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        // Tuesday 2026-11-03, 23:00 in Kuala Lumpur: closed (open Mon-Fri 10-19).
        Carbon::setTestNow('2026-11-03 15:00:00');
        $this->business = Business::factory()->create(['slug' => 'demo', 'timezone' => 'Asia/Kuala_Lumpur', 'settings' => ['hours' => ['mon' => ['10:00', '19:00'], 'tue' => ['10:00', '19:00'], 'wed' => ['10:00', '19:00']]]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(?string $text, ?string $token = null, ?string $option = null)
    {
        return $this->postJson(route('chat.send', 'demo'), ['text' => $text, 'option_id' => $option, 'token' => $token]);
    }

    private function botTexts($r): string
    {
        return implode(' | ', array_column(array_filter($r->json('messages'), fn ($m) => $m['from'] === 'bot'), 'text'));
    }

    public function test_a_chat_that_starts_while_closed_is_told_when_you_are_back_once(): void
    {
        $first = $this->say('hi');
        $this->assertStringContainsString('We are closed right now and will be back Wednesday at 10:00', $this->botTexts($first));

        $again = $this->say('English', $first->json('token'), 'lang_en');
        $this->assertStringNotContainsString('We are closed', $this->botTexts($again));
    }

    public function test_the_notice_can_be_switched_off_and_is_not_sent_while_open(): void
    {
        $this->business->update(['settings' => array_merge($this->business->settings, ['away_notice' => false])]);
        $this->assertStringNotContainsString('closed', $this->botTexts($this->say('hi')));

        $this->business->update(['settings' => array_merge($this->business->settings, ['away_notice' => true])]);
        Carbon::setTestNow('2026-11-03 04:00:00');   // 12:00 in Kuala Lumpur: open, no notice
        $this->assertStringNotContainsString('closed', $this->botTexts($this->say('hi')));
    }

    public function test_new_chats_that_need_a_person_go_to_the_least_busy_active_team_member(): void
    {
        $this->business->update(['settings' => array_merge($this->business->settings, ['auto_assign' => true])]);
        $a = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $b = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff', 'active' => false]);

        foreach (range(1, 4) as $_) {
            $token = $this->say('hi')->json('token');
            $this->say('English', $token, 'lang_en');
            $this->say('x', $token, 'staff');
        }

        $count = app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::query()->whereNotNull('assigned_to')->selectRaw('assigned_to, count(*) n')->groupBy('assigned_to')->pluck('n', 'assigned_to')->all());
        $this->assertSame([$a->id => 2, $b->id => 2], $count);
    }

    public function test_nobody_is_assigned_when_the_setting_is_off(): void
    {
        User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $token = $this->say('hi')->json('token');
        $this->say('English', $token, 'lang_en');
        $this->say('x', $token, 'staff');

        $this->assertSame(0, app(CurrentBusiness::class)->runAs($this->business, fn () => Conversation::query()->whereNotNull('assigned_to')->count()));
    }
}
