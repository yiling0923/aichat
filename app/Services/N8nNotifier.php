<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends events (new conversation, handover, new booking, reminder due) to n8n.
 * The app works without n8n: when no webhook URL is set this does nothing,
 * and a failure never breaks the chat.
 */
class N8nNotifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $event, array $data = []): void
    {
        $url = config('services.n8n.webhook_url');

        if (! $url) {
            return;
        }

        try {
            Http::timeout(2)
                ->withHeaders(['X-Aichat-Secret' => (string) config('services.n8n.secret')])
                ->post($url, [
                    'event' => $event,
                    'data' => $data,
                    'sent_at' => now()->toIso8601String(),
                ]);
        } catch (Throwable $e) {
            Log::warning('n8n event failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
