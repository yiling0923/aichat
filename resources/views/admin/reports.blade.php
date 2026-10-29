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

    <form method="get" action="{{ route('reports') }}" class="fbar" id="filters">
        <select name="period" id="period" aria-label="Period">@foreach ($periods as $k => $name)<option value="{{ $k }}" @selected($r->period === (string) $k)>{{ $name }}</option>@endforeach</select>
        <span class="f-dates" id="custom-dates" @if ($r->period !== 'custom') hidden @endif>
            <input type="date" name="from" value="{{ request('from', $r->from->format('Y-m-d')) }}" aria-label="From"> to <input type="date" name="to" value="{{ request('to', $r->to->format('Y-m-d')) }}" aria-label="To">
        </span>
        <select name="channel" aria-label="Channel"><option value="">All channels</option>@foreach ($channels as $k => $name)<option value="{{ $k }}" @selected($r->channel === $k)>{{ $name }}</option>@endforeach</select>
        <select name="service" aria-label="Service"><option value="">All services</option>@foreach ($services as $svc)<option value="{{ $svc->id }}" @selected($r->serviceId === $svc->id)>{{ $svc->label('en') }}</option>@endforeach</select>
        <select name="practitioner" aria-label="Practitioner"><option value="">Everyone</option>@foreach ($practitioners as $p)<option value="{{ $p->id }}" @selected($r->practitionerId === $p->id)>{{ $p->name }}</option>@endforeach</select>
        <button class="btn primary" type="submit">Apply</button>
        @if ($r->filtered() || $r->period !== '30')<a class="btn" href="{{ route('reports') }}">Reset</a>@endif
    </form>
    @if ($r->channel || $r->serviceId || $r->practitionerId)
        <p class="mute" style="font-size:12px;margin:-4px 0 12px">Channel applies to chats and bookings. Service and practitioner apply to bookings only. Customers, orders and surveys are always for the whole business.</p>
    @endif

    @php
        // A tiny trend line for a KPI card.
        $spark = function (string $key) use ($s) {
            $v = array_column($s['daily'], $key); $n = count($v);
            if ($n < 2 || max($v) <= 0) { return null; }
            $max = max($v); $pts = [];
            foreach ($v as $i => $x) { $pts[] = round($i / ($n - 1) * 100, 1).','.round(26 - $x / $max * 22, 1); }
            return implode(' ', $pts);
        };
    @endphp
    <div class="rp-kpis">
        @foreach ([
            ['Chats started', $kpi['chats']['value'], $kpi['chats']['delta'], 'chats', '✉'],
            ['New customers', $kpi['customers']['value'], $kpi['customers']['delta'], 'customers', '+'],
            ['Bookings made', $kpi['bookings']['value'], $kpi['bookings']['delta'], 'bookings', '▦'],
            ['Paid orders', $money($kpi['revenue']['value']), $kpi['revenue']['delta'], 'revenue', '$'],
        ] as [$label, $value, $delta, $key, $ic])
            @php $pts = $spark($key); @endphp
            <div class="rp-kpi">
                <div class="rp-kpi-top"><span class="rp-ic">{{ $ic }}</span><span class="rp-l">{{ $label }}</span>
                    @if ($delta !== null)<span class="pill {{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}%</span>@endif</div>
                <b>{{ $value }}</b>
                <svg viewBox="0 0 100 28" preserveAspectRatio="none" class="spark" aria-hidden="true">@if ($pts)<polyline points="0,27 {{ $pts }} 100,27" class="sp-fill"/><polyline points="{{ $pts }}" class="sp-line"/>@else<line x1="0" x2="100" y1="26" y2="26" class="sp-flat"/>@endif</svg>
                <span class="rp-s">{{ $delta !== null ? 'vs the period before' : 'no earlier data to compare' }}</span>
            </div>
        @endforeach
    </div>

    @php
        $surveyTone = $s['surveyAvg'] === null ? '' : ($s['surveyAvg'] >= 4 ? 'good' : ($s['surveyAvg'] < 3 ? 'bad' : 'warn'));
        $noShowTone = $s['noShowRate'] === null ? '' : ($s['noShowRate'] <= 5 ? 'good' : ($s['noShowRate'] > 15 ? 'bad' : 'warn'));
        $cancelTone = $s['cancelRate'] === null ? '' : ($s['cancelRate'] <= 10 ? 'good' : ($s['cancelRate'] > 25 ? 'bad' : 'warn'));
        $facts = [
            ['Handed over to staff', $kpi['handovers']['value'], $s['avgWait'] !== null ? 'avg wait '.$s['avgWait'].' min' : 'a person was needed', ''],
            ['Survey score', $s['surveyAvg'] === null ? '-' : number_format($s['surveyAvg'], 1).' / 5', $s['surveyCount'].' '.($s['surveyCount'] === 1 ? 'answer' : 'answers'), $surveyTone],
            ['No-show rate', $s['noShowRate'] === null ? '-' : $s['noShowRate'].'%', 'of finished visits', $noShowTone],
            ['Cancelled', $s['cancelRate'] === null ? '-' : $s['cancelRate'].'%', 'of bookings', $cancelTone],
            ['Repeat customers', $s['repeatCustomers'], 'came back to book', ''],
            ['Opted out', $s['optedOut'], 'sent STOP', ''],
            ['AI calls', $s['aiCalls'], 'in this period', ''],
        ];
    @endphp
    <section class="rp-health">
        @foreach ($facts as [$label, $value, $note, $tone])
            <div class="hl"><span class="hl-l">{{ $label }}@if ($tone)<i class="dot {{ $tone }}" title="{{ ['good' => 'Going well', 'warn' => 'Keep an eye on it', 'bad' => 'Needs attention'][$tone] }}"></i>@endif</span><b>{{ $value }}</b><small>{{ $note }}</small></div>
        @endforeach
    </section>

    <div class="tabs" id="report-tabs">
        <a href="#overview" data-tab="overview" class="on">Overview</a>
        <a href="#bookings" data-tab="bookings">Bookings</a>
        <a href="#people" data-tab="people">People</a>
        <a href="#money" data-tab="money">Money</a>
    </div>

    <section class="rtab" id="tab-overview">
        <div class="card rp-chart">
            <div class="bar spread">
                <h3>Day by day</h3>
                <div class="seg" id="ch-tabs">
                    <a href="#" data-k="chats" class="on">Chats</a><a href="#" data-k="bookings">Bookings</a><a href="#" data-k="customers">New customers</a><a href="#" data-k="revenue">Paid</a>
                </div>
            </div>
            <div class="rp-total" id="ch-total"></div>
            <div class="rp-canvas" id="ch-canvas"><div class="rp-tip" id="ch-tip" hidden></div></div>
        </div>
        <div class="rp-two">
            <div class="card"><h3>Top services</h3>@include('admin._bars', ['rows' => $s['topServices'], 'rank' => true])</div>
            <div class="card"><h3>Chats by channel</h3>
                @php $chTotal = collect($s['chatsByChannel'])->sum('n'); $pal = ['#0f766e', '#2563eb', '#f59e0b', '#9333ea', '#64748b']; @endphp
                @if ($chTotal)
                    <div class="stack">@foreach ($s['chatsByChannel'] as $i => $row)<i style="flex: {{ $row['n'] }}; background: {{ $pal[$i % 5] }}" title="{{ $row['label'] }}: {{ $row['n'] }}"></i>@endforeach</div>
                    <div class="legend-list">@foreach ($s['chatsByChannel'] as $i => $row)<div><i style="background: {{ $pal[$i % 5] }}"></i><span>{{ $row['label'] }}</span><b>{{ $row['n'] }}</b><small>{{ round($row['n'] / $chTotal * 100) }}%</small></div>@endforeach</div>
                @else<p class="mute">No chats in this period.</p>@endif
            </div>
        </div>
        <details class="numbers"><summary>Show the daily numbers as a table</summary>
            <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Chats</th><th>New customers</th><th>Bookings</th></tr></thead><tbody>
                @foreach ($s['daily'] as $d)<tr><td>{{ \Carbon\Carbon::parse($d['date'])->format('D j M Y') }}</td><td>{{ $d['chats'] }}</td><td>{{ $d['customers'] }}</td><td>{{ $d['bookings'] }}</td></tr>@endforeach
            </tbody></table></div>
        </details>
    </section>

    <section class="rtab" id="tab-bookings" hidden>
        <div class="rp-two">
            <div class="card"><h3>Top services</h3>@include('admin._bars', ['rows' => $s['topServices'], 'rank' => true])</div>
            <div class="card"><h3>By practitioner</h3>@include('admin._bars', ['rows' => $s['byPractitioner'], 'rank' => true])</div>
            <div class="card"><h3>Busiest weekdays</h3>@include('admin._bars', ['rows' => $s['byWeekday']])</div>
            <div class="card"><h3>Busiest hours</h3>@include('admin._bars', ['rows' => $s['byHour']])</div>
            <div class="card"><h3>Booking status</h3>@include('admin._bars', ['rows' => $s['bookingStatus']])</div>
        </div>
    </section>

    <section class="rtab" id="tab-people" hidden>
        <div class="rp-two">
            <div class="card"><h3>Chats by channel</h3>@include('admin._bars', ['rows' => $s['chatsByChannel'], 'rank' => true])</div>
            <div class="card"><h3>New customers by lead level</h3>@include('admin._bars', ['rows' => $s['leadsByScore']])</div>
            <div class="card"><h3>Most tapped buttons</h3>@include('admin._bars', ['rows' => $s['topTaps'], 'rank' => true])</div>
            <div class="card"><h3>Survey ratings</h3>@include('admin._bars', ['rows' => $s['ratings']])</div>
        </div>
    </section>

    <section class="rtab" id="tab-money" hidden>
        <div class="rp-two">
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
        var data = @json($s['daily']);
        var names = { chats: 'Chats started', bookings: 'Bookings made', customers: 'New customers', revenue: 'Paid orders' };
        var box = document.getElementById('ch-canvas'), tip = document.getElementById('ch-tip'), key = 'chats';
        function fmt(k, v) { return k === 'revenue' ? Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : String(v); }
        function dlabel(d) { return new Date(d + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }); }
        function smooth(pts) {
            if (pts.length < 3) { return 'M' + pts.map(function (p) { return p[0] + ',' + p[1]; }).join('L'); }
            var d = 'M' + pts[0][0] + ',' + pts[0][1];
            for (var i = 0; i < pts.length - 1; i++) {
                var p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2, t = 0.18;
                d += 'C' + (p1[0] + (p2[0] - p0[0]) * t) + ',' + (p1[1] + (p2[1] - p0[1]) * t) + ' ' + (p2[0] - (p3[0] - p1[0]) * t) + ',' + (p2[1] - (p3[1] - p1[1]) * t) + ' ' + p2[0] + ',' + p2[1];
            }
            return d;
        }
        function draw() {
            var W = Math.max(280, box.clientWidth), H = W < 520 ? 220 : 280, L = 40, R = 12, T = 14, B = 28;
            var vals = data.map(function (d) { return d[key]; }), max = Math.max.apply(null, vals.concat([1]));
            var nice = max <= 4 ? Math.ceil(max) : Math.ceil(max / 4) * 4, n = data.length, iw = W - L - R, ih = H - T - B;
            var x = function (i) { return L + (n === 1 ? iw / 2 : i / (n - 1) * iw); }, y = function (v) { return T + ih - v / nice * ih; };
            var pts = vals.map(function (v, i) { return [Math.round(x(i) * 10) / 10, Math.round(y(v) * 10) / 10]; });
            var grid = '';
            [0, 0.5, 1].forEach(function (g) { var yy = T + ih - g * ih; grid += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + yy + '" y2="' + yy + '" class="g"/><text x="' + (L - 8) + '" y="' + (yy + 4) + '" text-anchor="end">' + fmt(key, Math.round(g * nice * 100) / 100) + '</text>'; });
            var xl = ''; [0, Math.floor((n - 1) / 2), n - 1].forEach(function (i, k) { if (data[i]) { xl += '<text x="' + x(i) + '" y="' + (H - 8) + '" text-anchor="' + (k === 0 ? 'start' : (k === 2 ? 'end' : 'middle')) + '">' + new Date(data[i].date + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' }) + '</text>'; } });
            var line = smooth(pts), area = line + 'L' + pts[pts.length - 1][0] + ',' + (T + ih) + 'L' + pts[0][0] + ',' + (T + ih) + 'Z';
            box.querySelectorAll('svg').forEach(function (e) { e.remove(); });
            box.insertAdjacentHTML('afterbegin', '<svg width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + names[key] + ' per day"><defs><linearGradient id="gfill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#0f766e" stop-opacity=".28"/><stop offset="1" stop-color="#0f766e" stop-opacity="0"/></linearGradient></defs>' + grid + xl + '<path d="' + area + '" fill="url(#gfill)"/><path d="' + line + '" class="ln"/><line id="ch-guide" y1="' + T + '" y2="' + (T + ih) + '" class="guide" hidden/><circle id="ch-dot" r="5" class="dotc" hidden/></svg>');
            var total = vals.reduce(function (a, b) { return a + b; }, 0);
            document.getElementById('ch-total').innerHTML = '<b>' + fmt(key, key === 'revenue' ? Math.round(total * 100) / 100 : total) + '</b> <span>' + names[key].toLowerCase() + ' in this period</span>';
            var svg = box.querySelector('svg'), guide = svg.querySelector('#ch-guide'), dot = svg.querySelector('#ch-dot');
            svg.addEventListener('mousemove', function (e) {
                var r = svg.getBoundingClientRect(), mx = e.clientX - r.left, i = n === 1 ? 0 : Math.max(0, Math.min(n - 1, Math.round((mx - L) / iw * (n - 1))));
                guide.setAttribute('x1', x(i)); guide.setAttribute('x2', x(i)); guide.removeAttribute('hidden'); dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(vals[i])); dot.removeAttribute('hidden');
                tip.innerHTML = '<b>' + fmt(key, vals[i]) + '</b> ' + names[key].toLowerCase() + '<br><small>' + dlabel(data[i].date) + '</small>'; tip.hidden = false;
                tip.style.left = Math.min(W - 140, Math.max(0, x(i) - 60)) + 'px'; tip.style.top = Math.max(0, y(vals[i]) - 62) + 'px';
            });
            svg.addEventListener('mouseleave', function () { guide.setAttribute('hidden', ''); dot.setAttribute('hidden', ''); tip.hidden = true; });
        }
        document.querySelectorAll('#ch-tabs a').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); key = a.dataset.k; document.querySelectorAll('#ch-tabs a').forEach(function (x) { x.classList.toggle('on', x === a); }); draw(); }); });
        var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(draw, 120); });
        window.reportsDraw = draw;
    })();
    (function () {
        var p = document.getElementById('period'), c = document.getElementById('custom-dates');
        var tabs = document.querySelectorAll('#report-tabs a');
        function show(name) {
            if (!document.getElementById('tab-' + name)) { name = 'overview'; }
            document.querySelectorAll('.rtab').forEach(function (t) { t.hidden = t.id !== 'tab-' + name; });
            tabs.forEach(function (a) { a.classList.toggle('on', a.dataset.tab === name); });
            if (name === 'overview' && window.reportsDraw) { window.reportsDraw(); }
        }
        tabs.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); history.replaceState(null, '', '#' + a.dataset.tab); show(a.dataset.tab); }); });
        show(location.hash.replace('#', '') || 'overview');
        p.addEventListener('change', function () { c.hidden = p.value !== 'custom'; if (p.value !== 'custom') { document.getElementById('filters').submit(); } });
    })();
</script>
@endpush
