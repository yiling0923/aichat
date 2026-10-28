<?php

namespace App\Billing;

use App\Models\Business;

/** Something that can take a monthly payment. The local one is test mode; a real one (Stripe) plugs in here at go-live. */
interface BillingProvider
{
    public function name(): string;

    /** @return array{ok: bool, reference: ?string, error: ?string} */
    public function charge(Business $business, string $plan, int $amountCents, string $currency): array;
}
