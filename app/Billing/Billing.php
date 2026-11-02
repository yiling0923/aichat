<?php

namespace App\Billing;

use App\Models\Business;
use App\Models\Payment;

/** Starting, renewing and cancelling a paid plan. */
class Billing
{
    public function __construct(private Plans $plans) {}

    public function provider(): BillingProvider
    {
        if (app()->bound(BillingProvider::class)) {
            return app(BillingProvider::class);
        }

        return new LocalProvider;   // 'stripe' is added at go-live (phase 7)
    }

    /** Move a business to a paid plan and take the first month. @return array{ok: bool, error: ?string} */
    public function subscribe(Business $business, string $plan): array
    {
        abort_unless(in_array($plan, ['starter', 'growth'], true), 422);

        $result = $this->pay($business, $plan);

        if (! $result['ok']) {
            return ['ok' => false, 'error' => $result['error']];
        }

        $business->forceFill([
            'plan' => $plan,
            'subscription_status' => 'active',
            'current_period_ends_at' => now()->addMonth(),
        ])->save();

        return ['ok' => true, 'error' => null];
    }

    /** Stop renewing. The plan keeps working until the paid month ends. */
    public function cancel(Business $business): void
    {
        if (Stripe::enabled()) {
            app(Stripe::class)->cancelAtPeriodEnd($business);
        }

        $business->forceFill(['subscription_status' => 'canceled'])->save();
    }

    /** Daily: charge subscriptions whose month ended. @return array{renewed: int, failed: int} */
    public function renewDue(): array
    {
        $renewed = $failed = 0;

        if (Stripe::enabled()) {
            return ['renewed' => 0, 'failed' => 0];   // Stripe renews by itself and tells us through the webhook
        }

        Business::query()->where('subscription_status', 'active')->whereIn('plan', ['starter', 'growth'])
            ->where('current_period_ends_at', '<=', now())->get()
            ->each(function (Business $business) use (&$renewed, &$failed): void {
                $result = $this->pay($business, $business->plan);

                if ($result['ok']) {
                    $business->forceFill(['current_period_ends_at' => $business->current_period_ends_at->addMonth()])->save();
                    $renewed++;
                } else {
                    $business->forceFill(['subscription_status' => 'past_due'])->save();
                    $failed++;
                }
            });

        return ['renewed' => $renewed, 'failed' => $failed];
    }

    /** @return array{ok: bool, reference: ?string, error: ?string} */
    private function pay(Business $business, string $plan): array
    {
        $amount = $this->plans->priceCents($plan);
        $provider = $this->provider();
        $result = $provider->charge($business, $plan, $amount, $this->plans->currency());

        Payment::create([
            'business_id' => $business->id, 'plan' => $plan, 'amount_cents' => $amount, 'currency' => $this->plans->currency(),
            'provider' => $provider->name(), 'reference' => $result['reference'], 'status' => $result['ok'] ? 'paid' : 'failed',
        ]);

        return $result;
    }
}
