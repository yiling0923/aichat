<?php

namespace App\Console\Commands;

use App\Channels\WebhookRegistrar;
use Illuminate\Console\Command;
use Throwable;

/**
 * Saves the webhook address in the Meta app. start-tunnel.bat runs this after every new tunnel address.
 */
class MetaWebhook extends Command
{
    protected $signature = 'aichat:meta-webhook';

    protected $description = 'Save the Callback URL and Verify token in the Meta app';

    public function handle(WebhookRegistrar $registrar): int
    {
        try {
            $done = $registrar->register();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Webhook '.$registrar->url().' saved in Meta for '.implode(', ', $done).'.');

        return self::SUCCESS;
    }
}
