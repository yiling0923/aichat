<?php

namespace App\Marketing;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Survey;
use App\Support\CurrentBusiness;
use Carbon\CarbonInterface;

/**
 * Finds who is due for each switched-on automation and sends it. Safe to run every few minutes:
 * every send is logged and never repeated for the same booking or customer.
 */
class AutomationRunner
{
    private const PER_RUN = 50;

    public function __construct(private CurrentBusiness $current, private Outreach $outreach) {}

    /** @return array<string, int> messages sent per automation */
    public function runForBusiness(Business $business, ?CarbonInterface $now = null): array
    {
        return $this->current->runAs($business, function () use ($business, $now): array {
            AutomationDefaults::ensure();
            $now = ($now ?? now())->copy();
            $sent = [];

            foreach (Automation::query()->where('enabled', true)->get() as $automation) {
                $sent[$automation->key] = match ($automation->key) {
                    'survey' => $this->survey($automation, $business, $now),
                    'no_show' => $this->noShow($automation, $business, $now),
                    'rebooking' => $this->rebooking($automation, $business, $now),
                    'hot_lead' => $this->hotLead($automation, $business, $now),
                    default => 0, // review_request is triggered by a good survey answer
                };
            }

            return $sent;
        });
    }

    private function survey(Automation $a, Business $business, CarbonInterface $now): int
    {
        $count = 0;

        $bookings = Booking::query()->with(['customer', 'service'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('ends_at', '<=', $now->copy()->subHours($a->delay_hours))
            ->where('ends_at', '>', $now->copy()->subDays(7))
            ->whereNotIn('id', AutomationLog::query()->where('automation_key', 'survey')->whereNotNull('booking_id')->pluck('booking_id'))
            ->orderBy('ends_at')->limit(self::PER_RUN)->get();

        foreach ($bookings as $b) {
            if ($b->customer === null) {
                $this->log('survey', null, $b, 'skipped', 'no customer');

                continue;
            }

            // One open survey per customer: the chat can only remember one. The next one is sent on a later run
            // (nothing is logged, so this booking is still due), once this one is answered or a day has passed.
            $waiting = Survey::query()->where('customer_id', $b->customer_id)->whereNull('answered_at')->whereNotNull('sent_at')
                ->where('sent_at', '>', $now->copy()->subDay())->exists();

            if ($waiting) {
                continue;
            }

            $language = $this->language($b->customer);
            $result = $this->outreach->send(
                $b->customer, (array) $a->message, $this->values($b->customer, $business, $b, $language),
                Outreach::ratingButtons($language), $a->template_name, [(string) $b->customer->name, $b->service->label($language)], true,
            );

            if ($result['status'] === 'sent' && $result['conversation'] !== null) {
                $survey = Survey::create(['booking_id' => $b->id, 'customer_id' => $b->customer_id, 'conversation_id' => $result['conversation']->id, 'sent_at' => now()]);
                $state = (array) $result['conversation']->state;
                $state['survey'] = $survey->id;
                $result['conversation']->forceFill(['state' => $state])->save();
                $count++;
            }

            $this->log('survey', $b->customer, $b, $result['status'], $result['reason']);
        }

        return $count;
    }

    /**
     * Send one survey right now to one customer, ignoring the wait and the visit. For the Test button on the Automations
     * page. It still obeys consent, STOP and the 24-hour rule, so a "skipped" result tells you exactly what blocked it.
     *
     * @return array{status: string, reason: ?string}
     */
    public function testSurvey(Automation $a, Business $business, Customer $customer): array
    {
        return $this->current->runAs($business, function () use ($a, $business, $customer): array {
            $language = $this->language($customer);
            $result = $this->outreach->send(
                $customer, (array) $a->message, $this->values($customer, $business, null, $language),
                Outreach::ratingButtons($language), $a->template_name, [(string) $customer->name, (string) ($customer->interest ?: 'your visit')],
            );

            if ($result['status'] === 'sent' && $result['conversation'] !== null) {
                $survey = Survey::create(['customer_id' => $customer->id, 'conversation_id' => $result['conversation']->id, 'sent_at' => now()]);
                $state = (array) $result['conversation']->state;
                $state['survey'] = $survey->id;
                $result['conversation']->forceFill(['state' => $state])->save();
            }

            $this->log('survey', $customer, null, $result['status'], $result['reason'] ?? 'test message');

            return ['status' => $result['status'], 'reason' => $result['reason'] ?? null];
        });
    }

    private function noShow(Automation $a, Business $business, CarbonInterface $now): int
    {
        $count = 0;

        $bookings = Booking::query()->with(['customer', 'service'])
            ->where('status', 'no_show')
            ->where('updated_at', '<=', $now->copy()->subHours($a->delay_hours))
            ->where('updated_at', '>', $now->copy()->subDays(3))
            ->whereNotIn('id', AutomationLog::query()->where('automation_key', 'no_show')->whereNotNull('booking_id')->pluck('booking_id'))
            ->limit(self::PER_RUN)->get();

        foreach ($bookings as $b) {
            $count += $this->followUp($a, $business, $b->customer, $b, $b->service?->label($this->language($b->customer)));
        }

        return $count;
    }

    private function rebooking(Automation $a, Business $business, CarbonInterface $now): int
    {
        $count = 0;
        $days = max(1, (int) ($a->settings['rebook_days'] ?? 30));
        $cutoff = $now->copy()->subDays($days);

        $customers = Customer::query()
            ->whereHas('bookings', fn ($q) => $q->whereIn('status', ['confirmed', 'completed'])->where('ends_at', '<=', $cutoff))
            ->whereDoesntHave('bookings', fn ($q) => $q->where('status', 'confirmed')->where('ends_at', '>', $cutoff))
            ->whereNotIn('id', AutomationLog::query()->where('automation_key', 'rebooking')->where('created_at', '>', $now->copy()->subDays(60))->pluck('customer_id'))
            ->limit(self::PER_RUN)->get();

        foreach ($customers as $c) {
            $last = Booking::query()->with('service')->where('customer_id', $c->id)->whereIn('status', ['confirmed', 'completed'])->orderByDesc('ends_at')->first();
            $count += $this->followUp($a, $business, $c, null, $last?->service?->label($this->language($c)));
        }

        return $count;
    }

    private function hotLead(Automation $a, Business $business, CarbonInterface $now): int
    {
        $count = 0;

        $customers = Customer::query()
            ->where('score', 'hot')
            ->where('created_at', '<=', $now->copy()->subHours($a->delay_hours))
            ->where('created_at', '>', $now->copy()->subDays(14))
            ->whereDoesntHave('bookings', fn ($q) => $q->whereIn('status', ['confirmed', 'completed']))
            ->whereNotIn('id', AutomationLog::query()->where('automation_key', 'hot_lead')->pluck('customer_id'))
            ->limit(self::PER_RUN)->get();

        foreach ($customers as $c) {
            $count += $this->followUp($a, $business, $c, null, $c->interest);
        }

        return $count;
    }

    /** A message with a "Book now" button. Returns 1 when sent. */
    private function followUp(Automation $a, Business $business, ?Customer $customer, ?Booking $booking, ?string $service): int
    {
        if ($customer === null) {
            $this->log($a->key, null, $booking, 'skipped', 'no customer');

            return 0;
        }

        $language = $this->language($customer);
        $values = $this->values($customer, $business, $booking, $language) + [];
        $values['service'] = $service ?: $customer->interest ?: '';

        $result = $this->outreach->send($customer, (array) $a->message, $values, Outreach::bookNowButton($language), $a->template_name, [(string) $customer->name, (string) $values['service']]);
        $this->log($a->key, $customer, $booking, $result['status'], $result['reason']);

        return $result['status'] === 'sent' ? 1 : 0;
    }

    private function log(string $key, ?Customer $customer, ?Booking $booking, string $status, ?string $reason): void
    {
        AutomationLog::create([
            'automation_key' => $key, 'customer_id' => $customer?->id, 'booking_id' => $booking?->id,
            'status' => $status, 'reason' => $reason, 'created_at' => now(),
        ]);
    }

    private function language(?Customer $customer): string
    {
        return $customer?->language ?? 'en';
    }

    /** @return array<string, mixed> */
    private function values(Customer $customer, Business $business, ?Booking $booking, string $language): array
    {
        return ['name' => $customer->name, 'business' => $business->name, 'service' => $booking?->service?->label($language) ?? $customer->interest];
    }
}
