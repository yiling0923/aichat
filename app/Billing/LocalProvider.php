<?php

namespace App\Billing;

use App\Models\Business;
use Illuminate\Support\Str;

/** Test mode: nobody is charged, every payment succeeds unless the business is flagged to fail (used for testing). */
class LocalProvider implements BillingProvider
{
    public function name(): string
    {
        return 'local';
    }

    public function charge(Business $business, string $plan, int $amountCents, string $currency): array
    {
        if (($business->settings['billing_test_fail'] ?? false) === true) {
            return ['ok' => false, 'reference' => null, 'error' => 'Test payment declined.'];
        }

        return ['ok' => true, 'reference' => 'local_'.Str::lower(Str::random(12)), 'error' => null];
    }
}
