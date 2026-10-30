<?php

namespace App\Http\Controllers;

use App\Booking\Bookings;
use App\Booking\SlotTakenException;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Practitioner;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The staff schedule: upcoming and past bookings, cancel, mark done or no-show.
 */
class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $business = $request->user()->business;
        $tz = $business->timezone ?: 'UTC';
        $view = in_array($request->query('view'), ['month', 'week', 'day', 'list'], true) ? $request->query('view') : 'month';
        $practitionerId = $request->integer('practitioner') ?: null;

        $data = ['business' => $business, 'view' => $view, 'practitionerId' => $practitionerId,
            'practitioners' => Practitioner::query()->orderBy('name')->get(), 'tz' => $tz] + $this->summary($practitionerId);

        if ($view === 'list') {
            $show = $request->query('show', 'upcoming');
            $bookings = Booking::query()
                ->with(['service', 'practitioner', 'customer'])
                ->when($practitionerId, fn ($q) => $q->where('practitioner_id', $practitionerId))
                // Upcoming also keeps recent visits nobody has marked Done or No-show yet, so they are never lost.
                ->when($show === 'upcoming', fn ($q) => $q->where('status', 'confirmed')->where('ends_at', '>=', now()->subDays(30))->orderBy('starts_at'))
                ->when($show !== 'upcoming', fn ($q) => $q->where(fn ($w) => $w->where('status', '!=', 'confirmed')->orWhere('ends_at', '<', now()))->orderByDesc('starts_at'))
                ->limit(200)
                ->get();

            return view('admin.schedule', $data + ['bookings' => $bookings, 'show' => $show, 'active' => 'schedule']);
        }

        try {
            $anchor = Carbon::parse((string) $request->query('date', 'today'), $tz)->startOfDay();
        } catch (\Throwable) {
            $anchor = Carbon::now($tz)->startOfDay();
        }

        if ($view === 'month') {
            $first = $anchor->copy()->startOfMonth();
            $from = $first->copy()->startOfWeek(Carbon::MONDAY);
            $to = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            $title = $first->translatedFormat(app()->getLocale() === 'zh' ? 'Y年n月' : 'F Y');
            $prev = $first->copy()->subMonth();
            $next = $first->copy()->addMonth();
        } elseif ($view === 'day') {
            $from = $anchor->copy();
            $to = $anchor->copy()->endOfDay();
            $title = app()->getLocale() === 'zh' ? $anchor->translatedFormat('Y年n月j日 l') : $anchor->format('l, j F Y');
            $prev = $anchor->copy()->subDay();
            $next = $anchor->copy()->addDay();
        } else {
            $from = $anchor->copy()->startOfWeek(Carbon::MONDAY);
            $to = $from->copy()->endOfWeek(Carbon::SUNDAY);
            $title = app()->getLocale() === 'zh' ? $from->translatedFormat('n月j日').' 至 '.$to->translatedFormat('Y年n月j日') : $from->format('j M').' to '.$to->format('j M Y');
            $prev = $from->copy()->subWeek();
            $next = $from->copy()->addWeek();
        }

        $bookings = Booking::query()->with(['service', 'practitioner', 'customer'])
            ->whereBetween('starts_at', [$from->copy()->utc(), $to->copy()->utc()])->where('status', '!=', 'cancelled')
            ->when($practitionerId, fn ($q) => $q->where('practitioner_id', $practitionerId))
            ->orderBy('starts_at')->limit(1000)->get();

        $byDay = [];
        foreach ($bookings as $b) {
            $byDay[$b->starts_at->copy()->setTimezone($tz)->format('Y-m-d')][] = $b;
        }

        $data += ['title' => $title, 'anchor' => $anchor, 'from' => $from, 'to' => $to, 'prev' => $prev, 'next' => $next, 'byDay' => $byDay,
            'bookings' => $bookings, 'today' => Carbon::now($tz)->startOfDay(), 'active' => 'schedule'];

        if ($view === 'week') {
            $data += $this->weekLayout($byDay, $tz, $business);
        }

        // Day view: one column per practitioner (just the chosen one when a person is picked), laid out like the week grid.
        if ($view === 'day') {
            $columns = $data['practitioners']->when($practitionerId, fn ($c) => $c->where('id', $practitionerId))->values();
            $byPerson = [];
            foreach ($bookings as $b) {
                $byPerson[$b->practitioner_id][] = $b;
            }
            $data += ['columns' => $columns, 'byPerson' => $byPerson] + $this->weekLayout($byPerson, $tz, $business);
        }

        return view('admin.schedule', $data);
    }

    /**
     * The strip above the calendar: the next booking, and finished visits nobody has marked Done or No-show yet.
     *
     * @return array{nextBooking: ?Booking, toMark: int}
     */
    private function summary(?int $practitionerId): array
    {
        $scope = fn () => Booking::query()->where('status', 'confirmed')->when($practitionerId, fn ($q) => $q->where('practitioner_id', $practitionerId));

        return [
            'nextBooking' => $scope()->with(['service', 'practitioner', 'customer'])->where('starts_at', '>', now())->orderBy('starts_at')->first(),
            'toMark' => $scope()->where('ends_at', '<', now())->where('ends_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Where each booking sits in the week grid: hour range, and side-by-side lanes for bookings that overlap.
     *
     * @param  array<string, list<Booking>>  $byDay
     * @return array{startHour: int, endHour: int, lanes: array<int, array{lane: int, of: int}>}
     */
    private function weekLayout(array $byDay, string $tz, \App\Models\Business $business): array
    {
        [$startHour, $endHour] = $this->businessHourRange($business);
        $lanes = [];

        foreach ($byDay as $list) {
            $laneEnds = [];
            foreach ($list as $b) {
                $start = $b->starts_at->copy()->setTimezone($tz);
                $end = $b->ends_at->copy()->setTimezone($tz);
                if ($end->lte($start) || ! $end->isSameDay($start)) {   // an end that is missing, before the start or on another day still gets a sensible block
                    $end = $start->copy()->addMinutes(max(15, (int) ($b->service->duration_min ?? 30)));
                }
                $startHour = min($startHour, $start->hour);
                $endHour = max($endHour, $end->minute > 0 ? $end->hour + 1 : $end->hour);

                $placed = null;
                foreach ($laneEnds as $i => $laneEnd) {
                    if ($laneEnd->lte($start)) {
                        $placed = $i;
                        break;
                    }
                }
                $placed ??= count($laneEnds);
                $laneEnds[$placed] = $end;
                $lanes[$b->id] = ['lane' => $placed, 'of' => 1];
            }
            foreach ($list as $b) {
                $lanes[$b->id]['of'] = max(1, count($laneEnds));
            }
        }

        return ['startHour' => $startHour, 'endHour' => min(24, $endHour), 'lanes' => $lanes, 'openHours' => (array) ($business->settings['hours'] ?? []), 'closedDates' => (array) ($business->settings['closed_dates'] ?? [])];
    }

    /** The hours the week grid shows: from an hour before you open to an hour after you close (9 to 19 when no hours are set). @return array{0: int, 1: int} */
    private function businessHourRange(\App\Models\Business $business): array
    {
        $opens = [];
        $closes = [];

        foreach ((array) ($business->settings['hours'] ?? []) as $window) {
            if (is_array($window) && count($window) === 2) {
                $opens[] = (int) substr((string) $window[0], 0, 2);
                $closes[] = (int) ceil(((int) substr((string) $window[1], 0, 2)) + ((int) substr((string) $window[1], 3, 2)) / 60);
            }
        }

        return $opens === [] ? [9, 19] : [max(0, min($opens) - 1), min(24, max($closes) + 1)];
    }

    public function cancel(Request $request, int $id, Bookings $bookings): RedirectResponse
    {
        $bookings->cancel(Booking::query()->findOrFail($id), 'staff');

        return back()->with('status', __('Booking cancelled (and removed from Google Calendar if it was there).'));
    }

    /** Move a booking to another time (drag and drop on the calendar, or the form in the pop-up). */
    public function move(Request $request, int $id, Bookings $bookings): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d H:i'], 'notify' => ['nullable', 'boolean']]);
        $booking = Booking::query()->with(['service', 'practitioner', 'customer', 'conversation'])->findOrFail($id);
        $tz = $request->user()->business->timezone ?: 'UTC';
        $fail = fn (string $message) => $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => $message], 422)
            : back()->withErrors(['starts_at' => $message]);

        if ($booking->status !== 'confirmed') {
            return $fail(__('Only confirmed bookings can be moved.'));
        }

        $start = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at'], $tz);
        if ($start->lte(Carbon::now($tz))) {
            return $fail(__('Choose a time in the future.'));
        }

        try {
            $result = $bookings->reschedule($booking, $start, (bool) ($data['notify'] ?? false));
        } catch (SlotTakenException $e) {
            return $fail($e->getMessage());
        }

        $message = __('Moved to :when.', ['when' => \App\Support\Lang::fmt($start, 'D j M, H:i', 'n月j日 D H:i')]).(! empty($data['notify']) ? ' '.($result['notified'] ? __('The customer was told in the chat.') : __('The customer could not be told (the chat window is closed).')) : '');

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message]) : back()->with('status', $message);
    }

    public function mark(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:completed,no_show']]);
        $booking = Booking::query()->with(['service', 'customer'])->findOrFail($id);
        $message = __('Booking updated.');
        $order = null;

        if ($booking->status === 'completed' && $data['status'] === 'completed') {
            $order = Order::query()->where('reference', 'BK-'.$booking->id)->where('status', 'pending')->first();
            if ($order !== null) {
                $message = __('This visit is already done. The payment is still waiting, so it is open below.');
            }
        }

        if ($booking->status === 'confirmed') {
            $booking->update(['status' => $data['status']]);

            if ($data['status'] === 'completed') {
                $order = $this->orderForVisit($booking);
                if ($order === null && (float) ($booking->service->price ?? 0) <= 0) {
                    $message = __('Marked done. This service has no price, so no payment was created. Add a price in Booking setup, or add an order in Orders.');
                }
                if ($order !== null) {
                    $cur = $request->user()->business->settings['currency'] ?? 'RM';
                    $message = __('Marked done. Collect the payment of :amount below, or close this and mark it paid later.', ['amount' => $cur.' '.number_format((float) $order->amount, 2)]);
                }
            }
        }

        // Straight to the payment details for this visit, so the shop can take the money right away.
        return $order !== null ? redirect()->route('orders', ['pay' => $order->id])->with('status', $message) : back()->with('status', $message);
    }

    /** The customer pays at the shop, so a finished visit becomes an unpaid order. Free services and repeats make none. */
    private function orderForVisit(Booking $booking): ?Order
    {
        $price = (float) ($booking->service->price ?? 0);
        $reference = 'BK-'.$booking->id;

        if ($price <= 0 || Order::query()->where('reference', $reference)->exists()) {
            return null;
        }

        return Order::create([
            'customer_id' => $booking->customer_id,
            'reference' => $reference,
            'items' => $booking->service->label('en'),
            'amount' => $price,
            'status' => 'pending',
            'notes' => 'From the visit on '.$booking->starts_at->copy()->setTimezone($booking->business->timezone ?: 'UTC')->format('j M Y, H:i').' with '.($booking->practitioner->name ?? 'staff').'.',
            'ordered_at' => now(),
        ]);
    }
}
