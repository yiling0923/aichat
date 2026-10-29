<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_switch_sets_a_cookie_and_goes_back(): void
    {
        $this->from('/login')->get('/lang/zh')->assertRedirect('/login')->assertCookie('locale', 'zh');
        $this->get('/lang/fr')->assertNotFound();
    }

    public function test_public_pages_follow_the_cookie(): void
    {
        $zh = ['locale' => 'zh'];

        $this->withCookies($zh)->get('/login')->assertOk()->assertSee('欢迎回来。')->assertSee('登录')->assertDontSee('Welcome back.');
        $this->withCookies($zh)->get('/forgot-password')->assertOk()->assertSee('忘记密码了？');
        $this->withCookies($zh)->get('/signup')->assertOk()->assertSee('开始您的免费试用');
        $this->withCookies($zh)->get('/')->assertOk()->assertSee('把每一次聊天变成预约')->assertSee('常见问题');
        $this->withCookies($zh)->get('/terms')->assertOk()->assertSee('服务条款')->assertSee('使用本服务')->assertSee('2026年10月28日');
        $this->withCookies($zh)->get('/privacy')->assertOk()->assertSee('隐私声明')->assertSee('我们保存什么');
    }

    public function test_english_stays_the_default(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back.');
        $this->get('/terms')->assertOk()->assertSee('28 October 2026');
    }

    public function test_emails_use_the_language_of_the_person(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $business = \App\Models\Business::factory()->create(['slug' => 'em-co']);
        $owner = \App\Models\User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $owner->forceFill(['locale' => 'zh'])->save();

        \App\Support\Lang::as('zh', function () use ($business, $owner) {
            $mail = new \App\Mail\WaitingChatMail($business, 'Amy', 7, false, 'http://x.test/inbox');
            $this->assertStringContainsString('已经 7 分钟没有人回答', $mail->render());
            $this->assertStringContainsString('已等待 7 分钟', $mail->build()->subject);
        });
        $this->assertSame('en', app()->getLocale());
        $this->assertStringContainsString('Nobody has answered for 7 minutes', (new \App\Mail\WaitingChatMail($business, 'Amy', 7, false, 'http://x.test/inbox'))->render());
    }

    public function test_platform_console_follows_the_cookie(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'platform_owner', 'business_id' => null]);

        $this->withCookies(['locale' => 'zh'])->actingAs($admin)->get('/platform')->assertOk()->assertSee('平台后台')->assertSee('试用中')->assertDontSee('Platform console');
    }
}
