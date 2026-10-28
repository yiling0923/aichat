<?php

namespace App\Console\Commands;

use App\Marketing\AutomationRunner;
use App\Models\Business;
use Illuminate\Console\Command;

/**
 * Runs every five minutes (Laravel scheduler): survey, no-show, rebooking and hot-lead messages.
 */
class RunAutomations extends Command
{
    protected $signature = 'aichat:run-automations';

    protected $description = 'Send the automatic messages that are due (survey, rebooking, no-show, hot-lead follow-up)';

    public function handle(AutomationRunner $runner): int
    {
        $total = 0;

        foreach (Business::query()->where('status', 'active')->get() as $business) {
            $total += array_sum($runner->runForBusiness($business));
        }

        $this->info($total.' message(s) sent.');

        return self::SUCCESS;
    }
}
