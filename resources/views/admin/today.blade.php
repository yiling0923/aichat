@extends('admin.layout')
@section('title', __('Today'))
@section('content')
    @php
        $now = now($tz);
        $first = explode(' ', trim(auth()->user()->name))[0];
        $greet = $hour < 12 ? __('Good morning') : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
        $zh = app()->getLocale() === 'zh';
        $lbl = app()->getLocale() === 'zh' ? 'zh' : 'en';
        $fmt = fn ($c, $en, $cn) => $c->translatedFormat($zh ? $cn : $en);
        $upcoming = $bookings->first(fn ($b) => $b->status === 'confirmed' && $b->ends_at->gt(now()));
        $weekMax = max(1, collect($week)->max('count'));
        $weekTotal = collect($week)->sum('count');
        $money = fn ($v) => $currency.' '.number_format($v, 0);
        $icons = ['handover' => '!', 'message' => '✉', 'booking' => '▦', 'survey' => '★', 'plan' => '$', 'task' => '✓'];
        $delta = fn ($n) => $n === 0 ? 'same as the week before' : ($n > 0 ? '▲ '.$n.' more than last week' : '▼ '.abs($n).' fewer than last week');
    @endphp

    <header class="tp-hero">
        <div class="tp-title">
            <span class="tp-date">{{ $fmt($now, 'l, j F Y', 'Y年n月j日 l') }}</span>
            <h1>{{ __(':greet, :name', ['greet' => $greet, 'name' => $first]) }}</h1>
            <p class="tp-sub">
                @if ($waiting->count())<b class="tp-red">{{ $waiting->count() === 1 ? __('1 chat needs a reply') : __(':n chats need a reply', ['n' => $waiting->count()]) }}</b>
                @elseif ($upcoming){!! __('Next up at :time: :who', ['time' => '<b>'.e($upcoming->starts_at->copy()->setTimezone($tz)->format('H:i')).'</b>', 'who' => e($upcoming->customer?->name ?: $upcoming->service->label($lbl))]) !!}
                @else {{ __('A quiet day so far.') }} @endif
            </p>
        </div>
        <div class="tp-actions">
            <button type="button" class="btn ghost" id="td-copy" data-link="{{ $chatUrl }}" title="{{ __('Copy your customer chat link') }}">{{ __('Copy chat link') }}</button>
            <a class="btn" href="{{ route('tasks', ['new' => 1]) }}">{{ __('+ Task') }}</a>
            <a class="btn" href="{{ route('schedule') }}">{{ __('Schedule') }}</a>
            <a class="btn primary" href="{{ route('inbox') }}">{{ __('Open inbox') }}</a>
        </div>
    </header>

    @if ($checklist)
        @php $done = collect($checklist)->where('done', true)->count(); @endphp
        <section class="tp-panel tp-setup">
            <div class="tp-head"><h3>{{ __('Get set up') }}</h3><span class="tp-count">{{ __(':a of :b done', ['a' => $done, 'b' => count($checklist)]) }}</span><a class="btn small" href="{{ route('guide') }}" style="margin-left:auto">{{ __('Open the setup guide') }}</a></div>
            <div class="progress" style="margin:0 20px"><span style="width: {{ round($done / count($checklist) * 100) }}%"></span></div>
            @foreach ($checklist as $step)
                <div class="tp-row">
                    <span class="tp-ck {{ $step['done'] ? 'ok' : '' }}">{{ $step['done'] ? '✓' : '' }}</span>
                    <div class="tp-id"><b class="{{ $step['done'] ? 'tp-done' : '' }}">{{ __($step['text']) }}</b></div>
                    @unless ($step['done'])<a class="btn small" href="{{ $step['url'] }}">{{ __('Do it') }}</a>@endunless
                </div>
            @endforeach
        </section>
    @endif

    <section class="tp-panel at-panel {{ count($attention) ? '' : 'clear' }}">
        @if (count($attention))
            <div class="tp-head"><h3>{{ __('Needs your attention') }}</h3><span class="tp-badge red">{{ count($attention) }}</span></div>
            @foreach ($attention as $a)
                <div class="tp-row at-row at-{{ $a['tone'] }}">
                    <span class="at-ic">{{ $a['icon'] }}</span>
                    <div class="tp-id"><b>{{ $a['title'] }}</b><small>{{ $a['sub'] }}</small></div>
                    <a class="btn small {{ $a['tone'] === 'red' ? 'primary' : '' }}" href="{{ $a['url'] }}">{{ $a['cta'] }}</a>
                </div>
            @endforeach
        @else
            <div class="tp-row at-ok"><span class="at-ic">✓</span><div class="tp-id"><b>{{ __('All clear') }}</b><small>{{ __('Nothing is waiting for you right now.') }}</small></div></div>
        @endif
    </section>

    <section class="tp-stats">
        <a class="tp-stat {{ $waiting->count() ? 'alert' : '' }}" href="{{ route('inbox') }}"><span class="tp-sl">{{ __('Waiting for staff') }}</span><b>{{ $waiting->count() }}</b><span class="tp-sn">{{ $waiting->count() ? __('Reply to take over') : __('All handled by the bot') }}</span></a>
        <a class="tp-stat" href="{{ route('schedule') }}"><span class="tp-sl">{{ __('Bookings today') }}</span><b>{{ $bookings->count() }}</b><span class="tp-sn">{{ __(':n tomorrow', ['n' => $tomorrow]) }}</span></a>
        <a class="tp-stat" href="{{ route('inbox') }}"><span class="tp-sl">{{ __('Open chats') }}</span><b>{{ $openChats }}</b><span class="tp-sn">{{ __('all channels') }}</span></a>
        <a class="tp-stat" href="{{ route('customers') }}"><span class="tp-sl">{{ __('New customers') }}</span><b>{{ $newLeads }}</b><span class="tp-sn {{ $leadsDelta > 0 ? 'up' : ($leadsDelta < 0 ? 'down' : '') }}">{{ $leadsDelta === 0 ? __('same as last week') : __(':v vs last week', ['v' => $leadsDelta > 0 ? '▲ '.$leadsDelta : '▼ '.abs($leadsDelta)]) }}</span></a>
        <a class="tp-stat" href="{{ route('orders') }}"><span class="tp-sl">{{ __('Paid, 7 days') }}</span><b>{{ $money($revenueWeek) }}</b><span class="tp-sn {{ ($revenueDelta ?? 0) > 0 ? 'up' : (($revenueDelta ?? 0) < 0 ? 'down' : '') }}">{{ $revenueDelta === null ? __('no sales the week before') : __(':v vs week before', ['v' => ($revenueDelta >= 0 ? '▲ ' : '▼ ').abs($revenueDelta).'%']) }}</span></a>
    </section>

    <div class="tp-cols">
        <div class="tp-main">
            @if ($waiting->count())
                <section class="tp-panel">
                    <div class="tp-head"><h3>{{ __('Needs a person') }}</h3><span class="tp-badge red">{{ $waiting->count() }}</span><a href="{{ route('inbox') }}" class="tp-link">{{ __('Open inbox') }}</a></div>
                    @foreach ($waiting as $c)
                        @php $mins = $c->handover_at ? (int) $c->handover_at->diffInMinutes(now()) : 0; $nm = $c->customer?->name ?: ($c->contact_name ?: __('Visitor :n', ['n' => $c->id])); @endphp
                        <div class="tp-row">
                            <span class="tp-av">{{ mb_strtoupper(mb_substr($nm, 0, 1)) }}</span>
                            <div class="tp-id"><b>{{ $nm }}</b><small>{{ __($c->handover_reason ?: 'Asked for a person') }}</small></div>
                            <span class="tp-wait {{ $mins >= 10 ? 'late' : '' }}">{{ $mins < 60 ? __(':n min', ['n' => $mins]) : $c->handover_at->diffForHumans(null, true) }}</span>
                            <a class="btn small primary" href="{{ route('inbox', ['c' => $c->id]) }}">{{ __('Reply') }}</a>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="tp-panel">
                <div class="tp-head"><h3>{{ __("Today's bookings") }}</h3>@if ($bookings->count())<span class="tp-badge">{{ $bookings->count() }}</span>@endif<a href="{{ route('schedule') }}" class="tp-link">{{ __('Open schedule') }}</a></div>
                @forelse ($bookings as $b)
                    @php
                        $s = $b->starts_at->copy()->setTimezone($tz); $e = $b->ends_at->copy()->setTimezone($tz);
                        $state = $b->status === 'no_show' ? 'noshow' : ($b->status === 'completed' || $b->ends_at->lt(now()) ? 'done' : ($b->starts_at->lte(now()) ? 'now' : ($upcoming && $upcoming->id === $b->id ? 'next' : 'later')));
                    @endphp
                    <div class="tp-row tp-slot {{ $state }}">
                        <div class="tp-time"><b>{{ $s->format('H:i') }}</b><small>{{ $e->format('H:i') }}</small></div>
                        <div class="tp-id"><b>{{ $b->customer?->name ?: __('Walk-in') }}</b><small>{{ $b->service->label($lbl) }} · {{ $b->practitioner->name }}</small></div>
                        <span class="tp-chip {{ $state }}">{{ ['now' => __('In progress'), 'next' => __('Next, in :t', ['t' => $b->starts_at->diffForHumans(null, true)]), 'later' => __('Later'), 'done' => __('Done'), 'noshow' => __('No-show')][$state] }}</span>
                    </div>
                @empty
                    <p class="tp-empty">{!! __('No bookings today. Customers can book any time from your chat link, and you can move bookings in the :link.', ['link' => '<a href="'.route('schedule').'">'.e(__('schedule')).'</a>']) !!}</p>
                @endforelse
            </section>
            @if ($recent->count())
                <section class="tp-panel">
                    <div class="tp-head"><h3>{{ __('Recent activity') }}</h3></div>
                    @foreach ($recent as $n)
                        <a class="tp-row tp-act" href="{{ $n->url ?: route('inbox') }}"><span class="tp-ic">{{ $icons[$n->type] ?? '•' }}</span><span class="tp-id"><b>{{ $n->title }}</b><small>{{ \Illuminate\Support\Str::limit(str_replace(' [sample]', '', (string) $n->body), 46) }}</small></span><time>{{ $n->created_at->diffForHumans(null, true, true) }}</time></a>
                    @endforeach
                </section>
            @endif
        </div>

        <aside class="tp-side">
            @if ($overdueCount)
                <section class="tp-panel od-panel">
                    <div class="tp-head"><h3>{{ __('Overdue') }}</h3><span class="tp-badge red">{{ $overdueCount }}</span><a href="{{ route('tasks', ['due' => 'overdue']) }}" class="tp-link">{{ __('All overdue') }}</a></div>
                    @foreach ($overdue as $t)
                        @php $due = $t->due_at->copy()->setTimezone($tz); @endphp
                        <div class="tp-row">
                            <form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}">@csrf<button type="submit" class="tp-check" aria-label="{{ __('Mark done') }}" title="{{ __('Mark done') }}"></button></form>
                            <div class="tp-id"><b>{{ $t->title }}</b><small class="tp-red">{{ __('Due :when', ['when' => $fmt($due, 'D j M, H:i', 'n月j日 D H:i')]) }} · {{ $t->due_at->diffForHumans(null, true) }} {{ __('late') }}</small></div>
                        </div>
                    @endforeach
                    @if ($overdueCount > $overdue->count())<p class="tp-empty" style="padding:12px 20px"><a href="{{ route('tasks', ['due' => 'overdue']) }}">{{ __(':n more overdue', ['n' => $overdueCount - $overdue->count()]) }}</a></p>@endif
                </section>
            @endif

            <section class="tp-panel">
                <div class="tp-head"><h3>{{ __('Tasks') }}</h3>@if ($tasks->count())<span class="tp-badge">{{ $tasks->count() }}</span>@endif<a href="{{ route('tasks') }}" class="tp-link">{{ __('All tasks') }}</a></div>
                @forelse ($tasks as $t)
                    @php $due = $t->due_at?->copy()->setTimezone($tz); $late = $due && $due->lt($now); @endphp
                    <div class="tp-row">
                        <form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}">@csrf<button type="submit" class="tp-check" aria-label="{{ __('Mark done') }}" title="{{ __('Mark done') }}"></button></form>
                        <div class="tp-id"><b>{{ $t->title }}</b><small class="{{ $late ? 'tp-red' : '' }}">{{ $due ? ($late ? __('Overdue,').($zh ? '' : ' ') : '').$fmt($due, 'D j M, H:i', 'n月j日 D H:i') : __('No date') }}</small></div>
                    </div>
                @empty
                    <p class="tp-empty">{!! __('Nothing to do. :link.', ['link' => '<a href="'.route('tasks').'">'.e(__('Add a follow-up')).'</a>']) !!}</p>
                @endforelse
            </section>

            <section class="tp-panel">
                <div class="tp-head"><h3>{{ __('This week') }}</h3><span class="tp-count">{{ $weekTotal === 1 ? __('1 booking') : __(':n bookings', ['n' => $weekTotal]) }}</span></div>
                <div class="tp-week">
                    @foreach ($week as $d)
                        <div class="tp-day {{ $d['today'] ? 'today' : '' }}" title="{{ __(':n on :day', ['n' => $d['count'], 'day' => $d['label'].' '.$d['date']]) }}">
                            <span class="n">{{ $d['count'] ?: '' }}</span>
                            <i style="height: {{ $d['count'] ? max(8, round($d['count'] / $weekMax * 64)) : 3 }}px"></i>
                            <small>{{ $d['label'] }}</small>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($aiUsage)
                @php $pct = min(100, round($aiUsage['used'] / max(1, $aiUsage['limit']) * 100)); @endphp
                <section class="tp-panel">
                    <div class="tp-head"><h3>{{ __('AI replies this month') }}</h3><a href="{{ route('billing') }}" class="tp-link">{{ __('Plan') }}</a></div>
                    <div class="tp-ai"><div class="bar spread"><span class="mute">{{ __(':a of :b', ['a' => number_format($aiUsage['used']), 'b' => number_format($aiUsage['limit'])]) }}</span><b>{{ $pct }}%</b></div>
                        <div class="progress {{ $pct >= 90 ? 'hot' : ($pct >= 70 ? 'warn' : '') }}"><span style="width: {{ $pct }}%"></span></div></div>
                </section>
            @endif
        </aside>
    </div>

    <section class="tp-panel ds" id="ds" style="margin-top:18px">
        <div class="tp-head"><h3>{{ __('Daily summary') }}</h3>
            <div class="seg" id="ds-tabs"><a href="#" data-d="yesterday" class="on">{{ __('Yesterday') }}</a><a href="#" data-d="today">{{ __('Today so far') }}</a></div>
        </div>
        @foreach ($summaries as $key => $d)
            <div class="ds-body" data-d="{{ $key }}" @if ($key !== 'yesterday') hidden @endif>
                <p class="ds-date">{{ $d['label'] }}</p>
                <div class="ds-grid">
                    <div><b>{{ $d['new_chats'] }}</b><span>{{ __('New chats') }}</span></div>
                    <div><b>{{ $d['bookings_made'] }}</b><span>{{ __('Bookings made') }}</span></div>
                    <div><b>{{ $d['visits'] }}</b><span>{{ __('Visits') }}</span></div>
                    <div><b>{{ $d['new_customers'] }}</b><span>{{ __('New customers') }}</span></div>
                    <div><b>{{ $d['currency'] }} {{ number_format($d['revenue'], 0) }}</b><span>{{ $d['orders_paid'] === 1 ? __('Paid (1 order)') : __('Paid (:n orders)', ['n' => $d['orders_paid']]) }}</span></div>
                    <div><b>{{ $d['cancelled'] }}</b><span>{{ __('Cancelled') }}</span></div>
                </div>
                @if ($d['topics'])
                    <p class="ds-topics"><span class="mute">{{ __('Customers asked about') }}</span> @foreach ($d['topics'] as $t)<span class="tag">{{ __($t['name']) }} <b>{{ $t['count'] }}</b></span>@endforeach</p>
                @else
                    <p class="ds-topics mute">{{ $key === 'today' ? __('No tagged topics yet today.') : __('No tagged topics that day.') }}</p>
                @endif
            </div>
        @endforeach
    </section>
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
    var link = b.dataset.link, done = function () { var t = b.textContent; b.textContent = __t('Copied'); setTimeout(function () { b.textContent = t; }, 1400); };
    if (navigator.clipboard) { navigator.clipboard.writeText(link).then(done); } else { var i = document.createElement('input'); i.value = link; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); done(); }
  });
})();
</script>
@endpush
