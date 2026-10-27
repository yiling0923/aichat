<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The first page of the day: what needs a person, today's bookings, tasks due.
 */
class TodayController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $business = $user->business;
        $tz = $business->timezone ?: 'UTC';
        $start = now($tz)->startOfDay()->utc();
        $end = now($tz)->endOfDay()->utc();

        $waiting = Conversation::query()->where('channel', '!=', 'preview')->where('status', 'open')->where('mode', 'human')
            ->orderBy('handover_at')->limit(8)->get();

        $bookings = Booking::query()->with(['service', 'practitioner', 'customer'])->where('status', 'confirmed')
            ->whereBetween('starts_at', [$start, $end])->orderBy('starts_at')->get();

        $tasks = Task::query()->with('assignee')->whereNull('done_at')
            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '<=', $end))
            ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', $user->id))
            ->orderByRaw('due_at is null')->orderBy('due_at')->limit(8)->get();

        return view('admin.today', [
            'business' => $business,
            'active' => 'today',
            'waiting' => $waiting,
            'bookings' => $bookings,
            'tasks' => $tasks,
            'newLeads' => Customer::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'openChats' => Conversation::query()->where('channel', '!=', 'preview')->where('status', 'open')->count(),
            'hour' => (int) now($tz)->format('G'),
        ]);
    }
}
