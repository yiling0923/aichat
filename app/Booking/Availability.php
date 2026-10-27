<?php

namespace App\Booking;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Which times can be booked. Combines the business hours, existing bookings,
 * the buffer between appointments, and (when set up) each practitioner's Google Calendar.
 * A calendar that cannot be read throws, so the bot hands over instead of guessing.
 */
class Availability
{
    public function __construct(private GoogleCalendar $google) {}

    /**
     * Dates (Y-m-d, in the business time zone) that have at least one free time.
     *
     * @return list<string>
     */
    public function days(Business $business, Service $service, ?CarbonInterface $now = null): array
    {
        $rules = BookingRules::for($business);
        $tz = $business->timezone ?: 'UTC';
        $today = ($now ?? now())->copy()->setTimezone($tz)->startOfDay();
        $practitioners = $this->practitionersFor($service);

        if ($practitioners->isEmpty()) {
            return [];
        }

        $busy = $this->busy($practitioners, $today, $today->copy()->addDays($rules['max_days_ahead'] + 1));
        $days = [];

        for ($i = 0; $i <= $rules['max_days_ahead']; $i++) {
            $date = $today->copy()->addDays($i);
            if ($this->freeStarts($business, $service, $practitioners, $busy, $date, $rules, $now) !== []) {
                $days[] = $date->format('Y-m-d');
            }
        }

        return $days;
    }

    /**
     * Free start times on a date, each with the practitioner who would take it.
     *
     * @return list<array{time: string, practitioner_id: int}>
     */
    public function slots(Business $business, Service $service, string $date, ?CarbonInterface $now = null): array
    {
        $rules = BookingRules::for($business);
        $day = Carbon::parse($date, $business->timezone ?: 'UTC')->startOfDay();
        $practitioners = $this->practitionersFor($service);

        if ($practitioners->isEmpty()) {
            return [];
        }

        $busy = $this->busy($practitioners, $day, $day->copy()->addDay());

        return $this->freeStarts($business, $service, $practitioners, $busy, $day, $rules, $now);
    }

    /** Is this exact slot still free for this practitioner? Used right before saving. */
    public function isFree(Business $business, Service $service, Practitioner $practitioner, CarbonInterface $start): bool
    {
        $rules = BookingRules::for($business);
        $end = $start->copy()->addMinutes($service->duration_min);
        $busy = $this->busy(collect([$practitioner]), $start->copy()->startOfDay()->subDay(), $end->copy()->addDay());

        return ! $this->conflicts($busy[$practitioner->id] ?? [], $start, $end, $rules['buffer_min']);
    }

    /** @return Collection<int, Practitioner> */
    public function practitionersFor(Service $service): Collection
    {
        return $service->practitioners()->where('practitioners.active', true)->orderBy('practitioners.id')->get();
    }

    /**
     * @param  Collection<int, Practitioner>  $practitioners
     * @param  array<int, list<array{0: CarbonInterface, 1: CarbonInterface}>>  $busy
     * @param  array<string, int>  $rules
     * @return list<array{time: string, practitioner_id: int}>
     */
    private function freeStarts(Business $business, Service $service, Collection $practitioners, array $busy, Carbon $day, array $rules, ?CarbonInterface $now): array
    {
        $window = $this->openWindow($business, $day);

        if ($window === null) {
            return [];
        }

        [$open, $close] = $window;
        $earliest = ($now ?? now())->copy()->addHours($rules['min_notice_hours']);
        $latest = ($now ?? now())->copy()->addDays($rules['max_days_ahead'] + 1)->startOfDay();
        $duration = $service->duration_min;
        $found = [];

        for ($start = $open->copy(); $start->copy()->addMinutes($duration)->lte($close); $start->addMinutes($rules['slot_step_min'])) {
            if ($start->lt($earliest) || $start->gte($latest)) {
                continue;
            }
            $end = $start->copy()->addMinutes($duration);

            foreach ($practitioners as $p) {
                if (! $this->conflicts($busy[$p->id] ?? [], $start, $end, $rules['buffer_min'])) {
                    $found[] = ['time' => $start->format('H:i'), 'practitioner_id' => $p->id];
                    break;
                }
            }
        }

        return $found;
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function openWindow(Business $business, Carbon $day): ?array
    {
        $settings = (array) $business->settings;

        if (in_array($day->format('Y-m-d'), (array) ($settings['closed_dates'] ?? []), true)) {
            return null;
        }

        $hours = $settings['hours'] ?? null;

        // No hours saved: assume 09:00 to 18:00 every day, so booking still works.
        $today = is_array($hours) && $hours !== []
            ? ($hours[strtolower($day->format('D'))] ?? null)
            : ['09:00', '18:00'];

        if (! is_array($today) || count($today) !== 2) {
            return null;
        }

        return [
            $day->copy()->setTimeFromTimeString($today[0]),
            $day->copy()->setTimeFromTimeString($today[1]),
        ];
    }

    /**
     * @param  list<array{0: CarbonInterface, 1: CarbonInterface}>  $busy
     */
    private function conflicts(array $busy, CarbonInterface $start, CarbonInterface $end, int $buffer): bool
    {
        foreach ($busy as [$from, $to]) {
            if ($start->lt($to->copy()->addMinutes($buffer)) && $end->copy()->addMinutes($buffer)->gt($from)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Busy intervals per practitioner: saved bookings plus Google Calendar.
     *
     * @param  Collection<int, Practitioner>  $practitioners
     * @return array<int, list<array{0: CarbonInterface, 1: CarbonInterface}>>
     */
    private function busy(Collection $practitioners, CarbonInterface $from, CarbonInterface $to): array
    {
        $result = [];

        foreach ($practitioners as $p) {
            $result[$p->id] = Booking::query()
                ->where('practitioner_id', $p->id)
                ->where('status', 'confirmed')
                ->where('starts_at', '<', $to)
                ->where('ends_at', '>', $from)
                ->get()
                ->map(fn (Booking $b) => [$b->starts_at, $b->ends_at])
                ->all();

            if ($p->google_calendar_id && $this->google->enabled()) {
                $result[$p->id] = array_merge($result[$p->id], $this->google->busy($p->google_calendar_id, $from, $to));
            }
        }

        return $result;
    }
}
