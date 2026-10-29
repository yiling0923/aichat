@extends('admin.layout')
@section('title', $broadcast->name)
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'broadcasts'])
    @php
        $total = $broadcast->sent_count + $broadcast->skipped_count + $broadcast->failed_count;
        $pct = $total ? round($broadcast->sent_count / $total * 100) : 0;
        $recipients = $recipients->values();
    @endphp
    <p class="crumb"><a href="{{ route('broadcasts') }}">{{ __('← All broadcasts') }}</a></p>
    <div class="page-head">
        <div><h1>{{ $broadcast->name }}</h1><p class="mute">@if ($broadcast->sent_at){{ __('Sent :when', ['when' => \App\Support\Lang::fmt($broadcast->sent_at->copy()->setTimezone($business->timezone), 'j M Y, H:i', 'Y年n月j日 H:i')]) }}@else {{ __('Draft, not sent yet') }} @endif</p></div>
    </div>
    <div class="kpis main">
        <div class="kpi"><span class="kpi-l">{{ __('Sent') }}</span><b>{{ $broadcast->sent_count }}</b><span class="kpi-s">{{ __(':pct% of :total', ['pct' => $pct, 'total' => $total]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Skipped') }}</span><b>{{ $broadcast->skipped_count }}</b><span class="kpi-s">{{ __('for a reason, see below') }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Failed') }}</span><b>{{ $broadcast->failed_count }}</b><span class="kpi-s">{{ $broadcast->failed_count ? __('could not be delivered') : __('none') }}</span></div>
    </div>

    <div class="cust-cols">
        <section class="card">
            <div class="bar spread"><h3>{{ __('Who got it') }}</h3>
                <div class="seg" id="rc-filter"><a href="#" data-s="" class="on">{{ __('All') }} ({{ $recipients->count() }})</a>@foreach (['sent' => __('Sent'), 'skipped' => __('Skipped'), 'failed' => __('Failed')] as $k => $l)<a href="#" data-s="{{ $k }}">{{ $l }} ({{ $recipients->where('status', $k)->count() }})</a>@endforeach</div>
            </div>
            <div class="table-wrap" style="max-height:none">
            <table class="table" id="rc-table">
                <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Result') }}</th><th>{{ __('Why') }}</th></tr></thead>
                <tbody>
                @forelse ($recipients as $r)
                    <tr data-s="{{ $r->status }}">
                        <td>@if ($r->customer)<a href="{{ route('customers.show', $r->customer_id) }}">{{ $r->customer->name ?: $r->customer->phone }}</a>@else <span class="mute">{{ __('Deleted customer') }}</span> @endif</td>
                        <td><span class="tag {{ $r->status === 'sent' ? 'ok' : ($r->status === 'failed' ? 'hot' : 'warn') }}">{{ __(ucfirst($r->status)) }}</span></td>
                        <td class="mute">{{ $r->reason ? __($r->reason) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">{{ __('No recipients.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>
        <aside>
            <div class="card">
                <h3>{{ __('Message') }}</h3>
                <div class="wa-bubble" style="margin-top:8px">{{ $broadcast->message['en'] ?? '' }}</div>
                @if (! empty($broadcast->message['zh']))<div class="wa-bubble" style="margin-top:8px">{{ $broadcast->message['zh'] }}</div>@endif
                @if ($broadcast->template_name)<p class="mute small-note" style="padding:0;margin-top:8px">{{ __('WhatsApp template:') }} <code>{{ $broadcast->template_name }}</code></p>@endif
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
