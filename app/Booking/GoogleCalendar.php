<?php

namespace App\Booking;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Calendar through a service account (free, no sign-in screen).
 * The owner shares each practitioner's calendar with the service account email.
 * Without credentials nothing here runs and the built-in calendar is used.
 */
class GoogleCalendar
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar';

    /**
     * The key file in use: the one this business uploaded on the Booking setup page, else the one named in .env
     * (a key shared by the whole server), else an older server-wide upload.
     */
    public function credentialsPath(): ?string
    {
        $own = self::uploadedKeyPath();

        if (is_file($own)) {
            return $own;
        }

        $path = config('services.google_calendar.credentials');

        if ($path && is_file($path)) {
            return (string) $path;
        }

        $legacy = storage_path('app/google-service-account.json');

        return is_file($legacy) ? $legacy : null;
    }

    public function enabled(): bool
    {
        return $this->credentialsPath() !== null;
    }

    /** The email address staff must share each calendar with (from the key file), or null without a valid key. */
    public function serviceAccountEmail(): ?string
    {
        $path = $this->credentialsPath();
        $key = $path ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($key) && ! empty($key['client_email']) ? (string) $key['client_email'] : null;
    }

    /** Where this business's own uploaded key is kept (every business can use its own Google account). */
    public static function uploadedKeyPath(?int $businessId = null): string
    {
        return storage_path('app/google/'.($businessId ?? app(\App\Support\CurrentBusiness::class)->id() ?? 0).'.json');
    }

    /**
     * Checks that a calendar can really be read and written: asks for its busy times, then adds a tiny event and removes it again.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(string $calendarId): array
    {
        $email = $this->serviceAccountEmail() ?? __('the service account');

        try {
            $this->busy($calendarId, now(), now()->addDay());
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->explain($e->getMessage(), $email)];
        }

        try {
            $start = now()->addDays(2)->setTime(3, 0);
            $id = $this->createEvent($calendarId, 'Test from the booking assistant (safe to delete)', 'This event was made to test the connection and is removed straight away.', $start, $start->copy()->addMinutes(5));
            $this->deleteEvent($calendarId, $id);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->explain($e->getMessage(), $email)];
        }

        return ['ok' => true, 'message' => __('Connected. The calendar can be read and bookings can be added to it.')];
    }

    private function explain(string $error, string $email): string
    {
        if (str_contains($error, '404') || str_contains($error, 'cannot read')) {
            return __('Google cannot find or open this calendar. Check the Calendar ID, and share the calendar with :email (permission: Make changes to events).', ['email' => $email]);
        }
        if (str_contains($error, '403')) {
            return __('This calendar is not shared with :email with the permission "Make changes to events". Share it again, then test.', ['email' => $email]);
        }
        if (str_contains($error, 'sign-in failed') || str_contains($error, 'not valid')) {
            return __('Google did not accept the key file. Upload the JSON key again, or check that the Google Calendar API is turned on for the project.');
        }

        return __('Google Calendar did not answer: :why', ['why' => $error]);
    }

    /**
     * Busy periods of a calendar.
     *
     * @return list<array{0: \Carbon\Carbon, 1: \Carbon\Carbon}>
     */
    public function busy(string $calendarId, CarbonInterface $from, CarbonInterface $to): array
    {
        $response = $this->request()->post('https://www.googleapis.com/calendar/v3/freeBusy', [
            'timeMin' => $from->toIso8601String(),
            'timeMax' => $to->toIso8601String(),
            'items' => [['id' => $calendarId]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Google Calendar free/busy failed: '.$response->status());
        }

        // Calendar ids contain dots, so index the array instead of using dot notation.
        $calendar = ($response->json('calendars') ?? [])[$calendarId] ?? [];

        if (! empty($calendar['errors'])) {
            throw new RuntimeException('Google Calendar cannot read '.$calendarId.' (is it shared with the service account?)');
        }

        return array_map(
            fn (array $b) => [\Carbon\Carbon::parse($b['start']), \Carbon\Carbon::parse($b['end'])],
            $calendar['busy'] ?? [],
        );
    }

    public function createEvent(string $calendarId, string $title, string $description, CarbonInterface $start, CarbonInterface $end): string
    {
        $response = $this->request()->post('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events', [
            'summary' => $title,
            'description' => $description,
            'start' => ['dateTime' => $start->toIso8601String()],
            'end' => ['dateTime' => $end->toIso8601String()],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Google Calendar event failed: '.$response->status());
        }

        return (string) $response->json('id');
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        $response = $this->request()->delete('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId));

        if ($response->failed() && $response->status() !== 410 && $response->status() !== 404) {
            throw new RuntimeException('Google Calendar delete failed: '.$response->status());
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(8)->withToken($this->token())->acceptJson();
    }

    private function token(): string
    {
        return Cache::remember('google-calendar-token-'.md5((string) $this->credentialsPath().'|'.@filemtime((string) $this->credentialsPath())), 3300, function (): string {
            $key = json_decode((string) file_get_contents((string) $this->credentialsPath()), true);

            if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
                throw new RuntimeException('The Google service account file is not valid.');
            }

            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $key['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256);

            $response = Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ]);

            if ($response->failed() || ! $response->json('access_token')) {
                throw new RuntimeException('Google sign-in failed: '.$response->status());
            }

            return (string) $response->json('access_token');
        });
    }
}
