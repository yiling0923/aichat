<?php

namespace App\Console\Commands;

use App\Mail\DailySummaryMail;
use App\Models\Business;
use App\Models\User;
use App\Services\DailySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Runs every hour. At about 08:00 in each business's own time zone, owners and managers get yesterday's numbers by email (if the business turned it on). */
class SendDailySummary extends Command
{
    protected $signature = 'aichat:daily-summary {--force : Send now to every business that turned it on}';

    protected $description = 'Email yesterday\'s summary to owners and managers who asked for it';

    public function handle(DailySummary $summary): int
    {
        $sent = 0;

        Business::query()->get()->each(function (Business $business) use ($summary, &$sent) {
            if (empty($business->settings['daily_email'])) {
                return;
            }
            $now = now($business->timezone ?: 'UTC');
            $date = $now->toDateString();
            if (! $this->option('force') && ($now->hour !== 8 || ($business->settings['daily_email_sent'] ?? null) === $date)) {
                return;
            }

            $stats = $summary->forDay($business, $now->copy()->subDay());
            $users = User::query()->where('business_id', $business->id)->where('active', true)->whereIn('role', ['owner', 'manager'])->get();

            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(new DailySummaryMail($business, $stats, route('today')));
                    $sent++;
                } catch (Throwable $e) {
                    $this->warn('Could not email '.$user->email.': '.$e->getMessage());
                }
            }

            $settings = (array) $business->settings;
            $settings['daily_email_sent'] = $date;
            $business->forceFill(['settings' => $settings])->save();
        });

        $this->info($sent.' summary '.($sent === 1 ? 'email' : 'emails').' sent.');

        return self::SUCCESS;
    }
}
