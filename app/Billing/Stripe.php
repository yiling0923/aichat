<?php

namespace App\Billing;

use App\Models\Business;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Real payments through Stripe Checkout and webhooks (plain HTTPS calls, no extra package). */
class Stripe
{
    private const API = 'https://api.stripe.com/v1';

    public static function enabled(): bool
    {
        return config('plans.provider') === 'stripe';
    }

    /** The Stripe page where the owner enters a card and starts the subscription. */
    public function checkoutUrl(Business $business, string $plan, string $email, string $successUrl, string $cancelUrl): string
    {
        $price = config('services.stripe.prices.'.$plan);
        if (! $price) {
            throw new RuntimeException('No Stripe price is set for the '.$plan.' plan (STRIPE_PRICE_'.strtoupper($plan).').');
        }

        $params = [
            'mode' => 'subscription',
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'success_url' => $successUrl, 'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $business->id,
            'metadata' => ['plan' => $plan, 'business_id' => (string) $business->id],
            'subscription_data' => ['metadata' => ['plan' => $plan, 'business_id' => (string) $business->id]],
        ];

        $customer = $business->settings['stripe']['customer'] ?? null;
        $customer ? $params['customer'] = $customer : $params['customer_email'] = $email;

        return (string) $this->post('/checkout/sessions', $params)['url'];
    }

    /** Stop renewing at the end of the paid month. */
    public function cancelAtPeriodEnd(Business $business): void
    {
        $subscription = $business->settings['stripe']['subscription'] ?? null;
        if ($subscription) {
            $this->post('/subscriptions/'.$subscription, ['cancel_at_period_end' => 'true']);
        }
    }

    /** Stripe signs every webhook: only accept it when the signature matches and is recent. */
    public function validSignature(string $payload, ?string $header): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '' || $header === null) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k][] = $v;
        }

        $time = (int) ($parts['t'][0] ?? 0);
        if ($time === 0 || abs(time() - $time) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $time.'.'.$payload, $secret);

        foreach ($parts['v1'] ?? [] as $given) {
            if (hash_equals($expected, $given)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $event */
    public function handle(array $event): void
    {
        $object = $event['data']['object'] ?? [];

        match ($event['type'] ?? '') {
            'checkout.session.completed' => $this->checkoutCompleted($object),
            'invoice.paid' => $this->invoicePaid($object),
            'invoice.payment_failed' => $this->setStatus($this->byCustomer($object['customer'] ?? null), 'past_due'),
            'customer.subscription.updated' => $this->subscriptionUpdated($object),
            'customer.subscription.deleted' => $this->subscriptionEnded($object),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $session */
    private function checkoutCompleted(array $session): void
    {
        $business = Business::query()->find((int) ($session['client_reference_id'] ?? 0));
        $plan = $session['metadata']['plan'] ?? null;

        if ($business === null || ! in_array($plan, ['starter', 'growth'], true)) {
            return;
        }

        $settings = (array) $business->settings;
        $settings['stripe'] = ['customer' => $session['customer'] ?? null, 'subscription' => $session['subscription'] ?? null];
        $business->forceFill([
            'plan' => $plan, 'subscription_status' => 'active', 'settings' => $settings,
            'current_period_ends_at' => $business->current_period_ends_at?->isFuture() ? $business->current_period_ends_at : now()->addMonth(),
        ])->save();
    }

    /** @param  array<string, mixed>  $invoice */
    private function invoicePaid(array $invoice): void
    {
        $business = $this->byCustomer($invoice['customer'] ?? null);
        $reference = (string) ($invoice['id'] ?? '');

        if ($business === null || $reference === '' || Payment::query()->where('reference', $reference)->exists()) {
            return;
        }

        Payment::create([
            'business_id' => $business->id, 'plan' => (string) $business->plan, 'amount_cents' => (int) ($invoice['amount_paid'] ?? 0),
            'currency' => strtoupper((string) ($invoice['currency'] ?? config('plans.currency'))), 'provider' => 'stripe', 'reference' => $reference, 'status' => 'paid',
        ]);

        $end = $invoice['lines']['data'][0]['period']['end'] ?? null;
        $business->forceFill([
            'subscription_status' => $business->subscription_status === 'canceled' ? 'canceled' : 'active',
            'current_period_ends_at' => $end ? now()->setTimestamp((int) $end) : now()->addMonth(),
        ])->save();
    }

    /** @param  array<string, mixed>  $subscription */
    private function subscriptionUpdated(array $subscription): void
    {
        $business = $this->byCustomer($subscription['customer'] ?? null);

        if ($business !== null && ($subscription['status'] ?? '') === 'active') {
            $this->setStatus($business, ! empty($subscription['cancel_at_period_end']) ? 'canceled' : 'active');
        }
    }

    /** @param  array<string, mixed>  $subscription */
    private function subscriptionEnded(array $subscription): void
    {
        $business = $this->byCustomer($subscription['customer'] ?? null);

        if ($business !== null) {
            $business->forceFill(['subscription_status' => 'canceled', 'current_period_ends_at' => now()])->save();
        }
    }

    private function setStatus(?Business $business, string $status): void
    {
        $business?->forceFill(['subscription_status' => $status])->save();
    }

    private function byCustomer(?string $customer): ?Business
    {
        return $customer ? Business::query()->where('settings->stripe->customer', $customer)->first() : null;
    }

    /** @param  array<string, mixed>  $params @return array<string, mixed> */
    private function post(string $path, array $params): array
    {
        $response = Http::withBasicAuth((string) config('services.stripe.secret'), '')->asForm()->post(self::API.$path, $params);

        if (! $response->successful()) {
            throw new RuntimeException('Stripe said: '.($response->json('error.message') ?? 'error '.$response->status()));
        }

        return (array) $response->json();
    }
}
