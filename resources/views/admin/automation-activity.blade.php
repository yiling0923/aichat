@extends('admin.layout')
@section('title', 'Automation activity')
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'activity'])
    @php $chips = array_filter([$key !== '' ? ['Automation: '.($defs[$key]['title'] ?? $key), 'key'] : null, $status !== '' ? ['Result: '.ucfirst($status), 'status'] : null]); @endphp
    <div class="page-head">
        <div><h1>Activity log</h1><p class="mute">Every automatic message, and every one that was skipped, with the reason.</p></div>
    </div>
    <form method="get" class="filters">
        <label class="grow">Automation
            <select name="key" onchange="this.form.submit()"><option value="">All automations</option>
                @foreach ($defs as $k => $d)<option value="{{ $k }}" @selected($key === $k)>{{ $d['title'] }}</option>@endforeach</select></label>
        <label>Result
            <select name="status" onchange="this.form.submit()"><option value="">Any result</option>
                @foreach (['sent', 'skipped', 'failed'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></label>
        @if ($chips)<a class="btn" href="{{ route('automations.activity') }}">Reset</a>@endif
    </form>
    @if ($chips)<div class="result-line">@foreach ($chips as [$text, $param])<span class="fchip">{{ $text }} <a href="{{ request()->fullUrlWithQuery([$param => null, 'page' => null]) }}" aria-label="Remove filter">&times;</a></span>@endforeach</div>@endif
    <div class="table-wrap" style="max-height:none">
    <table class="table">
        <thead><tr><th>When</th><th>Automation</th><th>Customer</th><th>Result</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td>{{ $l->created_at?->copy()->setTimezone($business->timezone)->format('d M H:i') }}</td>
                <td>{{ $defs[$l->automation_key]['title'] ?? $l->automation_key }}</td>
                <td>@if ($l->customer)<a href="{{ route('customers.show', $l->customer_id) }}">{{ $l->customer->name ?: $l->customer->phone }}</a>@else - @endif</td>
                <td><span class="tag {{ $l->status === 'sent' ? 'ok' : ($l->status === 'failed' ? 'hot' : 'warn') }}">{{ ucfirst($l->status) }}</span></td>
                <td class="mute">{{ $l->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">{{ $chips ? 'Nothing matches these filters.' : 'Nothing yet. Switch an automation on and press Run now.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pager">{{ $logs->links('pagination.bar') }}</div>

    <h3 class="bill-h">Survey answers and review links</h3>
    <div class="table-wrap" style="max-height:none">
    <table class="table">
        <thead><tr><th>Sent</th><th>Customer</th><th>Rating</th><th>Review link</th></tr></thead>
        <tbody>
        @forelse ($surveys as $s)
            <tr>
                <td>{{ $s->sent_at?->copy()->setTimezone($business->timezone)->format('d M H:i') }}</td>
                <td>@if ($s->customer)<a href="{{ route('customers.show', $s->customer_id) }}">{{ $s->customer->name ?: $s->customer->phone }}</a>@endif</td>
                <td>@if ($s->rating)<span class="tag {{ $s->rating <= 2 ? 'hot' : 'ok' }}">{{ $s->rating }} / 5</span>@else<span class="mute">waiting</span>@endif</td>
                <td>{{ $s->review_sent_at ? 'Sent '.$s->review_sent_at->diffForHumans() : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">No surveys sent yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
@endsection
