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

    <section class="td-hero">
        <div>
            <h1>{{ $greet }}, {{ $first }}</h1>
            <p class="mute">{{ $now->format('l, j F Y') }}
                @if ($waiting->count())· <b class="td-alert">{{ $waiting->count() }} {{ $waiting->count() === 1 ? 'chat needs' : 'chats need' }} you</b>
                @elseif ($upcoming)· Next up: <b>{{ $upcoming->starts_at->copy()->setTimezone($tz)->format('H:i') }}</b> {{ $upcoming->customer?->name ?: $upcoming->service->label('en') }}
                @else· A quiet day so far @endif</p>
        </div>
        <div class="td-actions">
            <a class="btn primary" href="{{ route('inbox') }}">Open inbox</a>
            <a class="btn" href="{{ route('schedule') }}">Schedule</a>
            <a class="btn" href="{{ route('tasks') }}">+ Task</a>
            <button type="button" class="btn" id="td-copy" data-link="{{ $chatUrl }}" title="Copy your customer chat link">Copy chat link</button>
        </div>
    </section>

    @if ($checklist)
        @php $done = collect($checklist)->where('done', true)->count(); @endphp
        <div class="card setup-card">
            <div class="bar spread"><h3>Get set up</h3><span class="mute">{{ $done }} of {{ count($checklist) }} done</span></div>
            <div class="progress"><span style="width: {{ round($done / count($checklist) * 100) }}%"></span></div>
            <ul>
                @foreach ($checklist as $step)
                    <li><span>@if ($step['done'])<span class="tick ok">&#10003;</span> <s class="mute">{{ $step['text'] }}</s>@else<span class="tick">&#9675;</span> {{ $step['text'] }}@endif</span>
                        @unless ($step['done'])<a class="btn small" href="{{ $step['url'] }}">Do it</a>@endunless</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="td-kpis">
        <a class="td-kpi {{ $waiting->count() ? 'alert' : '' }}" href="{{ route('inbox') }}">
            <span class="ic">✉</span><span class="kpi-l">Waiting for staff</span><b>{{ $waiting->count() }}</b>
            <span class="kpi-s">{{ $waiting->count() ? 'Reply to take over from the bot' : 'The bot is handling everything' }}</span></a>
        <a class="td-kpi" href="{{ route('schedule') }}">
            <span class="ic">▦</span><span class="kpi-l">Bookings today</span><b>{{ $bookings->count() }}</b>
            <span class="kpi-s">{{ $tomorrow }} tomorrow</span></a>
        <a class="td-kpi" href="{{ route('inbox') }}">
            <span class="ic">☰</span><span class="kpi-l">Open chats</span><b>{{ $openChats }}</b>
            <span class="kpi-s">across all channels</span></a>
        <a class="td-kpi" href="{{ route('customers') }}">
            <span class="ic">+</span><span class="kpi-l">New customers</span><b>{{ $newLeads }}</b>
            <span class="kpi-s {{ $leadsDelta > 0 ? 'up' : ($leadsDelta < 0 ? 'down' : '') }}">{{ $delta($leadsDelta) }}</span></a>
        <a class="td-kpi" href="{{ route('orders') }}">
            <span class="ic">$</span><span class="kpi-l">Paid, last 7 days</span><b>{{ $money($revenueWeek) }}</b>
            <span class="kpi-s {{ ($revenueDelta ?? 0) > 0 ? 'up' : (($revenueDelta ?? 0) < 0 ? 'down' : '') }}">{{ $revenueDelta === null ? 'no sales the week before' : ($revenueDelta >= 0 ? '▲ ' : '▼ ').abs($revenueDelta).'% vs the week before' }}</span></a>
    </div>

    <div class="td-cols">
        <div class="td-main">
            @if ($waiting->count())
                <section class="card td-needs">
                    <div class="bar spread"><h3>Needs a person</h3><a href="{{ route('inbox') }}" class="mute">Open inbox</a></div>
                    @foreach ($waiting as $c)
                        <div class="td-row">
                            <span class="td-av warn">{{ mb_strtoupper(mb_substr($c->customer?->name ?: ($c->contact_name ?: 'V'), 0, 1)) }}</span>
                            <div class="td-id"><b>{{ $c->customer?->name ?: ($c->contact_name ?: 'Visitor '.$c->id) }}</b>@php $mins = $c->handover_at ? (int) $c->handover_at->diffInMinutes(now()) : 0; @endphp<small class="{{ $mins >= 10 ? 'td-late' : 'mute' }}">{{ $c->handover_reason ?: 'Asked for a person' }}@if ($c->handover_at) · waiting {{ $mins < 60 ? $mins.' min' : $c->handover_at->diffForHumans(null, true) }}@endif</small></div>
                            <a class="btn small primary" href="{{ route('inbox', ['c' => $c->id]) }}">Reply</a>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="card">
                <div class="bar spread"><h3>Today's bookings</h3><a href="{{ route('schedule') }}" class="mute">Open schedule</a></div>
                @forelse ($bookings as $b)
                    @php
                        $s = $b->starts_at->copy()->setTimezone($tz); $e = $b->ends_at->copy()->setTimezone($tz);
                        $state = $b->status === 'no_show' ? 'noshow' : ($b->status === 'completed' || $b->ends_at->lt(now()) ? 'done' : ($b->starts_at->lte(now()) ? 'now' : ($upcoming && $upcoming->id === $b->id ? 'next' : 'later')));
                    @endphp
                    <div class="td-slot {{ $state }}">
                        <div class="td-time"><b>{{ $s->format('H:i') }}</b><small>{{ $e->format('H:i') }}</small></div>
                        <div class="td-id"><b>{{ $b->customer?->name ?: 'Walk-in' }}</b><small class="mute">{{ $b->service->label('en') }} · {{ $b->practitioner->name }}</small></div>
                        <span class="tag {{ ['now' => 'ok', 'next' => 'warn', 'noshow' => 'hot'][$state] ?? '' }}">{{ ['now' => 'Now', 'next' => 'Next, '.$b->starts_at->diffForHumans(null, true), 'later' => 'Later', 'done' => 'Done', 'noshow' => 'No-show'][$state] }}</span>
                    </div>
                @empty
                    <p class="td-empty">No bookings today. Customers can book any time from your chat link, and you can move them in the <a href="{{ route('schedule') }}">schedule</a>.</p>
                @endforelse
            </section>
        </div>

        <aside class="td-side">
            <section class="card">
                <div class="bar spread"><h3>Tasks for you</h3><a href="{{ route('tasks') }}" class="mute">All tasks</a></div>
                @forelse ($tasks as $t)
                    @php $due = $t->due_at?->copy()->setTimezone($tz); $late = $due && $due->lt($now); @endphp
                    <div class="td-task">
                        <form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}">@csrf<button type="submit" class="td-check" aria-label="Mark done" title="Mark done"></button></form>
                        <div class="td-id"><b>{{ $t->title }}</b><small class="{{ $late ? 'td-late' : 'mute' }}">{{ $due ? ($late ? 'Overdue · ' : '').$due->format('D j M, H:i') : 'No date' }}</small></div>
                    </div>
                @empty
                    <p class="td-empty">Nothing to do. <a href="{{ route('tasks') }}">Add a follow-up</a>.</p>
                @endforelse
            </section>

            <section class="card">
                <div class="bar spread"><h3>This week</h3><span class="mute">{{ $weekTotal }} {{ $weekTotal === 1 ? 'booking' : 'bookings' }}</span></div>
                <div class="td-week">
                    @foreach ($week as $d)
                        <div class="td-day {{ $d['today'] ? 'today' : '' }}" title="{{ $d['count'] }} on {{ $d['label'] }} {{ $d['date'] }}">
                            <span class="n">{{ $d['count'] ?: '' }}</span>
                            <i style="height: {{ $d['count'] ? max(8, round($d['count'] / $weekMax * 70)) : 3 }}px"></i>
                            <small>{{ $d['label'] }}</small>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($recent->count())
                <section class="card">
                    <h3>Recent activity</h3>
                    @foreach ($recent as $n)
                        <a class="td-act" href="{{ $n->url ?: route('inbox') }}"><span class="td-ic">{{ $icons[$n->type] ?? '•' }}</span><span class="td-id"><b>{{ $n->title }}</b><small class="mute">{{ \Illuminate\Support\Str::limit(str_replace(' [sample]', '', (string) $n->body), 48) }}</small></span><time>{{ $n->created_at->diffForHumans(null, true, true) }}</time></a>
                    @endforeach
                </section>
            @endif

            @if ($aiUsage)
                @php $pct = min(100, round($aiUsage['used'] / max(1, $aiUsage['limit']) * 100)); @endphp
                <section class="card">
                    <div class="bar spread"><h3>AI replies this month</h3><a href="{{ route('billing') }}" class="mute">Plan</a></div>
                    <div class="bar spread"><span class="mute">{{ number_format($aiUsage['used']) }} of {{ number_format($aiUsage['limit']) }}</span><b>{{ $pct }}%</b></div>
                    <div class="progress {{ $pct >= 90 ? 'hot' : ($pct >= 70 ? 'warn' : '') }}"><span style="width: {{ $pct }}%"></span></div>
                </section>
            @endif
        </aside>
    </div>
@endsection
@push('scripts')
<script>
(function () {
  var b = document.getElementById('td-copy');
  if (!b) { return; }
  b.addEventListener('click', function () {
    var link = b.dataset.link, done = function () { var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1400); };
    if (navigator.clipboard) { navigator.clipboard.writeText(link).then(done); } else { var i = document.createElement('input'); i.value = link; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); done(); }
  });
})();
</script>
@endpush
