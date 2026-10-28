<?php

namespace Tests\Feature;

use App\Billing\Billing;
use App\Billing\Plans;
use App\Billing\Stripe;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Invite;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\Backup;
use App\Support\CurrentBusiness;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function signup(array $over = [])
    {
        return $this->post('/signup', $over + ['business_name' => 'Glow Studio', 'name' => 'Mia', 'email' => 'mia@example.com', 'password' => 'secret-pass-1', 'terms' => '1']);
    }

    private function owner(array $business = []): User
    {
        $b = Business::factory()->create($business + ['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(5)]);

        return User::factory()->create(['business_id' => $b->id, 'role' => 'owner']);
    }

    // ---- password reset ----

    public function test_password_reset_end_to_end(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->post('/forgot-password', ['email' => 'a@example.com'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $this->post('/reset-password', ['token' => $n->token, 'email' => $user->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertRedirect(route('login'));

            return true;
        });

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'brand-new-pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_forgot_password_does_not_reveal_unknown_emails_and_bad_tokens_fail(): void
    {
        Notification::fake();
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');
        Notification::assertNothingSent();

        $user = User::factory()->create();
        $this->post('/reset-password', ['token' => 'wrong', 'email' => $user->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertSessionHasErrors('email');
        $this->get('/login')->assertSee('Forgot password?');
    }

    // ---- invite-only beta ----

    public function test_invite_only_signup_needs_a_valid_unused_code(): void
    {
        Mail::fake();
        config(['plans.signup_mode' => 'invite']);

        $this->get('/signup')->assertSee('Invitation code');
        $this->signup()->assertSessionHasErrors('invite');
        $this->signup(['invite' => 'WRONG'])->assertSessionHasErrors('invite');
        $this->assertDatabaseCount('businesses', 0);

        $invite = Invite::generate();
        $this->signup(['invite' => strtolower($invite->code)])->assertRedirect(route('verification.notice'));
        $this->assertNotNull($invite->fresh()->used_at);
        $this->assertNotNull($invite->fresh()->business_id);

        auth()->logout();
        $this->signup(['invite' => $invite->code, 'email' => 'two@example.com', 'business_name' => 'Other'])->assertSessionHasErrors('invite');   // already used
    }

    public function test_an_invite_for_one_email_only_works_for_that_email(): void
    {
        Mail::fake();
        config(['plans.signup_mode' => 'invite']);
        $invite = Invite::generate('vip@example.com');

        $this->signup(['invite' => $invite->code, 'email' => 'someone@example.com'])->assertSessionHasErrors('invite');
        $this->signup(['invite' => $invite->code, 'email' => 'VIP@example.com'])->assertRedirect(route('verification.notice'));
    }

    public function test_open_mode_ignores_codes_and_platform_owner_can_make_invites(): void
    {
        Mail::fake();
        $this->signup()->assertRedirect(route('verification.notice'));

        auth()->logout();
        $admin = User::factory()->create(['role' => 'platform_owner', 'business_id' => null]);
        $this->actingAs($admin)->post('/platform/invites', ['note' => 'Dr Lee'])->assertSessionHas('status');
        $this->assertSame(1, Invite::query()->count());
        $this->get('/platform')->assertSee(Invite::first()->code);

        $this->actingAs($this->owner())->post('/platform/invites')->assertForbidden();
    }

    // ---- headers, health, preflight ----

    public function test_security_headers_and_chat_can_be_framed(): void
    {
        $this->get('/login')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Referrer-Policy');

        Business::factory()->create(['slug' => 'demo']);
        $this->get('/chat/demo')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeaderMissing('X-Frame-Options');
    }

    public function test_health_reports_database_and_scheduler(): void
    {
        $this->getJson('/health')->assertStatus(503)->assertJson(['ok' => false, 'database' => true, 'scheduler' => false]);

        Cache::put('scheduler-heartbeat', now(), 600);
        $this->getJson('/health')->assertOk()->assertJson(['ok' => true]);

        Cache::put('scheduler-heartbeat', now()->subMinutes(20), 600);
        $this->getJson('/health')->assertStatus(503);
    }

    public function test_preflight_flags_unsafe_settings_and_passes_a_good_setup(): void
    {
        $this->artisan('aichat:preflight')->assertFailed();

        config(['app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://chat.example.com', 'mail.default' => 'smtp']);
        Cache::put('scheduler-heartbeat', now(), 600);
        $this->artisan('aichat:preflight')->expectsOutputToContain('Scheduler')->assertSuccessful();

        config(['app.debug' => true]);
        $this->artisan('aichat:preflight')->assertFailed();
    }

    // ---- backups ----

    public function test_backup_command_line_prune_and_sqlite_copy(): void
    {
        $backup = new Backup;
        $cmd = $backup->dumpCommand(['host' => 'db', 'port' => 3307, 'username' => 'u', 'password' => 'secret', 'database' => 'app']);
        $this->assertSame('mysqldump', $cmd[0]);
        $this->assertContains('app', $cmd);
        $this->assertNotContains('secret', $cmd);   // the password is never on the command line

        $dir = sys_get_temp_dir().'/aichat-bk-'.uniqid();
        $db = $dir.'/test.sqlite';
        File::ensureDirectoryExists($dir);
        File::put($db, 'data');
        config(['database.connections.bk' => ['driver' => 'sqlite', 'database' => $db]]);

        $b = new class($dir) extends Backup
        {
            public function __construct(private string $d) {}

            public function directory(): string
            {
                return $this->d.'/out';
            }
        };
        $original = config('database.default');
        config(['database.default' => 'bk']);
        try {
            $file = $b->run();
        } finally {
            config(['database.default' => $original]);
        }
        $this->assertFileExists($file);

        foreach (['aichat-20200101-000001.sqlite', 'aichat-20200102-000001.sqlite', 'aichat-20200103-000001.sqlite'] as $f) {
            File::put($b->directory().'/'.$f, 'x');
        }
        $this->assertSame(2, $b->prune(2));
        $this->assertCount(2, File::glob($b->directory().'/aichat-*'));
        File::deleteDirectory($dir);
    }

    // ---- Stripe ----

    private function stripeOn(): void
    {
        config(['plans.provider' => 'stripe', 'services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_x',
            'services.stripe.prices' => ['starter' => 'price_s', 'growth' => 'price_g']]);
    }

    private function webhook(array $event, ?string $secret = 'whsec_x', ?int $time = null)
    {
        $payload = json_encode($event);
        $time ??= time();
        $sig = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, $secret);

        return $this->call('POST', '/webhook/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    public function test_stripe_webhook_rejects_bad_or_old_signatures(): void
    {
        $this->stripeOn();
        $event = ['type' => 'invoice.paid', 'data' => ['object' => []]];

        $this->webhook($event, 'wrong_secret')->assertStatus(400);
        $this->webhook($event, 'whsec_x', time() - 4000)->assertStatus(400);
        $this->postJson('/webhook/stripe', $event)->assertStatus(400);
        $this->webhook($event)->assertOk();
    }

    public function test_subscribing_sends_the_owner_to_stripe_checkout(): void
    {
        $this->stripeOn();
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/c/pay/abc'])]);
        $owner = $this->owner();

        $this->actingAs($owner)->post('/admin/billing/subscribe', ['plan' => 'starter'])->assertRedirect('https://checkout.stripe.com/c/pay/abc');

        Http::assertSent(fn ($r) => $r['line_items'][0]['price'] === 'price_s' && $r['client_reference_id'] == $owner->business_id && $r['customer_email'] === $owner->email && $r['mode'] === 'subscription');
        $this->assertSame('trial', $owner->business->fresh()->plan);   // nothing changes until Stripe confirms
    }

    public function test_a_missing_price_shows_a_message_instead_of_crashing(): void
    {
        $this->stripeOn();
        config(['services.stripe.prices' => ['starter' => null, 'growth' => null]]);
        $this->actingAs($this->owner())->post('/admin/billing/subscribe', ['plan' => 'growth'])->assertSessionHasErrors('plan');
    }

    public function test_stripe_events_drive_the_plan(): void
    {
        $this->stripeOn();
        $owner = $this->owner();
        $b = $owner->business;

        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => (string) $b->id, 'customer' => 'cus_1', 'subscription' => 'sub_1', 'metadata' => ['plan' => 'growth']]]])->assertOk();
        $b->refresh();
        $this->assertSame(['growth', 'active'], [$b->plan, $b->subscription_status]);
        $this->assertSame('active', app(Plans::class)->state($b));

        $end = now()->addMonth()->timestamp;
        $invoice = ['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_1', 'customer' => 'cus_1', 'amount_paid' => 7900, 'currency' => 'usd', 'lines' => ['data' => [['period' => ['end' => $end]]]]]]];
        $this->webhook($invoice)->assertOk();
        $this->webhook($invoice)->assertOk();   // Stripe retries: no double entry
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame([7900, 'USD', 'stripe'], Payment::first()->only(['amount_cents', 'currency', 'provider']) + [] === [] ? [] : array_values(Payment::first()->only(['amount_cents', 'currency', 'provider'])));
        $this->assertSame($end, $b->fresh()->current_period_ends_at->timestamp);

        $this->webhook(['type' => 'invoice.payment_failed', 'data' => ['object' => ['customer' => 'cus_1']]]);
        $this->assertSame('past_due', app(Plans::class)->state($b->fresh()));
        $this->assertSame(0, app(Plans::class)->limit($b->fresh(), 'ai'));

        $this->webhook($invoice + []);   // an old invoice again changes nothing
        $this->webhook(['type' => 'customer.subscription.deleted', 'data' => ['object' => ['customer' => 'cus_1']]]);
        $this->assertSame('canceled', app(Plans::class)->state($b->fresh()));
    }

    public function test_stripe_businesses_are_not_renewed_locally_and_cancel_calls_stripe(): void
    {
        $this->stripeOn();
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::response(['id' => 'sub_1'])]);
        $owner = $this->owner();
        $owner->business->forceFill(['plan' => 'starter', 'subscription_status' => 'active', 'current_period_ends_at' => now()->subDay(), 'settings' => ['stripe' => ['customer' => 'cus_1', 'subscription' => 'sub_1']]])->save();

        $this->assertSame(['renewed' => 0, 'failed' => 0], app(Billing::class)->renewDue());
        $this->assertSame(0, Payment::query()->count());

        $this->actingAs($owner)->post('/admin/billing/cancel')->assertRedirect();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/subscriptions/sub_1') && $r['cancel_at_period_end'] === 'true');
        $this->assertSame('canceled', $owner->business->fresh()->subscription_status);
    }

    // ---- customer privacy ----

    public function test_a_manager_can_export_and_erase_a_customer_and_future_bookings_are_cancelled(): void
    {
        $owner = $this->owner();
        $b = $owner->business;
        $cur = app(CurrentBusiness::class);

        [$customer, $booking, $convId] = $cur->runAs($b, function () {
            $c = Customer::create(['name' => 'Erin Erase', 'phone' => '60123', 'language' => 'en']);
            $conv = Conversation::startFor('web');
            $conv->forceFill(['customer_id' => $c->id])->save();
            $conv->addMessage('in', 'customer', 'my secret question');
            $p = Practitioner::create(['name' => 'P']);
            $s = Service::create(['name' => ['en' => 'S'], 'duration_min' => 30, 'price' => 10]);
            $bk = Booking::create(['customer_id' => $c->id, 'service_id' => $s->id, 'practitioner_id' => $p->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addMinutes(30), 'status' => 'confirmed']);

            return [$c, $bk, $conv->id];
        });

        $export = $this->actingAs($owner)->get('/admin/customers/'.$customer->id.'/export')->assertOk()->assertHeader('content-disposition');
        $this->assertStringContainsString('my secret question', $export->streamedContent());

        $this->post('/admin/customers/'.$customer->id.'/erase')->assertRedirect(route('customers'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $convId]);
        $this->assertSame(0, Message::query()->withoutGlobalScopes()->where('conversation_id', $convId)->count());
        $this->assertSame('cancelled', Booking::query()->withoutGlobalScopes()->find($booking->id)->status);
    }

    public function test_plain_staff_cannot_erase_or_export_and_other_businesses_are_untouched(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $theirs = app(CurrentBusiness::class)->runAs($other->business, fn () => Customer::create(['name' => 'Theirs', 'language' => 'en']));
        $staff = User::factory()->create(['business_id' => $owner->business_id, 'role' => 'staff']);

        $this->actingAs($staff)->post('/admin/customers/'.$theirs->id.'/erase')->assertForbidden();
        $this->actingAs($staff)->get('/admin/customers/'.$theirs->id.'/export')->assertForbidden();
        $this->actingAs($owner)->post('/admin/customers/'.$theirs->id.'/erase')->assertNotFound();
        $this->assertDatabaseHas('customers', ['id' => $theirs->id]);
    }
}
