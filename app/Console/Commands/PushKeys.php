<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        $keys = $this->makeKeys();

        if ($keys === null) {
            $this->error('PHP could not make the keys.');
            $this->line('OpenSSL loaded: '.(extension_loaded('openssl') ? 'yes' : 'NO').' | '.(defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : '-').' | PHP '.PHP_VERSION);
            $this->line('OPENSSL_CONF: '.(getenv('OPENSSL_CONF') ?: '(not set)').' | php.ini: '.(php_ini_loaded_file() ?: '(none)'));
            while (($err = openssl_error_string()) !== false) {
                $this->line('OpenSSL says: '.$err);
            }
            $this->line('Fix: in php.ini make sure ;extension=openssl has no ; in front, then try again.');

            return self::FAILURE;
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

    /**
     * A P-256 key pair in the form browsers want. XAMPP on Windows often has OPENSSL_CONF pointing at a file that is not there,
     * so each settings file is tried through the "config" option, ending with a minimal one of our own.
     *
     * @return array{publicKey: string, privateKey: string}|null
     */
    private function makeKeys(): ?array
    {
        $php = dirname(PHP_BINARY);
        $own = storage_path('app/openssl.cnf');
        @file_put_contents($own, "openssl_conf = default_conf\n[default_conf]\n[req]\ndistinguished_name = dn\n[dn]\n");

        $configs = array_values(array_filter([
            getenv('OPENSSL_CONF') ?: null, $php.'\\extras\\ssl\\openssl.cnf', dirname($php).'\\apache\\conf\\openssl.cnf', '/etc/ssl/openssl.cnf', $own,
        ], fn ($f) => $f !== null && is_file($f)));
        array_unshift($configs, null);

        foreach ($configs as $cnf) {
            $res = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + ($cnf === null ? [] : ['config' => $cnf]));
            $ec = $res === false ? null : (openssl_pkey_get_details($res)['ec'] ?? null);

            if ($ec !== null) {
                $b64 = fn (string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

                return ['publicKey' => $b64("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)), 'privateKey' => $b64(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT))];
            }
        }

        return null;
    }
}
