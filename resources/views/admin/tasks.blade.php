@extends('admin.layout')
@section('title', 'Tasks')
@section('content')
    <h1>Tasks</h1>
    <p class="mute">Follow-ups for the team. You can also add a task from a chat in the Inbox.</p>
    <form method="post" action="{{ url('/admin/tasks') }}" class="card row">
        @csrf
        <label class="f"><span>New task</span><input type="text" name="title" placeholder="Call Amy about her facial" required></label>
        <label class="f"><span>Due</span><input type="datetime-local" name="due_at"></label>
        <label class="f"><span>Assign to</span>
            <select name="assigned_to"><option value="">Anyone</option>@foreach ($team as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
        <button class="btn primary" type="submit">Add task</button>
    </form>
    <div class="bar">
        <a class="btn {{ $show === 'open' ? 'primary' : '' }}" href="{{ route('tasks', ['mine' => $mine ? 1 : 0]) }}">Open</a>
        <a class="btn {{ $show === 'done' ? 'primary' : '' }}" href="{{ route('tasks', ['show' => 'done', 'mine' => $mine ? 1 : 0]) }}">Done</a>
        <a class="btn {{ $mine ? 'primary' : '' }}" href="{{ route('tasks', ['show' => $show, 'mine' => $mine ? 0 : 1]) }}">Only mine</a>
    </div>
    <table class="table">
        <thead><tr><th></th><th>Task</th><th>Customer</th><th>Assigned</th><th>Due</th><th></th></tr></thead>
        <tbody>
        @forelse ($tasks as $t)
            <tr>
                <td><form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}" class="inline">@csrf<button class="btn small">{{ $t->done_at ? 'Reopen' : 'Done' }}</button></form></td>
                <td>{{ $t->title }}</td>
                <td>@if ($t->customer)<a href="{{ route('customers.show', $t->customer_id) }}">{{ $t->customer->name ?: $t->customer->phone }}</a>@endif
                    @if ($t->conversation_id) <a href="{{ route('inbox', ['c' => $t->conversation_id]) }}">chat</a>@endif</td>
                <td>{{ $t->assignee?->name ?: 'Anyone' }}</td>
                <td>{{ $t->due_at ? $t->due_at->copy()->setTimezone($business->timezone)->format('d M Y H:i') : '-' }}
                    @if (! $t->done_at && $t->due_at && $t->due_at->isPast())<span class="tag warn">overdue</span>@endif</td>
                <td><form method="post" action="{{ url('/admin/tasks/'.$t->id.'/delete') }}" class="inline" onsubmit="return confirm('Delete this task?')">@csrf<button class="btn small danger">Delete</button></form></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No tasks here.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
