<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifier;
use App\Services\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PushAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'push-co']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function sub(array $over = []): array
    {
        return array_merge(['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'pk', 'auth' => 'au']], $over);
    }

    private function configure(): void
    {
        config(['aichat.push.public' => 'pub', 'aichat.push.private' => 'priv']);
    }

    public function test_subscribe_saves_device_and_updates_instead_of_duplicating(): void
    {
        $this->actingAs($this->owner)->postJson('/admin/api/push/subscribe', $this->sub())->assertOk();
        $this->actingAs($this->owner)->postJson('/admin/api/push/subscribe', $this->sub())->assertOk();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($this->business->id, PushSubscription::first()->business_id);
    }

    public function test_subscribe_rejects_non_https_endpoint(): void
    {
        $this->actingAs($this->owner)->postJson('/admin/api/push/subscribe', $this->sub(['endpoint' => 'http://x.test/a']))->assertStatus(422);
    }

    public function test_unsubscribe_removes_only_own_device(): void
    {
        $other = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->actingAs($other)->postJson('/admin/api/push/subscribe', $this->sub())->assertOk();

        $this->actingAs($this->owner)->postJson('/admin/api/push/unsubscribe', ['endpoint' => 'https://push.example.com/abc'])->assertOk();
        $this->assertSame(1, PushSubscription::count());

        $this->actingAs($other)->postJson('/admin/api/push/unsubscribe', ['endpoint' => 'https://push.example.com/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_test_alert_explains_what_is_missing(): void
    {
        $this->actingAs($this->owner)->postJson('/admin/api/push/test')->assertOk()->assertJson(['ok' => false])->assertSee('aichat:push-keys');

        $this->configure();
        $this->actingAs($this->owner)->postJson('/admin/api/push/test')->assertJson(['ok' => false])->assertSee('Turn alerts on');
    }

    public function test_test_alert_reports_devices_reached(): void
    {
        $this->configure();
        $this->actingAs($this->owner)->postJson('/admin/api/push/subscribe', $this->sub())->assertOk();
        $mock = Mockery::mock(PushSender::class);
        $mock->shouldReceive('toUsers')->once()->andReturn(1);
        $this->app->instance(PushSender::class, $mock);

        $this->actingAs($this->owner)->postJson('/admin/api/push/test')->assertJson(['ok' => true])->assertSee('Sent to 1 device');
    }

    public function test_notifier_sends_push_only_when_configured(): void
    {
        $mock = Mockery::mock(PushSender::class);
        $mock->shouldReceive('toUsers')->never();
        $this->app->instance(PushSender::class, $mock);
        app(Notifier::class)->notify($this->business, 'chat', 'New chat', 'Hi', url('/admin/inbox'));

        $this->configure();
        $mock2 = Mockery::mock(PushSender::class);
        $mock2->shouldReceive('toUsers')->once()->withArgs(fn ($ids, $title) => $ids === [$this->owner->id] && $title === 'New chat 2')->andReturn(1);
        $this->app->instance(PushSender::class, $mock2);
        app(Notifier::class)->notify($this->business, 'chat', 'New chat 2', 'Hi', url('/admin/inbox/2'));
        $this->app->terminate();
    }

    public function test_push_keys_command_writes_env_keys(): void
    {
        $env = base_path('.env');
        $original = is_file($env) ? file_get_contents($env) : null;

        try {
            $this->artisan('aichat:push-keys')->assertSuccessful();
            $this->assertStringContainsString('VAPID_PUBLIC_KEY=', file_get_contents($env));
        } finally {
            $original === null ? @unlink($env) : file_put_contents($env, $original);
        }
    }

    public function test_account_page_and_service_worker(): void
    {
        $this->configure();
        $this->actingAs($this->owner)->get('/admin/account')->assertOk()->assertSee('Phone alerts')->assertSee('name="push-key"', false);
        $this->assertFileExists(public_path('sw.js'));
    }
}
