@extends('admin.layout')
@section('title', __('Automation activity'))
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'activity'])
    @php
        $tz = $business->timezone;
        $msgChips = array_filter([$key !== '' ? [__('Automation:').' '.__($defs[$key]['title'] ?? $key), 'key'] : null, $status !== '' ? [__('Result:').' '.__(ucfirst($status)), 'status'] : null]);
        $ratingLabels = ['waiting' => __('Waiting for an answer'), 'low' => __('Low (1 or 2)'), '5' => __('5 stars'), '4' => __('4 stars'), '3' => __('3 stars'), '2' => __('2 stars'), '1' => __('1 star')];
        $stars = fn ($n) => str_repeat('★', $n).str_repeat('☆', 5 - $n);
        $st = $surveyStats;
    @endphp
    <div class="page-head">
        <div><h1>{{ __('Activity log') }}</h1><p class="mute">{{ $tab === 'surveys' ? __('Ratings customers gave after their visit, and who was asked for a review.') : __('Every automatic message, and every one that was skipped, with the reason.') }}</p></div>
        <div class="seg">
            <a href="{{ route('automations.activity') }}" @class(['on' => $tab === 'messages'])>{{ __('Messages sent') }}</a>
            <a href="{{ route('automations.activity', ['tab' => 'surveys']) }}" @class(['on' => $tab === 'surveys'])>{{ __('Survey answers') }} ({{ $st['answered'] }})</a>
        </div>
    </div>

    @if ($tab === 'messages')
        <form method="get" class="fbar">
            <select name="key" aria-label="{{ __('Automation') }}"><option value="">{{ __('All automations') }}</option>
                @foreach ($defs as $k => $d)<option value="{{ $k }}" @selected($key === $k)>{{ __($d['title']) }}</option>@endforeach</select>
            <select name="status" aria-label="{{ __('Result') }}"><option value="">{{ __('Any result') }}</option>
                @foreach (['sent', 'skipped', 'failed'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select>
            @if ($msgChips)<a class="btn" href="{{ route('automations.activity') }}">{{ __('Reset') }}</a>@endif
        </form>
        @if ($msgChips)<div class="result-line">@foreach ($msgChips as [$text, $param])<span class="fchip">{{ $text }} <a href="{{ request()->fullUrlWithQuery([$param => null, 'page' => null]) }}" aria-label="{{ __('Remove filter') }}">&times;</a></span>@endforeach</div>@endif
        <div class="table-wrap" style="max-height:none">
        <table class="table">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Automation') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Result') }}</th><th>{{ __('Reason') }}</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td>{{ \App\Support\Lang::fmt($l->created_at?->copy()->setTimezone($tz), 'd M H:i', 'n月j日 H:i') }}</td>
                    <td>{{ __($defs[$l->automation_key]['title'] ?? $l->automation_key) }}</td>
                    <td>@if ($l->customer)<a href="{{ route('customers.show', $l->customer_id) }}">{{ $l->customer->name ?: $l->customer->phone }}</a>@else - @endif</td>
                    <td><span class="tag {{ $l->status === 'sent' ? 'ok' : ($l->status === 'failed' ? 'hot' : 'warn') }}">{{ __(ucfirst($l->status)) }}</span></td>
                    <td class="mute">{{ $l->reason ? __($l->reason) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ $msgChips ? __('Nothing matches these filters.') : __('Nothing yet. Switch an automation on and press Run now.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="pager">{{ $logs->links('pagination.bar') }}</div>
    @else
        <div class="kpis main">
            <div class="kpi"><span class="kpi-l">{{ __('Average rating') }}</span><b>{{ $st['average'] === null ? '-' : number_format($st['average'], 1).' / 5' }}</b><span class="kpi-s">{{ $st['answered'] === 1 ? __('1 answer') : __(':n answers', ['n' => $st['answered']]) }}</span></div>
            <div class="kpi"><span class="kpi-l">{{ __('Answered') }}</span><b>{{ $st['sent'] ? round($st['answered'] / $st['sent'] * 100) : 0 }}%</b><span class="kpi-s">{{ __(':a of :b asked', ['a' => $st['answered'], 'b' => $st['sent']]) }}</span></div>
            <div class="kpi"><span class="kpi-l">{{ __('Low ratings (1 or 2)') }}</span><b>{{ $st['low'] }}</b><span class="kpi-s">{{ $st['low'] ? __('each one made a follow-up task') : __('none, good') }}</span></div>
            <div class="kpi"><span class="kpi-l">{{ __('Review links sent') }}</span><b>{{ $st['reviews'] }}</b><span class="kpi-s">{{ __('after a good rating') }}</span></div>
        </div>
        <form method="get" class="fbar">
            <input type="hidden" name="tab" value="surveys">
            <select name="rating" aria-label="{{ __('Show') }}"><option value="">{{ __('All surveys') }}</option>
                @foreach ($ratingLabels as $k => $l)<option value="{{ $k }}" @selected($rating === (string) $k)>{{ $l }}</option>@endforeach</select>
            @if ($rating !== '')<a class="btn" href="{{ route('automations.activity', ['tab' => 'surveys']) }}">{{ __('Reset') }}</a>@endif
        </form>
        <div class="table-wrap" style="max-height:none">
        <table class="table">
            <thead><tr><th>{{ __('Sent') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Visit') }}</th><th>{{ __('Rating') }}</th><th>{{ __('Review link') }}</th></tr></thead>
            <tbody>
            @forelse ($surveys as $s)
                <tr>
                    <td>{{ \App\Support\Lang::fmt($s->sent_at?->copy()->setTimezone($tz), 'd M H:i', 'n月j日 H:i') }}</td>
                    <td>@if ($s->customer)<a href="{{ route('customers.show', $s->customer_id) }}">{{ $s->customer->name ?: $s->customer->phone }}</a>@else - @endif</td>
                    <td class="mute">{{ $s->booking?->service?->label(app()->getLocale() === 'zh' ? 'zh' : 'en') ?: __('Test message') }}</td>
                    <td>@if ($s->rating)<span class="tag {{ $s->rating <= 2 ? 'hot' : ($s->rating === 3 ? 'warn' : 'ok') }}" title="{{ $s->rating }} / 5">{{ $stars($s->rating) }}</span>@if ($s->comment)<br><small class="mute">{{ \Illuminate\Support\Str::limit($s->comment, 60) }}</small>@endif @else<span class="mute">{{ __('Waiting') }}</span>@endif</td>
                    <td>@if ($s->review_sent_at)<span class="tag ok">{{ __('Sent :when', ['when' => $s->review_sent_at->diffForHumans()]) }}</span>@else<span class="mute">-</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ $rating !== '' ? __('No surveys match this filter.') : __('No surveys sent yet. Switch on the Satisfaction survey, or use "Send a test" on its card.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="pager">{{ $surveys->links('pagination.bar') }}</div>
    @endif
@endsection
