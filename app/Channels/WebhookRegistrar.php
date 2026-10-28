<?php

namespace App\Channels;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Saves our Callback URL and Verify token in the Meta app (for the kinds of channel that are connected).
 * Meta calls our address straight away to check it, so the public address must be reachable.
 */
class WebhookRegistrar
{
    private const OBJECTS = [
        'whatsapp' => ['whatsapp_business_account', 'messages'],
        'messenger' => ['page', 'messages,messaging_postbacks'],
        'instagram' => ['instagram', 'messages,messaging_postbacks'],
    ];

    public function __construct(private MetaGraph $graph) {}

    public function url(): string
    {
        return rtrim((string) config('app.url'), '/').'/webhook/meta';
    }

    /**
     * @param  list<string>|null  $types  channel types to register; null means every type that is connected
     * @return list<string> labels of what was saved
     *
     * @throws RuntimeException with a message a person can act on
     */
    public function register(?array $types = null): array
    {
        $appId = (string) config('services.meta.app_id');
        $secret = (string) config('services.meta.app_secret');
        $verify = (string) config('services.meta.verify_token');

        if ($appId === '' || $secret === '' || $verify === '') {
            throw new RuntimeException('Set META_APP_ID, META_APP_SECRET and META_VERIFY_TOKEN in .env, then run php artisan config:clear.');
        }

        $types ??= Channel::query()->withoutGlobalScopes()->pluck('type')->unique()->all();

        if ($types === []) {
            throw new RuntimeException('Add a channel first.');
        }

        $done = [];
        foreach ($types as $type) {
            [$object, $fields] = self::OBJECTS[$type];

            $response = Http::asForm()->timeout(15)->post($this->graph->url($appId.'/subscriptions'), [
                'object' => $object, 'callback_url' => $this->url(), 'verify_token' => $verify, 'fields' => $fields,
                'access_token' => $appId.'|'.$secret,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Meta says: '.$this->graph->errorText($response).' (the tunnel must be open and the address must match APP_URL)');
            }

            $done[] = Channel::TYPES[$type];
        }

        return $done;
    }
}
