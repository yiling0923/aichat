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
        $show = $request->query('show', 'open');
        $mine = $request->boolean('mine');
        $business = $request->user()->business;

        $tasks = Task::query()->with(['assignee', 'customer'])
            ->when($show === 'done', fn ($q) => $q->whereNotNull('done_at')->orderByDesc('done_at'), fn ($q) => $q->whereNull('done_at')->orderByRaw('due_at is null')->orderBy('due_at'))
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->limit(200)->get();

        return view('admin.tasks', [
            'business' => $business, 'active' => 'tasks', 'tasks' => $tasks, 'show' => $show, 'mine' => $mine,
            'team' => User::query()->where('business_id', $business->id)->where('active', true)->orderBy('name')->get(),
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

        $task = Task::create([
            'title' => $data['title'],
            'due_at' => ! empty($data['due_at']) ? \Carbon\Carbon::parse($data['due_at'], $business->timezone ?: 'UTC')->utc() : null,
            'assigned_to' => $this->assignee($business->id, $data['assigned_to'] ?? null),
            'created_by' => $request->user()->id,
            'conversation_id' => $conversation?->id,
            'customer_id' => $customerId ? Customer::query()->findOrFail($customerId)->id : null,
        ]);

        return $request->expectsJson() ? response()->json(['task' => $task->id]) : back()->with('status', 'Task added.');
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
