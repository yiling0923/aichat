<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A person turns alerts on or off for the phone or computer they are using. */
class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'regex:/^https:\/\//'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $hash = hash('sha256', $data['endpoint']);

        // The same device can only belong to one person: signing in as someone else on it takes it over.
        $sub = PushSubscription::query()->updateOrCreate(['endpoint_hash' => $hash], [
            'user_id' => $user->id, 'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'],
            'device' => $this->device((string) $request->userAgent()),
        ]);
        $sub->forceFill(['business_id' => $user->business_id])->save();

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        PushSubscription::query()->where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    /** Sends a test alert to all of this person's devices. */
    public function test(Request $request, PushSender $push): JsonResponse
    {
        $user = $request->user();
        $count = PushSubscription::query()->where('user_id', $user->id)->count();

        if (! PushSender::configured()) {
            return response()->json(['ok' => false, 'message' => 'Phone alerts are not set up on this server yet. Ask whoever installed the app to run: php artisan aichat:push-keys']);
        }
        if ($count === 0) {
            return response()->json(['ok' => false, 'message' => 'Turn alerts on for this device first.']);
        }

        $reached = $push->toUsers([$user->id], 'Test alert', 'If you can read this, alerts reach this device.', route('inbox'), 'test-'.$user->id);

        return response()->json(['ok' => $reached > 0, 'message' => $reached > 0 ? 'Sent to '.$reached.' '.($reached === 1 ? 'device' : 'devices').'. It should appear in a moment.' : 'It could not be delivered. Turn alerts off and on again on this device.']);
    }

    private function device(string $ua): string
    {
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iPhone or iPad',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'a device',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'browser',
        };

        return $browser.' on '.$os;
    }
}
