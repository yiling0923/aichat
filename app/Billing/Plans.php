<?php

namespace App\Billing;

use App\Mail\LimitReachedMail;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Channel;
use App\Models\Practitioner;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Support\Facades\Mail;

/**
 * What a business's plan allows and how much it has used. A business with no plan at all (the sample
 * business, tests) is internal and has no limits.
 */
class Plans
{
    public const KINDS = ['ai', 'staff', 'practitioners', 'channels'];

    /** internal, trial, trial_expired, active, past_due or canceled (canceled = ended) */
    public function state(Business $business): string
    {
        if ($business->plan === null) {
            return 'internal';
        }

        if ($business->plan === 'trial') {
            return $business->trial_ends_at !== null && $business->trial_ends_at->isPast() ? 'trial_expired' : 'trial';
        }

        if ($business->subscription_status === 'past_due') {
            return 'past_due';
        }

        // Cancelled plans keep working until the paid period ends.
        if ($business->subscription_status === 'canceled'
            && ($business->current_period_ends_at === null || $business->current_period_ends_at->isPast())) {
            return 'canceled';
        }

        return 'active';
    }

    /** True while the plan lets the business use its features (AI, adding people). */
    public function inGoodStanding(Business $business): bool
    {
        return in_array($this->state($business), ['internal', 'trial', 'active'], true);
    }

    /** @return array<string, mixed> */
    public function definition(Business $business): array
    {
        return (array) config('plans.plans.'.($business->plan ?? 'trial'));
    }

    /** The limit for one kind, null = unlimited. A business out of good standing gets 0 AI replies. */
    public function limit(Business $business, string $kind): ?int
    {
        if ($this->state($business) === 'internal') {
            return null;
        }

        if ($kind === 'ai' && ! $this->inGoodStanding($business)) {
            return 0;
        }

        // The platform owner can give one business its own limit (a number, or "unlimited") instead of the plan's.
        $custom = ((array) $business->settings)['limit_overrides'][$kind] ?? null;
        if ($custom === 'unlimited') {
            return null;
        }
        if (is_numeric($custom)) {
            return max(0, (int) $custom);
        }

        $value = $this->definition($business)['limits'][$kind] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function used(Business $business, string $kind): int
    {
        return match ($kind) {
            'ai' => AiUsage::query()->withoutGlobalScopes()->where('business_id', $business->id)->where('created_at', '>=', now()->startOfMonth())->count(),
            'staff' => User::query()->where('business_id', $business->id)->where('active', true)->count(),
            'practitioners' => Practitioner::query()->withoutGlobalScopes()->where('business_id', $business->id)->where('active', true)->count(),
            'channels' => Channel::query()->withoutGlobalScopes()->where('business_id', $business->id)->count(),
            default => 0,
        };
    }

    public function canAdd(Business $business, string $kind): bool
    {
        if ($this->state($business) !== 'internal' && ! $this->inGoodStanding($business) && $kind !== 'channels') {
            return false;
        }

        $limit = $this->limit($business, $kind);

        return $limit === null || $this->used($business, $kind) < $limit;
    }

    /** Tell the owners once a month that a limit was reached. */
    public function notifyLimit(Business $business, string $kind): void
    {
        $month = now()->format('Y-m');
        $settings = (array) $business->settings;

        if (($settings['limit_notice'][$kind] ?? null) === $month) {
            return;
        }

        $settings['limit_notice'][$kind] = $month;
        $business->forceFill(['settings' => $settings])->save();

        app(Notifier::class)->notify($business, 'plan', 'Plan limit reached: '.(config('plans.labels.'.$kind) ?? $kind), $kind === 'ai' ? 'The AI has paused. Chats still reach your inbox.' : null, route('billing'), ['owner']);

        $owners = User::query()->where('business_id', $business->id)->where('role', 'owner')->where('active', true)->get();
        foreach ($owners as $owner) {
            \App\Support\Lang::as($owner->getAttribute('locale'), fn () => Mail::to($owner->email)->send(new LimitReachedMail($business, $kind)));
        }
    }

    public function daysLeftInTrial(Business $business): ?int
    {
        return $business->plan === 'trial' && $business->trial_ends_at !== null
            ? max(0, (int) ceil(now()->diffInHours($business->trial_ends_at, false) / 24))
            : null;
    }

    /** The plans as the platform owner has set them: the config, with any prices chosen in the console. @return array<string, array<string, mixed>> */
    public function catalog(): array
    {
        $plans = (array) config('plans.plans');
        $chosen = (array) \App\Models\PlatformSetting::get('billing.prices', []);

        foreach ($plans as $key => $plan) {
            if (isset($chosen[$key]) && is_numeric($chosen[$key]) && ($plan['price_cents'] ?? 0) > 0) {
                $plans[$key]['price_cents'] = max(0, (int) $chosen[$key]);
            }
        }

        return $plans;
    }

    /** The ISO currency code the plans are charged in (chosen in the console, otherwise BILLING_CURRENCY). */
    public function currency(): string
    {
        return strtoupper((string) \App\Models\PlatformSetting::get('billing.currency', config('plans.currency')));
    }

    /** What customers see before a plan price, such as "RM" or "$". */
    public function currencyLabel(): string
    {
        return \App\Support\Currencies::symbolFor($this->currency());
    }

    public function priceCents(string $plan): int
    {
        return (int) ($this->catalog()[$plan]['price_cents'] ?? 0);
    }
}
