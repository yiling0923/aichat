@extends('admin.layout')
@section('title', 'Today')
@section('content')
    @php
        $now = now($tz);
        $first = explode(' ', trim(auth()->user()->name))[0];
        $greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $upcoming = $bookings->first(fn ($b) => $b->status === 'confirmed' && $b->ends_at->gt(now()));
        $weekMax = max(1, collect($week)->max('count'));
        $weekTotal = collect($week)->sum('count');
        $money = fn ($v) => $currency.' '.number_format($v, 0);
        $icons = ['handover' => '!', 'message' => '✉', 'booking' => '▦', 'survey' => '★', 'plan' => '$', 'task' => '✓'];
        $delta = fn ($n) => $n === 0 ? 'same as the week before' : ($n > 0 ? '▲ '.$n.' more than last week' : '▼ '.abs($n).' fewer than last week');
    @endphp

    <header class="tp-hero">
        <div class="tp-title">
            <span class="tp-date">{{ $now->format('l, j F Y') }}</span>
            <h1>{{ $greet }}, {{ $first }}</h1>
            <p class="tp-sub">
                @if ($waiting->count())<b class="tp-red">{{ $waiting->count() }} {{ $waiting->count() === 1 ? 'chat needs' : 'chats need' }} a reply</b>
                @elseif ($upcoming)Next up at <b>{{ $upcoming->starts_at->copy()->setTimezone($tz)->format('H:i') }}</b>: {{ $upcoming->customer?->name ?: $upcoming->service->label('en') }}
                @else A quiet day so far. @endif
            </p>
        </div>
        <div class="tp-actions">
            <button type="button" class="btn ghost" id="td-copy" data-link="{{ $chatUrl }}" title="Copy your customer chat link">Copy chat link</button>
            <a class="btn" href="{{ route('tasks', ['new' => 1]) }}">+ Task</a>
            <a class="btn" href="{{ route('schedule') }}">Schedule</a>
            <a class="btn primary" href="{{ route('inbox') }}">Open inbox</a>
        </div>
    </header>

    @if ($checklist)
        @php $done = collect($checklist)->where('done', true)->count(); @endphp
        <section class="tp-panel tp-setup">
            <div class="tp-head"><h3>Get set up</h3><span class="tp-count">{{ $done }} of {{ count($checklist) }} done</span><a class="btn small" href="{{ route('guide') }}" style="margin-left:auto">Open the setup guide</a></div>
            <div class="progress" style="margin:0 20px"><span style="width: {{ round($done / count($checklist) * 100) }}%"></span></div>
            @foreach ($checklist as $step)
                <div class="tp-row">
                    <span class="tp-ck {{ $step['done'] ? 'ok' : '' }}">{{ $step['done'] ? '✓' : '' }}</span>
                    <div class="tp-id"><b class="{{ $step['done'] ? 'tp-done' : '' }}">{{ $step['text'] }}</b></div>
                    @unless ($step['done'])<a class="btn small" href="{{ $step['url'] }}">Do it</a>@endunless
                </div>
            @endforeach
        </section>
    @endif

    <section class="tp-stats">
        <a class="tp-stat {{ $waiting->count() ? 'alert' : '' }}" href="{{ route('inbox') }}"><span class="tp-sl">Waiting for staff</span><b>{{ $waiting->count() }}</b><span class="tp-sn">{{ $waiting->count() ? 'Reply to take over' : 'All handled by the bot' }}</span></a>
        <a class="tp-stat" href="{{ route('schedule') }}"><span class="tp-sl">Bookings today</span><b>{{ $bookings->count() }}</b><span class="tp-sn">{{ $tomorrow }} tomorrow</span></a>
        <a class="tp-stat" href="{{ route('inbox') }}"><span class="tp-sl">Open chats</span><b>{{ $openChats }}</b><span class="tp-sn">all channels</span></a>
        <a class="tp-stat" href="{{ route('customers') }}"><span class="tp-sl">New customers</span><b>{{ $newLeads }}</b><span class="tp-sn {{ $leadsDelta > 0 ? 'up' : ($leadsDelta < 0 ? 'down' : '') }}">{{ $leadsDelta === 0 ? 'same as last week' : ($leadsDelta > 0 ? '▲ '.$leadsDelta : '▼ '.abs($leadsDelta)).' vs last week' }}</span></a>
        <a class="tp-stat" href="{{ route('orders') }}"><span class="tp-sl">Paid, 7 days</span><b>{{ $money($revenueWeek) }}</b><span class="tp-sn {{ ($revenueDelta ?? 0) > 0 ? 'up' : (($revenueDelta ?? 0) < 0 ? 'down' : '') }}">{{ $revenueDelta === null ? 'no sales the week before' : ($revenueDelta >= 0 ? '▲ ' : '▼ ').abs($revenueDelta).'% vs week before' }}</span></a>
    </section>

    <section class="tp-panel ds" id="ds">
        <div class="tp-head"><h3>Daily summary</h3>
            <div class="seg" id="ds-tabs"><a href="#" data-d="yesterday" class="on">Yesterday</a><a href="#" data-d="today">Today so far</a></div>
        </div>
        @foreach ($summaries as $key => $d)
            <div class="ds-body" data-d="{{ $key }}" @if ($key !== 'yesterday') hidden @endif>
                <p class="ds-date">{{ $d['label'] }}</p>
                <div class="ds-grid">
                    <div><b>{{ $d['new_chats'] }}</b><span>New chats</span></div>
                    <div><b>{{ $d['bookings_made'] }}</b><span>Bookings made</span></div>
                    <div><b>{{ $d['visits'] }}</b><span>Visits</span></div>
                    <div><b>{{ $d['new_customers'] }}</b><span>New customers</span></div>
                    <div><b>{{ $d['currency'] }} {{ number_format($d['revenue'], 0) }}</b><span>Paid ({{ $d['orders_paid'] }} {{ $d['orders_paid'] === 1 ? 'order' : 'orders' }})</span></div>
                    <div><b>{{ $d['cancelled'] }}</b><span>Cancelled</span></div>
                </div>
                @if ($d['topics'])
                    <p class="ds-topics"><span class="mute">Customers asked about</span> @foreach ($d['topics'] as $t)<span class="tag">{{ $t['name'] }} <b>{{ $t['count'] }}</b></span>@endforeach</p>
                @else
                    <p class="ds-topics mute">No tagged topics {{ $key === 'today' ? 'yet today' : 'that day' }}.</p>
                @endif
            </div>
        @endforeach
        <div class="ds-attn">
            <a href="{{ route('inbox') }}" class="{{ $summaries['today']['waiting_now'] ? 'hot' : '' }}"><b>{{ $summaries['today']['waiting_now'] }}</b> waiting for a person</a>
            <a href="{{ route('tasks', ['due' => 'overdue']) }}" class="{{ $summaries['today']['overdue_tasks'] ? 'hot' : '' }}"><b>{{ $summaries['today']['overdue_tasks'] }}</b> overdue tasks</a>
            <a href="{{ route('schedule') }}"><b>{{ $summaries['today']['tomorrow'] }}</b> bookings tomorrow</a>
        </div>
    </section>

    <div class="tp-cols">
        <div class="tp-main">
            @if ($waiting->count())
                <section class="tp-panel">
                    <div class="tp-head"><h3>Needs a person</h3><span class="tp-badge red">{{ $waiting->count() }}</span><a href="{{ route('inbox') }}" class="tp-link">Open inbox</a></div>
                    @foreach ($waiting as $c)
                        @php $mins = $c->handover_at ? (int) $c->handover_at->diffInMinutes(now()) : 0; $nm = $c->customer?->name ?: ($c->contact_name ?: 'Visitor '.$c->id); @endphp
                        <div class="tp-row">
                            <span class="tp-av">{{ mb_strtoupper(mb_substr($nm, 0, 1)) }}</span>
                            <div class="tp-id"><b>{{ $nm }}</b><small>{{ $c->handover_reason ?: 'Asked for a person' }}</small></div>
                            <span class="tp-wait {{ $mins >= 10 ? 'late' : '' }}">{{ $mins < 60 ? $mins.' min' : $c->handover_at->diffForHumans(null, true) }}</span>
                            <a class="btn small primary" href="{{ route('inbox', ['c' => $c->id]) }}">Reply</a>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="tp-panel">
                <div class="tp-head"><h3>Today's bookings</h3>@if ($bookings->count())<span class="tp-badge">{{ $bookings->count() }}</span>@endif<a href="{{ route('schedule') }}" class="tp-link">Open schedule</a></div>
                @forelse ($bookings as $b)
                    @php
                        $s = $b->starts_at->copy()->setTimezone($tz); $e = $b->ends_at->copy()->setTimezone($tz);
                        $state = $b->status === 'no_show' ? 'noshow' : ($b->status === 'completed' || $b->ends_at->lt(now()) ? 'done' : ($b->starts_at->lte(now()) ? 'now' : ($upcoming && $upcoming->id === $b->id ? 'next' : 'later')));
                    @endphp
                    <div class="tp-row tp-slot {{ $state }}">
                        <div class="tp-time"><b>{{ $s->format('H:i') }}</b><small>{{ $e->format('H:i') }}</small></div>
                        <div class="tp-id"><b>{{ $b->customer?->name ?: 'Walk-in' }}</b><small>{{ $b->service->label('en') }} · {{ $b->practitioner->name }}</small></div>
                        <span class="tp-chip {{ $state }}">{{ ['now' => 'In progress', 'next' => 'Next, in '.$b->starts_at->diffForHumans(null, true), 'later' => 'Later', 'done' => 'Done', 'noshow' => 'No-show'][$state] }}</span>
                    </div>
                @empty
                    <p class="tp-empty">No bookings today. Customers can book any time from your chat link, and you can move bookings in the <a href="{{ route('schedule') }}">schedule</a>.</p>
                @endforelse
            </section>
        </div>

        <aside class="tp-side">
            <section class="tp-panel">
                <div class="tp-head"><h3>Tasks</h3>@if ($tasks->count())<span class="tp-badge">{{ $tasks->count() }}</span>@endif<a href="{{ route('tasks') }}" class="tp-link">All tasks</a></div>
                @forelse ($tasks as $t)
                    @php $due = $t->due_at?->copy()->setTimezone($tz); $late = $due && $due->lt($now); @endphp
                    <div class="tp-row">
                        <form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}">@csrf<button type="submit" class="tp-check" aria-label="Mark done" title="Mark done"></button></form>
                        <div class="tp-id"><b>{{ $t->title }}</b><small class="{{ $late ? 'tp-red' : '' }}">{{ $due ? ($late ? 'Overdue, ' : '').$due->format('D j M, H:i') : 'No date' }}</small></div>
                    </div>
                @empty
                    <p class="tp-empty">Nothing to do. <a href="{{ route('tasks') }}">Add a follow-up</a>.</p>
                @endforelse
            </section>

            <section class="tp-panel">
                <div class="tp-head"><h3>This week</h3><span class="tp-count">{{ $weekTotal }} {{ $weekTotal === 1 ? 'booking' : 'bookings' }}</span></div>
                <div class="tp-week">
                    @foreach ($week as $d)
                        <div class="tp-day {{ $d['today'] ? 'today' : '' }}" title="{{ $d['count'] }} on {{ $d['label'] }} {{ $d['date'] }}">
                            <span class="n">{{ $d['count'] ?: '' }}</span>
                            <i style="height: {{ $d['count'] ? max(8, round($d['count'] / $weekMax * 64)) : 3 }}px"></i>
                            <small>{{ $d['label'] }}</small>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($recent->count())
                <section class="tp-panel">
                    <div class="tp-head"><h3>Recent activity</h3></div>
                    @foreach ($recent as $n)
                        <a class="tp-row tp-act" href="{{ $n->url ?: route('inbox') }}"><span class="tp-ic">{{ $icons[$n->type] ?? '•' }}</span><span class="tp-id"><b>{{ $n->title }}</b><small>{{ \Illuminate\Support\Str::limit(str_replace(' [sample]', '', (string) $n->body), 46) }}</small></span><time>{{ $n->created_at->diffForHumans(null, true, true) }}</time></a>
                    @endforeach
                </section>
            @endif

            @if ($aiUsage)
                @php $pct = min(100, round($aiUsage['used'] / max(1, $aiUsage['limit']) * 100)); @endphp
                <section class="tp-panel">
                    <div class="tp-head"><h3>AI replies this month</h3><a href="{{ route('billing') }}" class="tp-link">Plan</a></div>
                    <div class="tp-ai"><div class="bar spread"><span class="mute">{{ number_format($aiUsage['used']) }} of {{ number_format($aiUsage['limit']) }}</span><b>{{ $pct }}%</b></div>
                        <div class="progress {{ $pct >= 90 ? 'hot' : ($pct >= 70 ? 'warn' : '') }}"><span style="width: {{ $pct }}%"></span></div></div>
                </section>
            @endif
        </aside>
    </div>
@endsection
@push('scripts')
<script>
(function () {
  document.querySelectorAll('#ds-tabs a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      document.querySelectorAll('#ds-tabs a').forEach(function (x) { x.classList.toggle('on', x === a); });
      document.querySelectorAll('#ds .ds-body').forEach(function (p) { p.hidden = p.dataset.d !== a.dataset.d; });
    });
  });
  var b = document.getElementById('td-copy');
  if (!b) { return; }
  b.addEventListener('click', function () {
    var link = b.dataset.link, done = function () { var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1400); };
    if (navigator.clipboard) { navigator.clipboard.writeText(link).then(done); } else { var i = document.createElement('input'); i.value = link; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); done(); }
  });
})();
</script>
@endpush
