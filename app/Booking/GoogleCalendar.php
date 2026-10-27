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

    public function enabled(): bool
    {
        $path = config('services.google_calendar.credentials');

        return $path && is_file($path);
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
        return Cache::remember('google-calendar-token', 3300, function (): string {
            $key = json_decode((string) file_get_contents((string) config('services.google_calendar.credentials')), true);

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
