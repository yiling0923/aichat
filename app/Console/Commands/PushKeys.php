<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class PushKeys extends Command
{
    protected $signature = 'aichat:push-keys {--force : Replace keys that already exist (everyone has to turn alerts on again)}';

    protected $description = 'Make the keys that phone and desktop alerts need, and save them in .env';

    public function handle(): int
    {
        $path = base_path('.env');
        $env = is_file($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^VAPID_PUBLIC_KEY=.+$/m', $env) && ! $this->option('force')) {
            $this->info('Alert keys already exist in .env. Nothing changed. (Use --force to replace them.)');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        $env = preg_replace('/^VAPID_(PUBLIC_KEY|PRIVATE_KEY)=.*\R?/m', '', $env) ?? $env;
        $env = rtrim($env)."\n\nVAPID_PUBLIC_KEY=".$keys['publicKey']."\nVAPID_PRIVATE_KEY=".$keys['privateKey']."\n";

        if (@file_put_contents($path, $env) === false) {
            $this->error('Could not write .env. Add these two lines to it yourself:');
            $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

            return self::FAILURE;
        }

        $this->info('Alert keys saved in .env. Now run: php artisan config:clear');
        $this->line('Then each person opens My account and turns alerts on for their phone or computer.');

        return self::SUCCESS;
    }
}
