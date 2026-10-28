@extends('admin.layout')
@section('title', 'Reports')
@section('content')
    @php
        $kpi = $s['kpi'];
        $money = fn ($v) => number_format($v, 2);
        $tile = function (string $label, string $value, ?int $delta = null, ?string $hint = null) {
            return compact('label', 'value', 'delta', 'hint');
        };
    @endphp
    <div class="page-head">
        <div><h1>Reports</h1><p class="mute">{{ $r->label }}, {{ $r->from->format('j M') }} to {{ $r->to->format('j M Y') }}@if ($r->filtered()) · filtered @endif</p></div>
        <details class="menu">
            <summary class="btn">Export CSV</summary>
            <div class="menu-body">
                @foreach ($exports as $key => $name)
                    <a href="{{ route('reports.export', ['section' => $key] + $query) }}">{{ $name }}</a>
                @endforeach
                <small class="mute">Uses the period and filters you chose.</small>
            </div>
        </details>
    </div>

    <form method="get" action="{{ route('reports') }}" class="filters" id="filters">
        <label>Period
            <select name="period" id="period">@foreach ($periods as $k => $name)<option value="{{ $k }}" @selected($r->period === (string) $k)>{{ $name }}</option>@endforeach</select></label>
        <span id="custom-dates" @if ($r->period !== 'custom') hidden @endif>
            <label>From <input type="date" name="from" value="{{ request('from', $r->from->format('Y-m-d')) }}"></label>
            <label>To <input type="date" name="to" value="{{ request('to', $r->to->format('Y-m-d')) }}"></label>
        </span>
        <label>Channel
            <select name="channel"><option value="">All channels</option>@foreach ($channels as $k => $name)<option value="{{ $k }}" @selected($r->channel === $k)>{{ $name }}</option>@endforeach</select></label>
        <label>Service
            <select name="service"><option value="">All services</option>@foreach ($services as $svc)<option value="{{ $svc->id }}" @selected($r->serviceId === $svc->id)>{{ $svc->label('en') }}</option>@endforeach</select></label>
        <label>Practitioner
            <select name="practitioner"><option value="">Everyone</option>@foreach ($practitioners as $p)<option value="{{ $p->id }}" @selected($r->practitionerId === $p->id)>{{ $p->name }}</option>@endforeach</select></label>
        <button class="btn primary" type="submit">Apply</button>
        @if ($r->filtered() || $r->period !== '30')<a class="btn" href="{{ route('reports') }}">Reset</a>@endif
    </form>
    @if ($r->channel || $r->serviceId || $r->practitionerId)
        <p class="mute" style="font-size:12px;margin:-4px 0 12px">Channel applies to chats and bookings. Service and practitioner apply to bookings only. Customers, orders and surveys are always for the whole business.</p>
    @endif

    <div class="kpis main">
        @foreach ([
            ['Chats started', $kpi['chats']['value'], $kpi['chats']['delta']],
            ['New customers', $kpi['customers']['value'], $kpi['customers']['delta']],
            ['Bookings made', $kpi['bookings']['value'], $kpi['bookings']['delta']],
            ['Paid orders', $money($kpi['revenue']['value']), $kpi['revenue']['delta']],
        ] as [$label, $value, $delta])
            <div class="kpi">
                <span class="kpi-l">{{ $label }}</span>
                <b>{{ $value }}</b>
                <span class="kpi-s">@if ($delta !== null)<span class="delta">{{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}%</span> vs previous period @else &nbsp; @endif</span>
            </div>
        @endforeach
    </div>

    <div class="strip">
        <span><b>{{ $kpi['handovers']['value'] }}</b> handed over to staff @if ($s['avgWait'] !== null)<small>(avg wait {{ $s['avgWait'] }} min)</small>@endif</span>
        <span><b>{{ $s['surveyAvg'] === null ? '-' : number_format($s['surveyAvg'], 1).' / 5' }}</b> survey score <small>({{ $s['surveyCount'] }} answers)</small></span>
        <span><b>{{ $s['noShowRate'] === null ? '-' : $s['noShowRate'].'%' }}</b> no-show rate</span>
        <span><b>{{ $s['cancelRate'] === null ? '-' : $s['cancelRate'].'%' }}</b> cancelled</span>
        <span><b>{{ $s['repeatCustomers'] }}</b> repeat customers</span>
        <span><b>{{ $s['optedOut'] }}</b> opted out</span>
        <span><b>{{ $s['aiCalls'] }}</b> AI calls</span>
    </div>

    <div class="tabs" id="report-tabs">
        <a href="#overview" data-tab="overview" class="on">Overview</a>
        <a href="#bookings" data-tab="bookings">Bookings</a>
        <a href="#people" data-tab="people">People</a>
        <a href="#money" data-tab="money">Money</a>
    </div>

    <section class="rtab" id="tab-overview">
        <div class="masonry">
            <div class="card"><h3>Chats started per day</h3><div class="chart">@include('admin._trend', ['days' => $s['daily'], 'key' => 'chats', 'title' => 'chats'])</div></div>
            <div class="card"><h3>Bookings made per day</h3><div class="chart">@include('admin._trend', ['days' => $s['daily'], 'key' => 'bookings', 'title' => 'bookings'])</div></div>
            <div class="card"><h3>Top services</h3>@include('admin._bars', ['rows' => $s['topServices']])</div>
            <div class="card"><h3>Chats by channel</h3>@include('admin._bars', ['rows' => $s['chatsByChannel']])</div>
        </div>
        <details class="numbers"><summary>Show the daily numbers as a table</summary>
            <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Chats</th><th>New customers</th><th>Bookings</th></tr></thead><tbody>
                @foreach ($s['daily'] as $d)<tr><td>{{ \Carbon\Carbon::parse($d['date'])->format('D j M Y') }}</td><td>{{ $d['chats'] }}</td><td>{{ $d['customers'] }}</td><td>{{ $d['bookings'] }}</td></tr>@endforeach
            </tbody></table></div>
        </details>
    </section>

    <section class="rtab" id="tab-bookings" hidden>
        <div class="masonry">
            <div class="card"><h3>Top services</h3>@include('admin._bars', ['rows' => $s['topServices']])</div>
            <div class="card"><h3>By practitioner</h3>@include('admin._bars', ['rows' => $s['byPractitioner']])</div>
            <div class="card"><h3>Busiest weekdays</h3>@include('admin._bars', ['rows' => $s['byWeekday']])</div>
            <div class="card"><h3>Busiest hours</h3>@include('admin._bars', ['rows' => $s['byHour']])</div>
            <div class="card"><h3>Booking status</h3>@include('admin._bars', ['rows' => $s['bookingStatus']])</div>
        </div>
    </section>

    <section class="rtab" id="tab-people" hidden>
        <div class="masonry">
            <div class="card"><h3>Chats by channel</h3>@include('admin._bars', ['rows' => $s['chatsByChannel']])</div>
            <div class="card"><h3>New customers by score</h3>@include('admin._bars', ['rows' => $s['leadsByScore']])</div>
            <div class="card"><h3>Most tapped buttons</h3>@include('admin._bars', ['rows' => $s['topTaps']])</div>
            <div class="card"><h3>Survey ratings</h3>@include('admin._bars', ['rows' => $s['ratings']])</div>
        </div>
    </section>

    <section class="rtab" id="tab-money" hidden>
        <div class="masonry">
            <div class="card"><h3>Orders</h3>
                @forelse ($s['ordersByStatus'] as $o)<ul><li><span>{{ $o['label'] }}</span><span><b>{{ $o['n'] }}</b> <span class="mute">· {{ $money($o['total']) }}</span></span></li></ul>@empty<p class="mute">No orders in this period.</p>@endforelse</div>
            <div class="card"><h3>Broadcasts sent</h3>
                @forelse ($s['broadcasts'] as $b)<ul><li><a href="{{ route('broadcasts.show', $b->id) }}">{{ $b->name }}</a><span class="mute">{{ $b->sent_count }} sent, {{ $b->skipped_count }} skipped</span></li></ul>@empty<p class="mute">None in this period.</p>@endforelse</div>
            <div class="card"><h3>AI</h3><p><b style="font-size:22px">{{ $s['aiCalls'] }}</b> <span class="mute">AI calls in this period</span></p><p class="mute" style="margin-top:6px"><a href="{{ route('ai') }}">Open AI assistant</a></p></div>
        </div>
    </section>
@endsection
@push('scripts')
<script>
    (function () {
        var p = document.getElementById('period'), c = document.getElementById('custom-dates');
        var tabs = document.querySelectorAll('#report-tabs a');
        function show(name) {
            if (!document.getElementById('tab-' + name)) { name = 'overview'; }
            document.querySelectorAll('.rtab').forEach(function (t) { t.hidden = t.id !== 'tab-' + name; });
            tabs.forEach(function (a) { a.classList.toggle('on', a.dataset.tab === name); });
        }
        tabs.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); history.replaceState(null, '', '#' + a.dataset.tab); show(a.dataset.tab); }); });
        show(location.hash.replace('#', '') || 'overview');
        p.addEventListener('change', function () { c.hidden = p.value !== 'custom'; if (p.value !== 'custom') { document.getElementById('filters').submit(); } });
    })();
</script>
@endpush
