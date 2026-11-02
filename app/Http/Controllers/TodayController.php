<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StaffNotification;
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
            ->orderBy('handover_at')->limit(6)->get();

        // Everything booked today, including visits already finished, so the day reads as a timeline.
        $bookings = Booking::query()->with(['service', 'practitioner', 'customer'])->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->whereBetween('starts_at', [$start, $end])->orderBy('starts_at')->get();

        $mine = fn ($q) => $q->where(fn ($w) => $w->whereNull('assigned_to')->orWhere('assigned_to', $user->id));
        // Late tasks get their own list so they cannot be missed; "tasks" is what is due today or has no date.
        $overdueQuery = Task::query()->whereNull('done_at')->where('due_at', '<', now())->tap($mine);
        $overdueCount = (clone $overdueQuery)->count();
        $overdue = (clone $overdueQuery)->with('assignee')->orderBy('due_at')->limit(5)->get();
        $tasks = Task::query()->with('assignee')->whereNull('done_at')
            ->where(fn ($q) => $q->whereNull('due_at')->orWhereBetween('due_at', [now(), $end]))
            ->tap($mine)->orderByRaw('due_at is null')->orderBy('due_at')->limit(8)->get();

        // The week at a glance: bookings per day, Monday to Sunday.
        $weekStart = now($tz)->startOfWeek();
        $week = [];
        $weekBookings = Booking::query()->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$weekStart->copy()->utc(), $weekStart->copy()->endOfWeek()->utc()])->pluck('starts_at');
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $week[] = ['label' => $day->translatedFormat('D'), 'date' => $day->format('j'), 'today' => $day->isToday(), 'count' => $weekBookings->filter(fn ($d) => $d->copy()->setTimezone($tz)->isSameDay($day))->count()];
        }

        $paid = fn ($from, $to) => (float) Order::query()->where('status', 'paid')->whereBetween('ordered_at', [$from->copy()->utc(), $to->copy()->utc()])->sum('amount');
        $now = now($tz);
        $revenueWeek = $paid($now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay());
        $revenuePrev = $paid($now->copy()->subDays(13)->startOfDay(), $now->copy()->subDays(7)->endOfDay());
        $leads = Customer::query()->where('created_at', '>=', now()->subDays(7))->count();
        $leadsPrev = Customer::query()->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        $plans = app(\App\Billing\Plans::class);

        $summary = app(\App\Services\DailySummary::class);

        // Everything that needs a person, most urgent first. Each line links straight to where it is fixed.
        $unmarked = $bookings->filter(fn ($b) => $b->status === 'confirmed' && $b->ends_at->lt(now()))->count();
        $badChannels = \App\Models\Channel::query()->where('active', true)->whereNotNull('last_error')->count();
        $attention = [];
        if ($waiting->count()) {
            $attention[] = ['tone' => 'red', 'icon' => '!', 'title' => ($waiting->count() === 1 ? __('1 chat is waiting for a person') : __(':n chats are waiting for a person', ['n' => $waiting->count()])), 'sub' => __('Customers asked for a person. The bot has stopped replying to them.'), 'url' => route('inbox'), 'cta' => __('Reply')];
        }
        if ($overdueCount) {
            $attention[] = ['tone' => 'red', 'icon' => '✓', 'title' => ($overdueCount === 1 ? __('1 task is overdue') : __(':n tasks are overdue', ['n' => $overdueCount])), 'sub' => __('Follow-ups that passed their date.'), 'url' => route('tasks', ['due' => 'overdue']), 'cta' => __('See tasks')];
        }
        if ($unmarked) {
            $attention[] = ['tone' => 'amber', 'icon' => '▦', 'title' => ($unmarked === 1 ? __('1 visit today is not marked yet') : __(':n visits today are not marked yet', ['n' => $unmarked])), 'sub' => __('Mark them done or no-show so your reports are right.'), 'url' => route('schedule'), 'cta' => __('Open schedule')];
        }
        if ($badChannels) {
            $attention[] = ['tone' => 'red', 'icon' => '⚠', 'title' => ($badChannels === 1 ? __('1 channel has a connection problem') : __(':n channels have a connection problem', ['n' => $badChannels])), 'sub' => __('Messages from it may not be arriving.'), 'url' => route('channels'), 'cta' => __('Fix it')];
        }
        if ($user->role === 'owner') {
            $state = $plans->state($business);
            $trialDays = $state === 'trial' && $business->trial_ends_at ? (int) ceil(now()->diffInHours($business->trial_ends_at, false) / 24) : null;
            if (in_array($state, ['trial_expired', 'past_due', 'canceled'], true)) {
                $attention[] = ['tone' => 'red', 'icon' => '$', 'title' => __('Your plan is not active'), 'sub' => __('The AI is paused until you choose a plan or update your payment.'), 'url' => route('billing'), 'cta' => __('Plan & billing')];
            } elseif ($trialDays !== null && $trialDays <= 3) {
                $attention[] = ['tone' => 'amber', 'icon' => '$', 'title' => $trialDays <= 0 ? __('Your free trial ends today') : ($trialDays === 1 ? __('Your free trial ends in 1 day') : __('Your free trial ends in :n days', ['n' => $trialDays])), 'sub' => __('Choose a plan to keep the AI replies on.'), 'url' => route('billing'), 'cta' => __('Plan & billing')];
            }
            $ai = $plans->limit($business, 'ai');
            if ($ai && $state !== 'trial_expired' && $plans->used($business, 'ai') >= $ai * 0.9) {
                $attention[] = ['tone' => 'amber', 'icon' => '◔', 'title' => __('AI replies are almost used up this month'), 'sub' => __(':a of :b used.', ['a' => number_format($plans->used($business, 'ai')), 'b' => number_format($ai)]), 'url' => route('billing'), 'cta' => __('Plan & billing')];
            }
        }

        return view('admin.today', [
            'summaries' => ['yesterday' => $summary->forDay($business, now($tz)->subDay()), 'today' => $summary->forDay($business, now($tz))],
            'business' => $business,
            'active' => 'today',
            'waiting' => $waiting,
            'bookings' => $bookings,
            'tasks' => $tasks,
            'overdue' => $overdue,
            'overdueCount' => $overdueCount,
            'attention' => $attention,
            'tz' => $tz,
            'week' => $week,
            'tomorrow' => Booking::query()->where('status', 'confirmed')->whereBetween('starts_at', [now($tz)->addDay()->startOfDay()->utc(), now($tz)->addDay()->endOfDay()->utc()])->count(),
            'newLeads' => $leads,
            'leadsDelta' => $leads - $leadsPrev,
            'revenueWeek' => $revenueWeek,
            'revenueDelta' => $revenuePrev > 0 ? (int) round(($revenueWeek - $revenuePrev) / $revenuePrev * 100) : null,
            'currency' => $business->settings['currency'] ?? 'RM',
            'openChats' => Conversation::query()->where('channel', '!=', 'preview')->where('status', 'open')->count(),
            'recent' => StaffNotification::query()->where('user_id', $user->id)->orderByDesc('id')->limit(5)->get(),
            'chatUrl' => route('chat.show', $business->slug),
            'aiUsage' => $user->role === 'owner' && $plans->limit($business, 'ai') !== null ? ['used' => $plans->used($business, 'ai'), 'limit' => $plans->limit($business, 'ai')] : null,
            'hour' => (int) now($tz)->format('G'),
            'checklist' => $user->role === 'owner' ? $this->checklist($business) : [],
        ]);
    }

    /** What a new owner still has to do. Empty once everything is done, so the card disappears. @return list<array{done: bool, text: string, url: string}> */
    private function checklist($business): array
    {
        $steps = [
            ['done' => \App\Models\Service::query()->exists(), 'text' => 'Add your services and prices', 'url' => route('booking.setup')],
            ['done' => ! empty($business->settings['hours']), 'text' => 'Set your opening hours', 'url' => route('booking.setup')],
            ['done' => \App\Models\Channel::query()->where('active', true)->exists(), 'text' => 'Connect WhatsApp, Messenger or Instagram (optional for the website chat)', 'url' => route('channels')],
            ['done' => $business->setup_done_at !== null || $business->plan === null, 'text' => 'Try your chat, then go live', 'url' => route('setup')],
        ];

        return collect($steps)->every(fn ($s) => $s['done']) ? [] : $steps;
    }
}
