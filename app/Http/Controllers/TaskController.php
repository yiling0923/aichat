<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Follow-ups for the team: call back, send a quote, check payment. Can start from a chat or a customer.
 */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $show = $request->query('show', 'open') === 'done' ? 'done' : 'open';
        $business = $request->user()->business;
        $tz = $business->timezone ?: 'UTC';
        $me = $request->user()->id;
        $team = User::query()->where('business_id', $business->id)->where('active', true)->orderBy('name')->get();

        // Who: everyone, only me, or one person ("mine=1" from older links still works).
        $who = (string) $request->query('who', $request->boolean('mine') ? 'me' : 'all');
        $who = $who === 'all' || $who === 'me' || $team->contains('id', (int) $who) ? $who : 'all';
        $mine = $who === 'me';
        $assignee = $who === 'me' ? $me : ($who === 'all' ? null : (int) $who);
        $dueFilter = in_array($request->query('due'), ['overdue', 'today', 'week', 'none'], true) ? (string) $request->query('due') : '';
        $q = trim((string) $request->query('q', ''));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $endToday = \Carbon\Carbon::now($tz)->endOfDay()->utc();

        $scoped = fn () => Task::query()
            ->when($assignee !== null, fn ($w) => $w->where('assigned_to', $assignee))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like))))
            ->when($dueFilter === 'overdue', fn ($w) => $w->where('due_at', '<', now()))
            ->when($dueFilter === 'today', fn ($w) => $w->whereBetween('due_at', [\Carbon\Carbon::now($tz)->startOfDay()->utc(), $endToday]))
            ->when($dueFilter === 'week', fn ($w) => $w->whereBetween('due_at', [now(), \Carbon\Carbon::now($tz)->addDays(7)->endOfDay()->utc()]))
            ->when($dueFilter === 'none', fn ($w) => $w->whereNull('due_at'));

        $tasks = $scoped()->with(['assignee', 'customer'])
            ->when($show === 'done', fn ($q) => $q->whereNotNull('done_at')->orderByDesc('done_at'), fn ($q) => $q->whereNull('done_at')->orderByRaw('due_at is null')->orderBy('due_at'))
            ->limit(200)->get();

        // Open tasks grouped by when they are due.
        $now = \Carbon\Carbon::now($tz);
        $groups = [];
        if ($show === 'open') {
            foreach ($tasks as $t) {
                $due = $t->due_at?->copy()->setTimezone($tz);
                $key = match (true) {
                    $due === null => 'No date',
                    $due->lt($now) => 'Overdue',
                    $due->isSameDay($now) => 'Today',
                    $due->isSameDay($now->copy()->addDay()) => 'Tomorrow',
                    $due->lt($now->copy()->addDays(7)->endOfDay()) => 'This week',
                    default => 'Later',
                };
                $groups[$key][] = $t;
            }
            $groups = array_replace(array_flip(array_intersect(['Overdue', 'Today', 'Tomorrow', 'This week', 'Later', 'No date'], array_keys($groups))), $groups);
        }

        return view('admin.tasks', [
            'business' => $business, 'active' => 'tasks', 'tasks' => $tasks, 'show' => $show, 'mine' => $mine, 'who' => $who, 'due' => $dueFilter, 'q' => $q, 'groups' => $groups,
            'counts' => ['open' => $scoped()->whereNull('done_at')->count(), 'done' => $scoped()->whereNotNull('done_at')->count(),
                'overdue' => $scoped()->whereNull('done_at')->where('due_at', '<', now())->count()],
            'team' => $team,
            'tz' => $tz,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $business = $request->user()->business;
        $conversation = ! empty($data['conversation_id']) ? Conversation::query()->findOrFail($data['conversation_id']) : null;
        $customerId = $data['customer_id'] ?? $conversation?->customer_id;

        $assignee = $this->assignee($business->id, $data['assigned_to'] ?? null);
        $task = Task::create([
            'title' => $data['title'],
            'due_at' => ! empty($data['due_at']) ? \Carbon\Carbon::parse($data['due_at'], $business->timezone ?: 'UTC')->utc() : null,
            'assigned_to' => $assignee,
            'created_by' => $request->user()->id,
            'conversation_id' => $conversation?->id,
            'customer_id' => $customerId ? Customer::query()->findOrFail($customerId)->id : null,
        ]);

        if ($assignee !== null && $assignee !== $request->user()->id) {
            app(\App\Services\Notifier::class)->notify($business, 'task', 'New task for you: '.$task->title, $request->user()->name.' assigned it to you.', route('tasks'), null, $assignee);
        }

        return $request->expectsJson() ? response()->json(['task' => $task->id]) : back()->with('status', 'Task added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'due_at' => ['nullable', 'date'], 'assigned_to' => ['nullable', 'integer']]);
        $business = $request->user()->business;
        $task = Task::query()->findOrFail($id);
        $assignee = $this->assignee($business->id, $data['assigned_to'] ?? null);

        $task->update([
            'title' => $data['title'],
            'due_at' => ! empty($data['due_at']) ? \Carbon\Carbon::parse($data['due_at'], $business->timezone ?: 'UTC')->utc() : null,
            'assigned_to' => $assignee,
        ]);

        if ($task->wasChanged('assigned_to') && $assignee !== null && $assignee !== $request->user()->id) {
            app(\App\Services\Notifier::class)->notify($business, 'task', 'New task for you: '.$task->title, $request->user()->name.' assigned it to you.', route('tasks'), null, $assignee);
        }

        return back()->with('status', 'Task saved.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $task = Task::query()->findOrFail($id);
        $task->update(['done_at' => $task->done_at ? null : now()]);

        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        Task::query()->findOrFail($id)->delete();

        return back()->with('status', 'Task deleted.');
    }

    /** Only people of this business can be assigned. */
    private function assignee(int $businessId, mixed $userId): ?int
    {
        return $userId ? User::query()->where('business_id', $businessId)->where('active', true)->whereKey($userId)->value('id') : null;
    }
}
