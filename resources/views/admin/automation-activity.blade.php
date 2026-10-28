@extends('admin.layout')
@section('title', 'Automation activity')
@section('content')
    <p><a href="{{ route('automations') }}">&larr; Automations</a></p>
    <h1>Automation activity</h1>
    <p class="mute">Every automatic message, and every one that was skipped with the reason. Survey answers and review links are listed below.</p>
    <form method="get" class="bar">
        <select name="key" class="small"><option value="">All automations</option>
            @foreach ($defs as $k => $d)<option value="{{ $k }}" @selected($key === $k)>{{ $d['title'] }}</option>@endforeach</select>
        <select name="status" class="small"><option value="">Any result</option>
            @foreach (['sent', 'skipped', 'failed'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
        <button class="btn" type="submit">Filter</button>
    </form>
    <table class="table">
        <thead><tr><th>When</th><th>Automation</th><th>Customer</th><th>Result</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td>{{ $l->created_at?->copy()->setTimezone($business->timezone)->format('d M H:i') }}</td>
                <td>{{ $defs[$l->automation_key]['title'] ?? $l->automation_key }}</td>
                <td>@if ($l->customer)<a href="{{ route('customers.show', $l->customer_id) }}">{{ $l->customer->name ?: $l->customer->phone }}</a>@else - @endif</td>
                <td><span class="tag {{ $l->status === 'sent' ? 'ok' : ($l->status === 'failed' ? 'hot' : 'warn') }}">{{ $l->status }}</span></td>
                <td class="mute">{{ $l->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Nothing yet. Switch an automation on and press Run now.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $logs->links('pagination::simple-default') }}</div>

    <h2>Survey answers and review links</h2>
    <table class="table">
        <thead><tr><th>Sent</th><th>Customer</th><th>Rating</th><th>Review link</th></tr></thead>
        <tbody>
        @forelse ($surveys as $s)
            <tr>
                <td>{{ $s->sent_at?->copy()->setTimezone($business->timezone)->format('d M H:i') }}</td>
                <td>@if ($s->customer)<a href="{{ route('customers.show', $s->customer_id) }}">{{ $s->customer->name ?: $s->customer->phone }}</a>@endif</td>
                <td>@if ($s->rating)<span class="tag {{ $s->rating <= 2 ? 'hot' : 'ok' }}">{{ $s->rating }} / 5</span>@else<span class="mute">waiting</span>@endif</td>
                <td>{{ $s->review_sent_at ? 'sent '.$s->review_sent_at->diffForHumans() : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">No surveys sent yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
