<?php

namespace App\Http\Controllers;

use App\Channels\InboundHandler;
use App\Channels\WebhookParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one public address Meta calls for WhatsApp, Messenger and Instagram.
 * GET answers Meta's check when you save the webhook. POST receives messages.
 */
class MetaWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $expected = (string) config('services.meta.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            self::note('verified');

            return response((string) $request->query('hub_challenge', $request->query('hub.challenge')), 200)->header('Content-Type', 'text/plain');
        }

        self::note('verify_failed');

        return response('Forbidden', 403);
    }

    /** Remembers the last call from Meta, so the Channels page can say in plain words whether messages are arriving. */
    public static function note(string $result, int $messages = 0): void
    {
        try {
            Cache::put('meta_webhook_last', ['at' => now()->toIso8601String(), 'result' => $result, 'messages' => $messages], now()->addDays(14));
        } catch (Throwable) {
            // a diagnostic must never break the webhook
        }
    }

    public function receive(Request $request, WebhookParser $parser, InboundHandler $handler): Response
    {
        $secret = (string) config('services.meta.app_secret');
        $given = (string) $request->header('X-Hub-Signature-256');

        // Only Meta knows the app secret, so a valid signature proves the message is from Meta.
        if ($secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $given)) {
            Log::warning('Meta webhook rejected: missing app secret or bad signature');
            self::note($secret === '' ? 'no_secret' : 'bad_signature');

            return response('Forbidden', 403);
        }

        $events = $parser->parse((array) $request->json()->all());
        Log::info('Meta webhook received', ['messages' => count($events)]);
        self::note('received', count($events));

        foreach ($events as $event) {
            try {
                $handler->handle($event);
            } catch (Throwable $e) {
                // Still answer 200: Meta would otherwise resend the same message again and again.
                report($e);
            }
        }

        return response('OK', 200);
    }
}
