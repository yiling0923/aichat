<?php

namespace App\Http\Controllers;

use App\Booking\Bookings;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The staff schedule: upcoming and past bookings, cancel, mark done or no-show.
 */
class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $show = $request->query('show', 'upcoming');

        $bookings = Booking::query()
            ->with(['service', 'practitioner', 'customer'])
            ->when($show === 'upcoming', fn ($q) => $q->where('status', 'confirmed')->where('ends_at', '>=', now())->orderBy('starts_at'))
            ->when($show !== 'upcoming', fn ($q) => $q->where(fn ($w) => $w->where('status', '!=', 'confirmed')->orWhere('ends_at', '<', now()))->orderByDesc('starts_at'))
            ->limit(200)
            ->get();

        return view('admin.schedule', [
            'business' => $request->user()->business,
            'bookings' => $bookings,
            'show' => $show,
        ]);
    }

    public function cancel(Request $request, int $id, Bookings $bookings): RedirectResponse
    {
        $bookings->cancel(Booking::query()->findOrFail($id), 'staff');

        return back()->with('status', 'Booking cancelled (and removed from Google Calendar if it was there).');
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
