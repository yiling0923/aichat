<?php

namespace App\Bot;

use App\Booking\Availability;
use App\Booking\Bookings;
use App\Booking\SlotTakenException;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Service;
use App\Services\LeadService;
use Carbon\Carbon;
use Throwable;

/**
 * The booking part of the menu bot: service, day, time, name, phone, confirm.
 * Progress lives in conversation state under "booking". Every method returns
 * ['out' => messages, 'status' => continue|done|handover, 'reason' => text].
 */
class BookingStep
{
    private const WEEKDAYS = [
        'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        'zh' => ['周日', '周一', '周二', '周三', '周四', '周五', '周六'],
    ];

    private const MONTHS = [
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    ];

    private const TIMES_PER_PAGE = 8;

    public function __construct(private Availability $availability, private Bookings $bookings, private LeadService $leads) {}

    /** Start a new booking and show the services. */
    public function begin(Conversation $c, array &$state, string $language): array
    {
        $state['booking'] = ['stage' => 'service', 'offset' => 0, 'page' => 0];

        return $this->prompt($c, $state, $language);
    }

    /** @param array<string, mixed> $state */
    public function handle(Conversation $c, array &$state, string $text, ?string $optionId, string $language): array
    {
        $b = $state['booking'] ?? ['stage' => 'service', 'offset' => 0, 'page' => 0];

        try {
            return match ($b['stage']) {
                'service', 'day', 'time' => $this->choose($c, $state, $b, $text, $optionId, $language),
                'name' => $this->name($c, $state, $b, $text, $language),
                'phone' => $this->phone($c, $state, $b, $text, $language),
                default => $this->result([], 'continue'),
            };
        } catch (SlotTakenException) {
            $state['booking']['stage'] = 'time';
            $out = [$c->addMessage('out', 'bot', Strings::get('booking_slot_taken', $language))];

            return $this->merge($out, $this->prompt($c, $state, $language));
        } catch (Throwable $e) {
            report($e);

            return $this->handoverResult($c, 'booking_error', 'Booking failed: '.$e->getMessage(), $language);
        }
    }

    /** @return array<string, mixed> */
    public function prompt(Conversation $c, array &$state, string $language): array
    {
        $business = $c->business;
        $b = $state['booking'];

        try {
            if ($b['stage'] === 'service') {
                $services = $this->services();
                if ($services->isEmpty()) {
                    return $this->handoverResult($c, 'booking_no_slots', 'No bookable services set up', $language);
                }
                $options = $services->map(fn (Service $s) => ['id' => 'svc_'.$s->id, 'label' => $s->label($language)])->all();

                return $this->ask($c, 'booking_pick_service', $options, $language);
            }

            $service = Service::query()->find($b['service_id'] ?? 0);
            if ($service === null) {
                $state['booking'] = ['stage' => 'service', 'offset' => 0, 'page' => 0];

                return $this->prompt($c, $state, $language);
            }

            if ($b['stage'] === 'day') {
                $days = $this->availability->days($business, $service);
                if ($days === []) {
                    return $this->handoverResult($c, 'booking_no_slots', 'No free times for '.$service->label('en'), $language);
                }
                $per = (int) config('aichat.booking_days_per_page', 5);
                $shown = array_slice($days, (int) $b['offset'], $per);
                $options = array_map(fn (string $d) => ['id' => 'day_'.$d, 'label' => $this->dayLabel($d, $language)], $shown);
                if ((int) $b['offset'] + $per < count($days)) {
                    $options[] = ['id' => 'more_days', 'label' => Strings::get('opt_more_days', $language)];
                }

                return $this->ask($c, 'booking_pick_day', $options, $language);
            }

            if ($b['stage'] === 'time') {
                $slots = $this->availability->slots($business, $service, $b['date']);
                if ($slots === []) {
                    $state['booking']['stage'] = 'day';

                    return $this->merge([$c->addMessage('out', 'bot', Strings::get('booking_slot_taken', $language))], $this->prompt($c, $state, $language));
                }
                $page = (int) $b['page'];
                $shown = array_slice($slots, $page * self::TIMES_PER_PAGE, self::TIMES_PER_PAGE);
                $options = array_map(fn (array $s) => ['id' => 'slot_'.$s['time'], 'label' => $s['time']], $shown);
                if (($page + 1) * self::TIMES_PER_PAGE < count($slots)) {
                    $options[] = ['id' => 'more_times', 'label' => Strings::get('opt_more_times', $language)];
                }

                return $this->ask($c, 'booking_pick_time', $options, $language, ['day' => $this->dayLabel($b['date'], $language)]);
            }
        } catch (Throwable $e) {
            report($e);

            return $this->handoverResult($c, 'booking_error', 'Calendar error: '.$e->getMessage(), $language);
        }

        if ($b['stage'] === 'name') {
            return $this->result([$c->addMessage('out', 'bot', Strings::get('booking_ask_name', $language))]);
        }

        return $this->result([$c->addMessage('out', 'bot', Text::fill(Strings::get('booking_ask_phone', $language), $state['data'] ?? []))]);
    }

    /** @param array<string, mixed> $b */
    private function choose(Conversation $c, array &$state, array $b, string $text, ?string $optionId, string $language): array
    {
        $id = $optionId ?? $this->matchTyped($c, $state, $b, $text, $language);

        if ($id === 'back' && $b['stage'] === 'service') {
            return $this->result([], 'back');
        }

        if ($id === 'back') {
            $state['booking']['stage'] = $b['stage'] === 'time' ? 'day' : 'service';
            $state['booking']['page'] = 0;
            $state['booking']['offset'] = 0;

            return $this->prompt($c, $state, $language);
        }

        if ($b['stage'] === 'service' && str_starts_with((string) $id, 'svc_')) {
            $service = $this->services()->firstWhere('id', (int) substr($id, 4));
            if ($service !== null) {
                $state['booking'] = ['stage' => 'day', 'service_id' => $service->id, 'offset' => 0, 'page' => 0];
                $state['data']['interest'] = $service->label($language);

                return $this->prompt($c, $state, $language);
            }
        }

        if ($b['stage'] === 'day') {
            if ($id === 'more_days') {
                $state['booking']['offset'] = (int) $b['offset'] + (int) config('aichat.booking_days_per_page', 5);

                return $this->prompt($c, $state, $language);
            }
            if (str_starts_with((string) $id, 'day_')) {
                $state['booking']['stage'] = 'time';
                $state['booking']['date'] = substr($id, 4);
                $state['booking']['page'] = 0;

                return $this->prompt($c, $state, $language);
            }
        }

        if ($b['stage'] === 'time') {
            if ($id === 'more_times') {
                $state['booking']['page'] = (int) $b['page'] + 1;

                return $this->prompt($c, $state, $language);
            }
            if (str_starts_with((string) $id, 'slot_')) {
                $state['booking']['time'] = substr($id, 5);

                return $this->afterTime($c, $state, $language);
            }
        }

        $state['misses'] = ((int) ($state['misses'] ?? 0)) + 1;
        if ($state['misses'] > (int) config('aichat.max_misses')) {
            return $this->handoverResult($c, null, 'Bot could not understand the customer', $language);
        }

        return $this->merge([$c->addMessage('out', 'bot', Strings::get('sorry_choose', $language))], $this->prompt($c, $state, $language));
    }

    private function afterTime(Conversation $c, array &$state, string $language): array
    {
        $state['misses'] = 0;
        $data = $state['data'] ?? [];

        if (! empty($data['name']) && ! empty($data['phone'])) {
            return $this->finish($c, $state, $language);
        }

        $state['booking']['stage'] = empty($data['name']) ? 'name' : 'phone';

        return $this->prompt($c, $state, $language);
    }

    private function name(Conversation $c, array &$state, array $b, string $text, string $language): array
    {
        if ($this->typedBack($text)) {
            return $this->backToTime($c, $state, $language);
        }

        $name = trim($text);
        if ($name === '') {
            return $this->merge([$c->addMessage('out', 'bot', Strings::get('sorry_ask', $language))], $this->prompt($c, $state, $language));
        }

        $state['data']['name'] = mb_substr($name, 0, 120);

        if (! empty($state['data']['phone'])) {
            return $this->finish($c, $state, $language);
        }

        $state['booking']['stage'] = 'phone';

        return $this->prompt($c, $state, $language);
    }

    private function phone(Conversation $c, array &$state, array $b, string $text, string $language): array
    {
        if ($this->typedBack($text)) {
            return $this->backToTime($c, $state, $language);
        }

        $phone = $this->leads->normalizePhone($text);
        $digits = $phone === null ? 0 : strlen(ltrim($phone, '+'));

        if ($digits < 7 || $digits > 15) {
            $state['misses'] = ((int) ($state['misses'] ?? 0)) + 1;
            if ($state['misses'] > (int) config('aichat.max_misses')) {
                return $this->handoverResult($c, null, 'Bot could not get a valid phone number', $language);
            }

            return $this->result([$c->addMessage('out', 'bot', Strings::get('invalid_phone', $language))]);
        }

        $state['misses'] = 0;
        $state['data']['phone'] = $phone;

        return $this->finish($c, $state, $language);
    }

    private function typedBack(string $text): bool
    {
        return Text::isCommand(Text::normalize($text), config('aichat.keywords.back'));
    }

    private function backToTime(Conversation $c, array &$state, string $language): array
    {
        $state['booking']['stage'] = 'time';
        $state['booking']['page'] = 0;

        return $this->prompt($c, $state, $language);
    }

    private function finish(Conversation $c, array &$state, string $language): array
    {
        $b = $state['booking'];
        $service = Service::query()->findOrFail($b['service_id']);
        $tz = $c->business->timezone ?: 'UTC';
        $start = Carbon::parse($b['date'].' '.$b['time'], $tz);

        // Pick whoever is free at this time (the first free practitioner).
        $slot = collect($this->availability->slots($c->business, $service, $b['date']))->firstWhere('time', $b['time']);
        if ($slot === null) {
            throw new SlotTakenException;
        }

        $practitioner = $this->availability->practitionersFor($service)->firstWhere('id', $slot['practitioner_id']);

        // The menu preview shows the confirmation but never saves a booking.
        if ($c->channel !== 'preview') {
            $this->bookings->book($c, $service, $practitioner, $start, $state['data'] ?? []);
        }

        unset($state['booking']);
        $text = Text::fill(Strings::get('booking_confirmed', $language), [
            'name' => $state['data']['name'] ?? '',
            'service' => $service->label($language),
            'when' => $this->dayLabel($b['date'], $language).', '.$b['time'],
            'who' => $practitioner->name,
        ]);

        return $this->result([$c->addMessage('out', 'bot', $text)], 'done');
    }

    /** Show the customer's next booking with cancel / keep buttons. Returns null when there is none. */
    public function summary(Conversation $c, string $language): ?array
    {
        $booking = $this->upcoming($c);

        if ($booking === null) {
            return null;
        }

        $text = Text::fill(Strings::get('booking_summary', $language), [
            'service' => $booking->service->label($language),
            'when' => $this->whenLabel($booking, $language),
            'who' => $booking->practitioner->name,
        ]);
        $options = [
            ['id' => 'bk_cancel', 'label' => Strings::get('opt_cancel', $language)],
            ['id' => 'bk_keep', 'label' => Strings::get('opt_keep', $language)],
        ];

        return $this->result([$c->addMessage('out', 'bot', $text, ['options' => $options])]);
    }

    /** Handle Cancel / Keep on the My booking step. */
    public function handleSummary(Conversation $c, string $text, ?string $optionId, string $language): array
    {
        $normalized = Text::normalize($text);
        $cancel = $optionId === 'bk_cancel' || $normalized === Text::normalize(Strings::get('opt_cancel', $language));
        $keep = $optionId === 'bk_keep' || $normalized === Text::normalize(Strings::get('opt_keep', $language));

        if ($cancel) {
            $booking = $this->upcoming($c);
            if ($booking === null) {
                return $this->result([$c->addMessage('out', 'bot', Strings::get('booking_none_upcoming', $language))], 'done');
            }
            if (! $this->bookings->customerMayCancel($booking)) {
                $hours = \App\Booking\BookingRules::for($c->business)['cancel_hours'];

                return $this->handoverResult($c, null, 'Customer asked to cancel a booking inside the cancellation window', $language, Text::fill(Strings::get('booking_cancel_late', $language), ['hours' => $hours]));
            }
            $this->bookings->cancel($booking);

            return $this->result([$c->addMessage('out', 'bot', Strings::get('booking_cancelled', $language))], 'done');
        }

        if ($keep) {
            return $this->result([], 'done');
        }

        return $this->result([$c->addMessage('out', 'bot', Strings::get('sorry_choose', $language))], 'retry');
    }

    private function upcoming(Conversation $c): ?Booking
    {
        return Booking::query()
            ->where('status', 'confirmed')
            ->where('starts_at', '>', now())
            ->where(fn ($q) => $q->where('conversation_id', $c->id)->when($c->customer_id, fn ($w) => $w->orWhere('customer_id', $c->customer_id)))
            ->with(['service', 'practitioner'])
            ->orderBy('starts_at')
            ->first();
    }

    public function whenLabel(Booking $booking, string $language): string
    {
        $local = $booking->starts_at->copy()->setTimezone($booking->business->timezone ?: 'UTC');

        return $this->dayLabel($local->format('Y-m-d'), $language).', '.$local->format('H:i');
    }

    private function dayLabel(string $date, string $language): string
    {
        $d = Carbon::parse($date);
        $w = (self::WEEKDAYS[$language] ?? self::WEEKDAYS['en'])[$d->dayOfWeek];

        return $language === 'zh'
            ? $d->month.'月'.$d->day.'日 '.$w
            : $w.' '.$d->day.' '.self::MONTHS['en'][$d->month - 1];
    }

    /** @return \Illuminate\Support\Collection<int, Service> */
    private function services()
    {
        return Service::query()->where('active', true)->whereHas('practitioners', fn ($q) => $q->where('practitioners.active', true))
            ->orderBy('sort')->orderBy('id')->get();
    }

    /** Map a typed number or label to the id of the option currently shown. */
    private function matchTyped(Conversation $c, array $state, array $b, string $text, string $language): ?string
    {
        $options = $this->currentOptions($c, $b, $language);
        $normalized = Text::normalize($text);

        if (ctype_digit($normalized) && isset($options[(int) $normalized - 1])) {
            return $options[(int) $normalized - 1]['id'];
        }

        foreach ($options as $o) {
            if ($normalized !== '' && Text::normalize($o['label']) === $normalized) {
                return $o['id'];
            }
        }

        return null;
    }

    /** @return list<array{id: string, label: string}> */
    private function currentOptions(Conversation $c, array $b, string $language): array
    {
        $last = $c->messages()->where('sender_type', 'bot')->where('is_private', false)->orderByDesc('id')->get()
            ->first(fn ($m) => ! empty($m->payload['options']));

        return $last?->payload['options'] ?? [];
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @param  array<string, string>  $values
     */
    private function ask(Conversation $c, string $key, array $options, string $language, array $values = [], bool $back = true): array
    {
        if ($back) {
            $options[] = ['id' => 'back', 'label' => '← '.Strings::get('opt_back', $language)];
        }

        return $this->result([$c->addMessage('out', 'bot', Text::fill(Strings::get($key, $language), $values), ['options' => $options])]);
    }

    private function handoverResult(Conversation $c, ?string $key, string $reason, string $language, ?string $text = null): array
    {
        $out = [];
        $text ??= $key !== null ? Strings::get($key, $language) : null;
        if ($text !== null) {
            $out[] = $c->addMessage('out', 'bot', $text);
        }

        return $this->result($out, 'handover', $reason);
    }

    /** @param list<\App\Models\Message> $out */
    private function result(array $out, string $status = 'continue', ?string $reason = null): array
    {
        return ['out' => $out, 'status' => $status, 'reason' => $reason];
    }

    private function merge(array $out, array $next): array
    {
        $next['out'] = array_merge($out, $next['out']);

        return $next;
    }
}
