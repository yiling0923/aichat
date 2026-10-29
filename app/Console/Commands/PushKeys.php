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

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            // XAMPP on Windows often cannot find PHP's OpenSSL settings file; point it at the one that ships with XAMPP and retry.
            $keys = null;
            $php = dirname(PHP_BINARY);
            foreach ([$php.'\\extras\\ssl\\openssl.cnf', dirname($php).'\\apache\\conf\\openssl.cnf', $php.'\\openssl.cnf', '/etc/ssl/openssl.cnf'] as $cnf) {
                if (is_file($cnf)) {
                    putenv('OPENSSL_CONF='.$cnf);
                    try {
                        $keys = VAPID::createVapidKeys();

                        break;
                    } catch (\Throwable) {
                        // try the next file
                    }
                }
            }
            if ($keys === null) {
                $this->error('PHP could not make the keys: '.$e->getMessage());
                $this->line('Fix: in C:\\xampp\\php\\php.ini make sure ;extension=openssl has no ; in front, then try again.');

                return self::FAILURE;
            }
        }
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
