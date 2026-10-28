@extends('admin.layout')
@section('title', 'Automation activity')
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'activity'])
    @php
        $tz = $business->timezone;
        $msgChips = array_filter([$key !== '' ? ['Automation: '.($defs[$key]['title'] ?? $key), 'key'] : null, $status !== '' ? ['Result: '.ucfirst($status), 'status'] : null]);
        $ratingLabels = ['waiting' => 'Waiting for an answer', 'low' => 'Low (1 or 2)', '5' => '5 stars', '4' => '4 stars', '3' => '3 stars', '2' => '2 stars', '1' => '1 star'];
        $stars = fn ($n) => str_repeat('★', $n).str_repeat('☆', 5 - $n);
        $st = $surveyStats;
    @endphp
    <div class="page-head">
        <div><h1>Activity log</h1><p class="mute">{{ $tab === 'surveys' ? 'Ratings customers gave after their visit, and who was asked for a review.' : 'Every automatic message, and every one that was skipped, with the reason.' }}</p></div>
        <div class="seg">
            <a href="{{ route('automations.activity') }}" @class(['on' => $tab === 'messages'])>Messages sent</a>
            <a href="{{ route('automations.activity', ['tab' => 'surveys']) }}" @class(['on' => $tab === 'surveys'])>Survey answers ({{ $st['answered'] }})</a>
        </div>
    </div>

    @if ($tab === 'messages')
        <form method="get" class="filters">
            <label class="grow">Automation
                <select name="key" onchange="this.form.submit()"><option value="">All automations</option>
                    @foreach ($defs as $k => $d)<option value="{{ $k }}" @selected($key === $k)>{{ $d['title'] }}</option>@endforeach</select></label>
            <label>Result
                <select name="status" onchange="this.form.submit()"><option value="">Any result</option>
                    @foreach (['sent', 'skipped', 'failed'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></label>
            @if ($msgChips)<a class="btn" href="{{ route('automations.activity') }}">Reset</a>@endif
        </form>
        @if ($msgChips)<div class="result-line">@foreach ($msgChips as [$text, $param])<span class="fchip">{{ $text }} <a href="{{ request()->fullUrlWithQuery([$param => null, 'page' => null]) }}" aria-label="Remove filter">&times;</a></span>@endforeach</div>@endif
        <div class="table-wrap" style="max-height:none">
        <table class="table">
            <thead><tr><th>When</th><th>Automation</th><th>Customer</th><th>Result</th><th>Reason</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td>{{ $l->created_at?->copy()->setTimezone($tz)->format('d M H:i') }}</td>
                    <td>{{ $defs[$l->automation_key]['title'] ?? $l->automation_key }}</td>
                    <td>@if ($l->customer)<a href="{{ route('customers.show', $l->customer_id) }}">{{ $l->customer->name ?: $l->customer->phone }}</a>@else - @endif</td>
                    <td><span class="tag {{ $l->status === 'sent' ? 'ok' : ($l->status === 'failed' ? 'hot' : 'warn') }}">{{ ucfirst($l->status) }}</span></td>
                    <td class="mute">{{ $l->reason ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ $msgChips ? 'Nothing matches these filters.' : 'Nothing yet. Switch an automation on and press Run now.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="pager">{{ $logs->links('pagination.bar') }}</div>
    @else
        <div class="kpis main">
            <div class="kpi"><span class="kpi-l">Average rating</span><b>{{ $st['average'] === null ? '-' : number_format($st['average'], 1).' / 5' }}</b><span class="kpi-s">{{ $st['answered'] }} {{ $st['answered'] === 1 ? 'answer' : 'answers' }}</span></div>
            <div class="kpi"><span class="kpi-l">Answered</span><b>{{ $st['sent'] ? round($st['answered'] / $st['sent'] * 100) : 0 }}%</b><span class="kpi-s">{{ $st['answered'] }} of {{ $st['sent'] }} asked</span></div>
            <div class="kpi"><span class="kpi-l">Low ratings (1 or 2)</span><b>{{ $st['low'] }}</b><span class="kpi-s">{{ $st['low'] ? 'each one made a follow-up task' : 'none, good' }}</span></div>
            <div class="kpi"><span class="kpi-l">Review links sent</span><b>{{ $st['reviews'] }}</b><span class="kpi-s">after a good rating</span></div>
        </div>
        <form method="get" class="filters">
            <input type="hidden" name="tab" value="surveys">
            <label class="grow">Show
                <select name="rating" onchange="this.form.submit()"><option value="">All surveys</option>
                    @foreach ($ratingLabels as $k => $l)<option value="{{ $k }}" @selected($rating === (string) $k)>{{ $l }}</option>@endforeach</select></label>
            @if ($rating !== '')<a class="btn" href="{{ route('automations.activity', ['tab' => 'surveys']) }}">Reset</a>@endif
        </form>
        <div class="table-wrap" style="max-height:none">
        <table class="table">
            <thead><tr><th>Sent</th><th>Customer</th><th>Visit</th><th>Rating</th><th>Review link</th></tr></thead>
            <tbody>
            @forelse ($surveys as $s)
                <tr>
                    <td>{{ $s->sent_at?->copy()->setTimezone($tz)->format('d M H:i') }}</td>
                    <td>@if ($s->customer)<a href="{{ route('customers.show', $s->customer_id) }}">{{ $s->customer->name ?: $s->customer->phone }}</a>@else - @endif</td>
                    <td class="mute">{{ $s->booking?->service?->label('en') ?: 'Test message' }}</td>
                    <td>@if ($s->rating)<span class="tag {{ $s->rating <= 2 ? 'hot' : ($s->rating === 3 ? 'warn' : 'ok') }}" title="{{ $s->rating }} / 5">{{ $stars($s->rating) }}</span>@if ($s->comment)<br><small class="mute">{{ \Illuminate\Support\Str::limit($s->comment, 60) }}</small>@endif @else<span class="mute">Waiting</span>@endif</td>
                    <td>@if ($s->review_sent_at)<span class="tag ok">Sent {{ $s->review_sent_at->diffForHumans() }}</span>@else<span class="mute">-</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ $rating !== '' ? 'No surveys match this filter.' : 'No surveys sent yet. Switch on the Satisfaction survey, or use "Send a test" on its card.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="pager">{{ $surveys->links('pagination.bar') }}</div>
    @endif
@endsection
