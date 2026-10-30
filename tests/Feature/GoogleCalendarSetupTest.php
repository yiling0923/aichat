<?php

namespace Tests\Feature;

use App\Booking\GoogleCalendar;
use App\Models\Business;
use App\Models\Practitioner;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarSetupTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_calendar.credentials' => null]);
        @unlink(GoogleCalendar::uploadedKeyPath());
        Cache::forget('google-calendar-token');
        $this->business = Business::factory()->create(['slug' => 'gc-co']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    protected function tearDown(): void
    {
        @unlink(GoogleCalendar::uploadedKeyPath());
        Cache::forget('google-calendar-token');
        parent::tearDown();
    }

    private function keyFile(string $email = 'bot@proj.iam.gserviceaccount.com'): UploadedFile
    {
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pkey, $pem);

        return UploadedFile::fake()->createWithContent('key.json', json_encode(['type' => 'service_account', 'client_email' => $email, 'private_key' => $pem, 'project_id' => 'proj']));
    }

    private function practitioner(?string $calendar = 'team@group.calendar.google.com'): Practitioner
    {
        return app(CurrentBusiness::class)->runAs($this->business, fn () => Practitioner::create(['name' => 'Dr Mei', 'google_calendar_id' => $calendar]));
    }

    public function test_the_page_explains_how_to_connect_and_the_owner_can_upload_the_key(): void
    {
        $this->actingAs($this->owner)->get('/admin/booking-setup')->assertOk()->assertSee('How to connect')->assertSee('Not connected');

        $this->actingAs($this->owner)->post('/admin/booking-setup/google-key', ['key' => $this->keyFile()])->assertRedirect();

        $this->assertFileExists(GoogleCalendar::uploadedKeyPath());
        $this->actingAs($this->owner)->get('/admin/booking-setup')->assertOk()->assertSee('Key loaded')->assertSee('bot@proj.iam.gserviceaccount.com')->assertSee('Share each calendar with this address');
        $this->assertTrue(app(GoogleCalendar::class)->enabled());

        $this->actingAs($this->owner)->post('/admin/booking-setup/google-key/remove')->assertRedirect();
        $this->assertFalse(app(GoogleCalendar::class)->enabled());
    }

    public function test_a_file_that_is_not_a_service_account_key_is_refused(): void
    {
        $bad = UploadedFile::fake()->createWithContent('x.json', json_encode(['hello' => 'world']));
        $this->actingAs($this->owner)->post('/admin/booking-setup/google-key', ['key' => $bad])->assertSessionHasErrors('key');
        $this->assertFileDoesNotExist(GoogleCalendar::uploadedKeyPath());
    }

    public function test_on_a_server_shared_by_several_businesses_only_the_platform_owner_changes_the_key(): void
    {
        Business::factory()->create(['slug' => 'other-co']);
        $this->actingAs($this->owner)->post('/admin/booking-setup/google-key', ['key' => $this->keyFile()])->assertForbidden();

        $platform = User::factory()->create(['business_id' => null, 'role' => 'platform_owner']);
        $this->actingAs($platform)->post('/admin/booking-setup/google-key', ['key' => $this->keyFile()])->assertStatus(302);
    }

    public function test_a_calendar_can_be_tested_for_reading_and_writing(): void
    {
        $this->actingAs($this->owner)->post('/admin/booking-setup/google-key', ['key' => $this->keyFile()]);
        $p = $this->practitioner();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['team@group.calendar.google.com' => ['busy' => []]]]),
            'www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'ev1']),
            'www.googleapis.com/calendar/v3/calendars/*/events/ev1' => Http::response('', 204),
        ]);

        $this->actingAs($this->owner)->post('/admin/booking-setup/practitioners/'.$p->id.'/google-test')->assertSessionHas('status', fn ($m) => str_contains($m, 'Dr Mei: connected'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/ev1'));
    }

    public function test_a_calendar_that_is_not_shared_says_what_to_do(): void
    {
        $this->actingAs($this->owner)->post('/admin/booking-setup/google-key', ['key' => $this->keyFile()]);
        $p = $this->practitioner();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['team@group.calendar.google.com' => ['errors' => [['reason' => 'notFound']]]]]),
        ]);

        $r = $this->actingAs($this->owner)->post('/admin/booking-setup/practitioners/'.$p->id.'/google-test');
        $r->assertSessionHasErrors('google');
        $this->assertStringContainsString('bot@proj.iam.gserviceaccount.com', session('errors')->first('google'));
    }

    public function test_testing_without_a_key_or_without_a_calendar_id_explains_the_missing_step(): void
    {
        $p = $this->practitioner(null);
        $this->actingAs($this->owner)->post('/admin/booking-setup/practitioners/'.$p->id.'/google-test')->assertSessionHasErrors('google');

        $this->actingAs($this->owner)->post('/admin/booking-setup/google-key', ['key' => $this->keyFile()]);
        $r = $this->actingAs($this->owner)->post('/admin/booking-setup/practitioners/'.$p->id.'/google-test');
        $this->assertStringContainsString('Calendar ID', session('errors')->first('google'));
    }
}
