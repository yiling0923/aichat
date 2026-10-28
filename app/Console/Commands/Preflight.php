<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Before opening to real customers: checks the settings that matter and says what to fix. */
class Preflight extends Command
{
    protected $signature = 'aichat:preflight';

    protected $description = 'Check that this server is ready for real customers (settings, secrets, email, backups)';

    public function handle(): int
    {
        $rows = [];
        $add = function (string $name, string $level, string $note) use (&$rows): void {
            $rows[] = [$name, $level, $note];
        };

        $production = config('app.env') === 'production';
        $add('APP_ENV', $production ? 'PASS' : 'FAIL', $production ? 'production' : 'is "'.config('app.env').'", set APP_ENV=production');
        $add('APP_DEBUG', config('app.debug') ? 'FAIL' : 'PASS', config('app.debug') ? 'debug is on: it would show secrets in errors. Set APP_DEBUG=false' : 'off');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add('APP_URL', $https ? 'PASS' : 'FAIL', $https ? config('app.url') : 'must start with https://');
        $add('APP_KEY', config('app.key') ? 'PASS' : 'FAIL', config('app.key') ? 'set' : 'missing: php artisan key:generate');

        try {
            DB::select('select 1');
            $db = config('database.connections.'.config('database.default'));
            $weak = ($db['driver'] ?? '') === 'mysql' && (($db['username'] ?? '') === 'root' || ($db['password'] ?? '') === '');
            $add('Database', $weak ? 'WARN' : 'PASS', $weak ? 'uses root or an empty password: create a dedicated database user with a strong password' : 'connected');
        } catch (Throwable $e) {
            $add('Database', 'FAIL', 'cannot connect');
        }

        $mailer = (string) config('mail.default');
        $add('Email', in_array($mailer, ['log', 'array'], true) ? 'FAIL' : 'PASS', in_array($mailer, ['log', 'array'], true) ? 'MAIL_MAILER='.$mailer.' only writes to a file: set up SMTP so confirmation emails arrive' : $mailer);
        $add('Sessions', config('session.secure') || $https ? 'PASS' : 'WARN', 'set SESSION_SECURE_COOKIE=true once on https');

        $add('Meta app', config('services.meta.app_id') && config('services.meta.app_secret') && config('services.meta.verify_token') ? 'PASS' : 'WARN', 'META_APP_ID, META_APP_SECRET and META_VERIFY_TOKEN are needed for WhatsApp, Instagram and Messenger');
        $ai = (string) config('aichat.ai.provider');
        $add('AI', $ai === 'claude' && ! config('aichat.ai.api_key') ? 'FAIL' : 'PASS', $ai === 'claude' ? (config('aichat.ai.api_key') ? 'Claude with a key' : 'AI_PROVIDER=claude but no ANTHROPIC_API_KEY') : 'AI_PROVIDER='.$ai);
        $local = config('plans.provider') === 'local';
        $add('Billing', $local ? 'WARN' : (config('services.stripe.secret') && config('services.stripe.webhook_secret') ? 'PASS' : 'FAIL'), $local ? 'test mode: nobody is charged (fine for the beta, not for paid customers)' : 'stripe needs STRIPE_SECRET and STRIPE_WEBHOOK_SECRET');
        $add('Sign-up', config('plans.signup_mode') === 'invite' ? 'PASS' : 'WARN', config('plans.signup_mode') === 'invite' ? 'invite-only' : 'open to everyone: use SIGNUP_MODE=invite for the beta');

        $beat = cache('scheduler-heartbeat');
        $add('Scheduler', $beat && now()->diffInMinutes($beat, true) < 5 ? 'PASS' : 'FAIL', $beat ? 'last ran '.$beat->diffForHumans() : 'has never run: add the cron line (docs/GO-LIVE.md)');
        $add('Storage writable', is_writable(storage_path('logs')) ? 'PASS' : 'FAIL', storage_path());

        $backups = glob(storage_path('app/backups/aichat-*')) ?: [];
        $newest = $backups ? max(array_map('filemtime', $backups)) : null;
        $add('Backups', $newest && $newest > time() - 2 * 86400 ? 'PASS' : 'WARN', $newest ? 'newest is '.date('j M H:i', $newest) : 'none yet: run php artisan aichat:backup');

        $this->table(['Check', 'Result', 'Note'], $rows);
        $failed = count(array_filter($rows, fn ($r) => $r[1] === 'FAIL'));
        $this->line($failed ? "<fg=red>{$failed} problem(s) to fix before going live.</>" : '<fg=green>No blocking problems.</>');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
