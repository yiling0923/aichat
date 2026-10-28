<?php

namespace App\Http\Controllers;

use App\Booking\Bookings;
use App\Booking\SlotTakenException;
use App\Models\Booking;
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
                ->when($show === 'upcoming', fn ($q) => $q->where('status', 'confirmed')->where('ends_at', '>=', now())->orderBy('starts_at'))
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
            $title = $first->format('F Y');
            $prev = $first->copy()->subMonth();
            $next = $first->copy()->addMonth();
        } else {
            $from = $anchor->copy()->startOfWeek(Carbon::MONDAY);
            $to = $from->copy()->endOfWeek(Carbon::SUNDAY);
            $title = $from->format('j M').' to '.$to->format('j M Y');
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

        return back()->with('status', 'Booking cancelled (and removed from Google Calendar if it was there).');
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
            return $fail('Only confirmed bookings can be moved.');
        }

        $start = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at'], $tz);
        if ($start->lte(Carbon::now($tz))) {
            return $fail('Choose a time in the future.');
        }

        try {
            $result = $bookings->reschedule($booking, $start, (bool) ($data['notify'] ?? false));
        } catch (SlotTakenException $e) {
            return $fail($e->getMessage());
        }

        $message = 'Moved to '.$start->format('D j M, H:i').'.'.(! empty($data['notify']) ? ($result['notified'] ? ' The customer was told in the chat.' : ' The customer could not be told (the chat window is closed).') : '');

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message]) : back()->with('status', $message);
    }

    public function mark(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:completed,no_show']]);
        $booking = Booking::query()->findOrFail($id);

        if ($booking->status === 'confirmed') {
            $booking->update(['status' => $data['status']]);
        }

        return back()->with('status', 'Booking updated.');
    }
}
