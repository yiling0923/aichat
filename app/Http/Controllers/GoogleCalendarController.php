<?php

namespace App\Http\Controllers;

use App\Booking\GoogleCalendar;
use App\Models\Business;
use App\Models\Practitioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Connect Google Calendar from the Booking setup page: upload the key, remove it, and test a calendar. */
class GoogleCalendarController extends Controller
{
    /** One key serves the whole server. On a server shared by several businesses only the platform owner changes it. */
    private function mayChangeKey(Request $request): bool
    {
        return $request->user()->role === 'platform_owner' || Business::query()->withoutGlobalScopes()->count() <= 1;
    }

    public function saveKey(Request $request): RedirectResponse
    {
        abort_unless($this->mayChangeKey($request), 403);
        $request->validate(['key' => ['required', 'file', 'max:64']], ['key.max' => __('That file is too big to be a Google key.')]);

        $key = json_decode((string) file_get_contents($request->file('key')->getRealPath()), true);

        if (! is_array($key) || ($key['type'] ?? '') !== 'service_account' || empty($key['client_email']) || ! str_contains((string) ($key['private_key'] ?? ''), 'PRIVATE KEY')) {
            return back()->withErrors(['key' => __('That is not a Google service account key. In Google Cloud choose Keys, Add key, Create new key, JSON, and upload that file.')]);
        }

        $path = GoogleCalendar::uploadedKeyPath();
        file_put_contents($path, json_encode($key, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0600);
        Cache::forget('google-calendar-token');

        return back()->with('status', __('Google key saved. Now share each calendar with :email and press Test.', ['email' => $key['client_email']]));
    }

    public function removeKey(Request $request): RedirectResponse
    {
        abort_unless($this->mayChangeKey($request), 403);
        @unlink(GoogleCalendar::uploadedKeyPath());
        Cache::forget('google-calendar-token');

        return back()->with('status', __('Google key removed. Bookings use the built-in calendar only.'));
    }

    public function test(Request $request, int $id, GoogleCalendar $google): RedirectResponse
    {
        $practitioner = Practitioner::query()->findOrFail($id);

        if (! $google->enabled()) {
            return back()->withErrors(['google' => __('Upload the Google key first (step 3 above).')]);
        }

        $typed = trim((string) $request->input('google_calendar_id', ''));
        if ($typed !== '' && $typed !== $practitioner->google_calendar_id) {
            $practitioner->forceFill(['google_calendar_id' => $typed])->save();
        }

        if (! $practitioner->google_calendar_id) {
            return back()->withErrors(['google' => __('Paste a Calendar ID for :name first and press Save.', ['name' => $practitioner->name])]);
        }

        $result = $google->test($practitioner->google_calendar_id);

        return $result['ok']
            ? back()->with('status', __(':name: connected. The calendar can be read and bookings can be added to it.', ['name' => $practitioner->name]))
            : back()->withErrors(['google' => $practitioner->name.': '.$result['message']]);
    }
}
