<?php

namespace App\Console\Commands;

use App\Billing\Billing;
use Illuminate\Console\Command;

class RenewSubscriptions extends Command
{
    protected $signature = 'aichat:renew-subscriptions';

    protected $description = 'Take the next monthly payment for paid plans whose month ended';

    public function handle(Billing $billing): int
    {
        $r = $billing->renewDue();
        $this->info("Renewed {$r['renewed']}, failed {$r['failed']}.");

        return self::SUCCESS;
    }
}
