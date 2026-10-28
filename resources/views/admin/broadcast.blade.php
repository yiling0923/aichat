@extends('admin.layout')
@section('title', $broadcast->name)
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'broadcasts'])
    @php
        $total = $broadcast->sent_count + $broadcast->skipped_count + $broadcast->failed_count;
        $pct = $total ? round($broadcast->sent_count / $total * 100) : 0;
        $recipients = $recipients->values();
    @endphp
    <p class="crumb"><a href="{{ route('broadcasts') }}">&larr; All broadcasts</a></p>
    <div class="page-head">
        <div><h1>{{ $broadcast->name }}</h1><p class="mute">@if ($broadcast->sent_at)Sent {{ $broadcast->sent_at->copy()->setTimezone($business->timezone)->format('j M Y, H:i') }}@else Draft, not sent yet @endif</p></div>
    </div>
    <div class="kpis main">
        <div class="kpi"><span class="kpi-l">Sent</span><b>{{ $broadcast->sent_count }}</b><span class="kpi-s">{{ $pct }}% of {{ $total }}</span></div>
        <div class="kpi"><span class="kpi-l">Skipped</span><b>{{ $broadcast->skipped_count }}</b><span class="kpi-s">for a reason, see below</span></div>
        <div class="kpi"><span class="kpi-l">Failed</span><b>{{ $broadcast->failed_count }}</b><span class="kpi-s">{{ $broadcast->failed_count ? 'could not be delivered' : 'none' }}</span></div>
    </div>

    <div class="cust-cols">
        <section class="card">
            <div class="bar spread"><h3>Who got it</h3>
                <div class="seg" id="rc-filter"><a href="#" data-s="" class="on">All ({{ $recipients->count() }})</a>@foreach (['sent' => 'Sent', 'skipped' => 'Skipped', 'failed' => 'Failed'] as $k => $l)<a href="#" data-s="{{ $k }}">{{ $l }} ({{ $recipients->where('status', $k)->count() }})</a>@endforeach</div>
            </div>
            <div class="table-wrap" style="max-height:none">
            <table class="table" id="rc-table">
                <thead><tr><th>Customer</th><th>Result</th><th>Why</th></tr></thead>
                <tbody>
                @forelse ($recipients as $r)
                    <tr data-s="{{ $r->status }}">
                        <td>@if ($r->customer)<a href="{{ route('customers.show', $r->customer_id) }}">{{ $r->customer->name ?: $r->customer->phone }}</a>@else <span class="mute">Deleted customer</span> @endif</td>
                        <td><span class="tag {{ $r->status === 'sent' ? 'ok' : ($r->status === 'failed' ? 'hot' : 'warn') }}">{{ ucfirst($r->status) }}</span></td>
                        <td class="mute">{{ $r->reason ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No recipients.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>
        <aside>
            <div class="card">
                <h3>Message</h3>
                <div class="wa-bubble" style="margin-top:8px">{{ $broadcast->message['en'] ?? '' }}</div>
                @if (! empty($broadcast->message['zh']))<div class="wa-bubble" style="margin-top:8px">{{ $broadcast->message['zh'] }}</div>@endif
                @if ($broadcast->template_name)<p class="mute small-note" style="padding:0;margin-top:8px">WhatsApp template: <code>{{ $broadcast->template_name }}</code></p>@endif
            </div>
        </aside>
    </div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('#rc-filter a').forEach(function (a) {
  a.addEventListener('click', function (e) {
    e.preventDefault();
    document.querySelectorAll('#rc-filter a').forEach(function (x) { x.classList.toggle('on', x === a); });
    document.querySelectorAll('#rc-table tr[data-s]').forEach(function (r) { r.hidden = a.dataset.s !== '' && r.dataset.s !== a.dataset.s; });
  });
});
</script>
@endpush
