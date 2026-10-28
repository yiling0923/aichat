<?php

namespace Tests\Feature;

use App\Ai\AiAssistant;
use App\Billing\Billing;
use App\Billing\Plans;
use App\Mail\LimitReachedMail;
use App\Mail\VerifyEmailMail;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Customer;
use App\Models\KnowledgeEntry;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function signup(array $over = [])
    {
        return $this->post('/signup', $over + [
            'business_name' => 'Glow Studio', 'name' => 'Mia', 'email' => 'mia@example.com',
            'password' => 'secret-pass-1', 'terms' => '1',
        ]);
    }

    private function trialBusiness(array $over = []): array
    {
        $b = Business::factory()->create($over + ['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(10)]);
        $u = User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);

        return [$b, $u];
    }

    public function test_signup_creates_business_owner_trial_and_sends_email(): void
    {
        Mail::fake();

        $this->signup()->assertRedirect(route('verification.notice'));

        $b = Business::query()->where('slug', 'glow-studio')->firstOrFail();
        $this->assertSame('trial', $b->plan);
        $this->assertTrue($b->trial_ends_at->isFuture());
        $owner = User::query()->where('email', 'mia@example.com')->firstOrFail();
        $this->assertSame('owner', $owner->role);
        $this->assertNull($owner->email_verified_at);
        Mail::assertSent(VerifyEmailMail::class, fn ($m) => $m->hasTo('mia@example.com'));

        // The menu starts neutral, not as a made-up clinic.
        $flow = app(CurrentBusiness::class)->runAs($b, fn () => \App\Models\BotFlow::query()->first());
        $this->assertStringNotContainsString('Hydrating', json_encode($flow->published));
    }

    public function test_unverified_owner_cannot_use_the_admin_until_the_link_is_opened(): void
    {
        Mail::fake();
        $this->signup();
        $this->get('/admin/today')->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'mia@example.com')->first();
        $mail = new VerifyEmailMail($user);
        $this->get('/email/verify/'.$user->id.'/wronghash?'.parse_url($mail->link, PHP_URL_QUERY))->assertForbidden();
        $this->get($mail->link)->assertRedirect(route('setup'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get('/admin/today')->assertOk();
    }

    public function test_signup_rejects_duplicates_bad_input_and_bots(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'mia@example.com']);

        $this->signup()->assertSessionHasErrors('email');
        $this->signup(['email' => 'new@example.com', 'terms' => null])->assertSessionHasErrors('terms');
        $this->signup(['email' => 'new@example.com', 'password' => 'short'])->assertSessionHasErrors('password');
        $this->signup(['email' => 'bot@example.com', 'website' => 'http://spam'])->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_signup_is_rate_limited_per_address(): void
    {
        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->signup(['business_name' => 'Shop '.$i, 'email' => "u$i@example.com"])->assertRedirect();
            auth()->logout();
        }
        $this->signup(['business_name' => 'Shop X', 'email' => 'x@example.com'])->assertStatus(429);
    }

    public function test_slugs_stay_unique(): void
    {
        Mail::fake();
        $this->signup();
        auth()->logout();
        $this->signup(['email' => 'two@example.com']);

        $this->assertSame(['glow-studio', 'glow-studio-2'], Business::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_two_new_businesses_cannot_see_each_others_data(): void
    {
        Mail::fake();
        [$a, $ua] = $this->trialBusiness(['slug' => 'aaa']);
        [$b, $ub] = $this->trialBusiness(['slug' => 'bbb']);
        app(CurrentBusiness::class)->runAs($a, fn () => Customer::create(['name' => 'Alice Secret', 'language' => 'en']));
        app(CurrentBusiness::class)->runAs($a, fn () => Service::create(['name' => ['en' => 'Only A'], 'duration_min' => 30, 'price' => 10]));

        $this->actingAs($ub)->get('/admin/customers')->assertOk()->assertDontSee('Alice Secret');
        $this->actingAs($ub)->get('/admin/booking-setup')->assertOk()->assertDontSee('Only A');
        $this->actingAs($ua)->get('/admin/customers')->assertSee('Alice Secret');
    }

    public function test_setup_drafts_from_price_lines_and_applies_only_after_approval(): void
    {
        [$b, $owner] = $this->trialBusiness(['slug' => 'glow']);
        $this->actingAs($owner);

        $this->post('/admin/setup/draft', ['about' => "Facial - RM 188 (60 min)\nLaser: RM 450 45 min\nWe love our customers!", 'address' => '1 Test Road'])->assertRedirect();
        $draft = $b->fresh()->settings['setup_draft'];
        $this->assertSame('simple', $draft['source']);
        $this->assertCount(2, $draft['services']);
        $this->assertSame(0, Service::query()->withoutGlobalScopes()->where('business_id', $b->id)->count());   // not saved yet

        $this->post('/admin/setup/approve', [
            'address' => '1 Test Road',
            'services' => [
                ['include' => 1, 'name_en' => 'Facial', 'name_zh' => '', 'duration_min' => 60, 'price' => 188],
                ['name_en' => 'Laser', 'duration_min' => 45, 'price' => 450],   // not ticked
            ],
            'faqs' => [['include' => 1, 'title' => 'Parking', 'answer' => 'Free for two hours.']],
        ])->assertRedirect();

        app(CurrentBusiness::class)->runAs($b, function () {
            $this->assertSame(['Facial'], Service::query()->get()->map->label('en')->all());
            $this->assertSame(1, KnowledgeEntry::query()->count());
            $this->assertSame(1, Practitioner::query()->count());
            $flow = \App\Models\BotFlow::query()->first();
            $this->assertStringContainsString('Facial: RM 188', $flow->published['nodes']['prices']['text']['en']);
            $this->assertStringContainsString('1 Test Road', $flow->published['nodes']['location']['text']['en']);
        });
        $this->assertArrayNotHasKey('setup_draft', $b->fresh()->settings);
    }

    public function test_setup_draft_can_use_the_ai_and_counts_as_a_call(): void
    {
        config(['aichat.ai.provider' => 'fake']);
        [$b, $owner] = $this->trialBusiness();
        $this->actingAs($owner)->post('/admin/setup/draft', ['about' => 'Haircut - RM 40 (30 min)'])->assertRedirect();

        $this->assertSame('ai', $b->fresh()->settings['setup_draft']['source']);
        $this->assertSame(1, AiUsage::query()->withoutGlobalScopes()->where('business_id', $b->id)->where('purpose', 'setup')->count());
    }

    public function test_only_the_owner_sees_setup_billing_and_never_the_platform(): void
    {
        [$b] = $this->trialBusiness();
        $staff = User::factory()->create(['business_id' => $b->id, 'role' => 'staff']);

        $this->actingAs($staff);
        $this->get('/admin/setup')->assertForbidden();
        $this->get('/admin/billing')->assertForbidden();
        $this->get('/platform')->assertForbidden();
    }

    public function test_ai_pauses_at_the_plan_limit_the_owner_is_told_and_chat_still_works(): void
    {
        Mail::fake();
        config(['aichat.ai.provider' => 'fake']);
        [$b, $owner] = $this->trialBusiness(['slug' => 'lim', 'settings' => ['ai' => ['mode' => 'auto']]]);
        $limit = (int) config('plans.plans.trial.limits.ai');
        app(CurrentBusiness::class)->runAs($b, function () use ($limit) {
            for ($i = 0; $i < $limit; $i++) {
                AiUsage::create(['purpose' => 'reply', 'provider' => 'fake', 'status' => 'ok', 'created_at' => now()]);
            }
        });

        $ai = app(AiAssistant::class);
        $this->assertNull($ai->preview($b, 'what is the pricing'));
        $this->assertStringContainsString('limit of your plan', (string) $ai->lastNote);
        $ai->preview($b, 'again');
        Mail::assertSent(LimitReachedMail::class, 1);   // once a month only

        // The visitor chat still answers (menu bot).
        $this->postJson('/api/chat/lim/messages', ['text' => 'hello'])->assertSuccessful();
    }

    public function test_expired_trial_pauses_the_ai_but_not_the_admin(): void
    {
        config(['aichat.ai.provider' => 'fake']);
        [$b, $owner] = $this->trialBusiness(['settings' => ['ai' => ['mode' => 'auto']], 'trial_ends_at' => now()->subDay()]);

        $this->assertSame('trial_expired', app(Plans::class)->state($b->fresh()));
        $this->assertNull(app(AiAssistant::class)->preview($b->fresh(), 'what is the pricing'));
        $this->actingAs($owner)->get('/admin/today')->assertOk()->assertSee('AI is paused');
    }

    public function test_sample_business_without_a_plan_has_no_limits(): void
    {
        $b = Business::factory()->create();
        $this->assertSame('internal', app(Plans::class)->state($b));
        $this->assertNull(app(Plans::class)->limit($b, 'ai'));
        $this->assertTrue(app(Plans::class)->canAdd($b, 'staff'));
    }

    public function test_team_and_practitioner_limits_follow_the_plan(): void
    {
        [$b, $owner] = $this->trialBusiness();   // trial: 2 staff, 2 practitioners
        $this->actingAs($owner);

        $this->post('/admin/settings/team', ['name' => 'Two', 'email' => 'two@x.com', 'password' => 'password1', 'role' => 'staff'])->assertSessionHasNoErrors();
        $this->post('/admin/settings/team', ['name' => 'Three', 'email' => 'three@x.com', 'password' => 'password1', 'role' => 'staff'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'three@x.com']);

        app(Billing::class)->subscribe($b, 'starter');
        $owner->unsetRelation('business');
        $this->post('/admin/settings/team', ['name' => 'Three', 'email' => 'three@x.com', 'password' => 'password1', 'role' => 'staff'])->assertSessionHasNoErrors();

        $this->post('/admin/booking-setup/practitioners', ['name' => 'P1'])->assertSessionHasNoErrors();
        $this->post('/admin/booking-setup/practitioners', ['name' => 'P2'])->assertSessionHasNoErrors();
        $this->post('/admin/booking-setup/practitioners', ['name' => 'P3'])->assertSessionHasNoErrors();
        $this->post('/admin/booking-setup/practitioners', ['name' => 'P4'])->assertSessionHasErrors('name');
    }

    public function test_subscribe_records_a_payment_and_renews_or_fails(): void
    {
        [$b] = $this->trialBusiness();
        $billing = app(Billing::class);

        $this->assertTrue($billing->subscribe($b, 'starter')['ok']);
        $b->refresh();
        $this->assertSame('starter', $b->plan);
        $this->assertSame('active', app(Plans::class)->state($b));
        $this->assertSame(2900, Payment::query()->first()->amount_cents);

        $b->forceFill(['current_period_ends_at' => now()->subHour()])->save();
        $this->assertSame(['renewed' => 1, 'failed' => 0], $billing->renewDue());
        $this->assertSame(2, Payment::query()->count());

        $b->refresh()->forceFill(['current_period_ends_at' => now()->subHour(), 'settings' => ['billing_test_fail' => true]])->save();
        $this->assertSame(['renewed' => 0, 'failed' => 1], $billing->renewDue());
        $this->assertSame('past_due', app(Plans::class)->state($b->fresh()));
        $this->assertSame(0, app(Plans::class)->limit($b->fresh(), 'ai'));
    }

    public function test_failed_first_payment_keeps_the_trial(): void
    {
        [$b, $owner] = $this->trialBusiness(['settings' => ['billing_test_fail' => true]]);
        $this->actingAs($owner)->post('/admin/billing/subscribe', ['plan' => 'growth'])->assertSessionHasErrors('plan');
        $this->assertSame('trial', $b->fresh()->plan);
    }

    public function test_cancelled_plan_works_until_the_paid_month_ends(): void
    {
        [$b, $owner] = $this->trialBusiness();
        app(Billing::class)->subscribe($b, 'growth');
        $this->actingAs($owner)->post('/admin/billing/cancel')->assertRedirect();

        $this->assertSame('active', app(Plans::class)->state($b->fresh()));
        $b->forceFill(['current_period_ends_at' => now()->subDay()])->save();
        $this->assertSame('canceled', app(Plans::class)->state($b->fresh()));
        $this->assertSame(['renewed' => 0, 'failed' => 0], app(Billing::class)->renewDue());
    }

    public function test_billing_page_shows_usage(): void
    {
        [$b, $owner] = $this->trialBusiness();
        $this->actingAs($owner)->get('/admin/billing')->assertOk()->assertSee('Free trial')->assertSee('Test mode')->assertSee('Starter');
    }

    public function test_platform_owner_sees_everything_and_can_pause_and_resume(): void
    {
        [$b, $owner] = $this->trialBusiness(['slug' => 'pausable', 'name' => 'Pausable Co']);
        app(Billing::class)->subscribe($b, 'growth');
        $admin = User::factory()->create(['role' => 'platform_owner', 'business_id' => null]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('platform.index'));
        $this->get('/platform')->assertOk()->assertSee('Pausable Co')->assertSee('79');
        $this->get('/admin/today')->assertRedirect(route('platform.index'));

        $this->post('/platform/businesses/'.$b->id.'/pause', ['reason' => 'Spam'])->assertRedirect();
        $this->assertSame('paused', $b->fresh()->status);
        auth()->logout();
        $this->get('/chat/pausable')->assertNotFound();
        $this->actingAs($owner)->get('/admin/today')->assertForbidden();

        $this->actingAs($admin)->post('/platform/businesses/'.$b->id.'/resume')->assertRedirect();
        $this->assertSame('active', $b->fresh()->status);
        $owner->unsetRelation('business');
        $this->actingAs($owner)->get('/admin/today')->assertOk();
    }

    public function test_legal_pages_and_home_link_to_signup(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms of service');
        $this->get('/privacy')->assertOk()->assertSee('Privacy notice');
        $this->get('/')->assertOk()->assertSee('Start free trial')->assertSee('Pricing')->assertSee('Terms');
        $this->get('/signup')->assertOk();
    }

    public function test_make_platform_owner_command(): void
    {
        $this->artisan('aichat:make-platform-owner', ['email' => 'boss@example.com', '--password' => 'longpassword'])->assertSuccessful();
        $u = User::query()->where('email', 'boss@example.com')->first();
        $this->assertTrue($u->isPlatformOwner());
        $this->assertNull($u->business_id);
    }
}
