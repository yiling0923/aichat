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
        $view = in_array($request->query('view'), ['month', 'week', 'list'], true) ? $request->query('view') : 'month';
        $practitionerId = $request->integer('practitioner') ?: null;

        $data = ['business' => $business, 'view' => $view, 'practitionerId' => $practitionerId,
            'practitioners' => Practitioner::query()->orderBy('name')->get(), 'tz' => $tz];

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
            $data += $this->weekLayout($byDay, $tz);
        }

        return view('admin.schedule', $data);
    }

    /**
     * Where each booking sits in the week grid: hour range, and side-by-side lanes for bookings that overlap.
     *
     * @param  array<string, list<Booking>>  $byDay
     * @return array{startHour: int, endHour: int, lanes: array<int, array{lane: int, of: int}>}
     */
    private function weekLayout(array $byDay, string $tz): array
    {
        $startHour = 9;
        $endHour = 19;
        $lanes = [];

        foreach ($byDay as $list) {
            $laneEnds = [];
            foreach ($list as $b) {
                $start = $b->starts_at->copy()->setTimezone($tz);
                $end = $b->ends_at->copy()->setTimezone($tz);
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

        return ['startHour' => $startHour, 'endHour' => min(24, $endHour), 'lanes' => $lanes];
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

        if ($booking->status === 'confirmed') {
            $booking->update(['status' => $data['status']]);

            if ($data['status'] === 'completed') {
                $order = $this->orderForVisit($booking);
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
