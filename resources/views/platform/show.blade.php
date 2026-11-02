<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $b->name }} · {{ __('Platform console') }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body class="plat">
@php
    use App\Support\PlatformUi as UI;
    $svg = fn ($n) => UI::svg($n);
    $st = fn (string $label, string $tone = 'mute') => UI::pill($label, $tone);
    $stateTone = ['internal' => 'ok', 'trial' => 'ok', 'trial_expired' => 'hot', 'active' => 'ok', 'past_due' => 'hot', 'canceled' => 'mute'];
    $stateLabel = ['internal' => 'Active', 'trial' => 'Active', 'trial_expired' => 'Expired', 'active' => 'Active', 'past_due' => 'Past due', 'canceled' => 'Canceled'];
    $money = fn ($v) => $currency.' '.number_format($v, 2);
    $date = fn ($d) => $d ? \App\Support\Lang::fmt($d, 'j M Y', 'Y年n月j日') : '-';
    $planName = $b->plan === null ? __('Internal') : __((string) (config('plans.plans.'.$b->plan.'.name') ?? ucfirst($b->plan)));
    $paused = $b->status === 'paused';
    $endsAt = in_array($state, ['trial', 'trial_expired'], true) ? $b->trial_ends_at : $b->current_period_ends_at;
    $initial = mb_strtoupper(mb_substr($b->name, 0, 1));
    $owners = $team->where('role', 'owner');
    $brand = ['whatsapp' => ['#16a34a', 'WhatsApp'], 'messenger' => ['#2563eb', 'Messenger'], 'instagram' => ['#db2777', 'Instagram'], 'web' => ['#0f766e', 'Website']];
    $role = fn ($r) => ['owner' => 'info', 'manager' => 'warn'][$r] ?? 'mute';
@endphp
<header class="plat-bar">
    <b><span class="logo">A</span> <a href="{{ route('platform.index') }}" style="color:inherit;text-decoration:none">{{ __('Platform console') }}</a></b>
    <span class="mute">{{ auth()->user()->email }}</span>
    @include('_guest_lang')
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn small" type="submit">{{ __('Sign out') }}</button></form>
</header>
<main class="plat-main plat-wide bz">
    <a class="crumb" href="{{ route('platform.index') }}">&larr; {{ __('All businesses') }}</a>
    @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
    @foreach ($errors->all() as $error)<div class="note err">{{ $error }}</div>@endforeach

    <section class="bz-hero {{ $paused ? 'is-paused' : '' }}">
        <div class="bz-av">{{ $initial }}</div>
        <div class="bz-id">
            <h1>{{ $b->name }}</h1>
            <div class="bz-meta">
                {!! $paused ? $st(__('Paused'), 'hot') : $st(__($stateLabel[$state] ?? ucfirst(str_replace('_', ' ', $state))), $stateTone[$state] ?? 'mute') !!}
                <span class="bz-chip">{!! $svg('plan') !!}{{ $planName }}</span>
                @if ($endsAt)<span class="bz-chip">{!! $svg('hourglass') !!}{{ in_array($state, ['trial', 'trial_expired'], true) ? __('Trial ends :d', ['d' => $date($endsAt)]) : __('Paid until :d', ['d' => $date($endsAt)]) }}</span>@endif
                <span class="mute">{{ $b->slug }} · {{ __('joined :date', ['date' => $date($b->created_at)]) }} · {{ $b->timezone }}</span>
            </div>
            @if ($paused && $b->paused_reason)<p style="margin:10px 0 0"><span class="reason-box" title="{{ __('Why it was paused') }}">{{ __('Reason: :why', ['why' => $b->paused_reason]) }}</span></p>@endif
        </div>
        <div class="bz-actions">
            <a class="ibtn" href="{{ route('chat.show', $b->slug) }}" target="_blank" rel="noopener" data-tip="{{ __('Customer chat') }}" aria-label="{{ __('Customer chat') }}">{!! $svg('chat') !!}</a>
            @if ($paused)
                <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/resume') }}">@csrf<button class="ibtn ibtn-ok" type="submit" data-tip="{{ __('Resume') }}" aria-label="{{ __('Resume') }}">{!! $svg('play') !!}</button></form>
            @else
                <button type="button" class="ibtn ibtn-danger" id="pause-btn" data-tip="{{ __('Pause') }}" aria-label="{{ __('Pause') }}">{!! $svg('pause') !!}</button>
            @endif
        </div>
    </section>

    <nav class="bz-tabs" id="bz-tabs" role="tablist" aria-label="{{ __('Sections') }}">
        <a href="#overview" data-tab="overview" class="on">{!! $svg('home') !!}<span>{{ __('Overview') }}</span></a>
        <a href="#plan" data-tab="plan">{!! $svg('sliders') !!}<span>{{ __('Plan and limits') }}</span></a>
        <a href="#people" data-tab="people">{!! $svg('users') !!}<span>{{ __('People and channels') }}</span><em>{{ $team->count() + $channels->count() }}</em></a>
        <a href="#support" data-tab="support">{!! $svg('lifebuoy') !!}<span>{{ __('Support') }}</span></a>
        <a href="#payments" data-tab="payments">{!! $svg('card') !!}<span>{{ __('Payments') }}</span>@if ($paymentCount)<em>{{ $paymentCount }}</em>@endif</a>
        <a href="#activity" data-tab="activity">{!! $svg('history') !!}<span>{{ __('Activity') }}</span>@if ($audits->count())<em>{{ $audits->count() }}</em>@endif</a>
    </nav>

    {{-- ===== Overview ===== --}}
    <section data-bz="overview">
        <div class="pk-grid four">
            <div class="pk pk-blue">
                <div class="pk-top"><span class="pk-ic">{!! $svg('chat') !!}</span><span class="pk-l">{{ __('Chats, last 30 days') }}</span></div>
                <b>{{ number_format($stats['chats']) }}</b>
                <div class="pk-sub">{{ $stats['lastChat'] ? __('last message :when', ['when' => \Illuminate\Support\Carbon::parse($stats['lastChat'])->diffForHumans()]) : __('no messages yet') }}</div>
                {!! UI::spark($series['chats']) !!}
            </div>
            <div class="pk pk-teal">
                <div class="pk-top"><span class="pk-ic">{!! $svg('users') !!}</span><span class="pk-l">{{ __('Customers') }}</span></div>
                <b>{{ number_format($stats['customers']) }}</b>
                <div class="pk-sub">{{ __(':n new in 30 days', ['n' => $stats['newCustomers']]) }}</div>
            </div>
            <div class="pk pk-violet">
                <div class="pk-top"><span class="pk-ic">{!! $svg('calendar') !!}</span><span class="pk-l">{{ __('Bookings, last 30 days') }}</span></div>
                <b>{{ number_format($stats['bookings']) }}</b>
                <div class="pk-sub">{{ __(':n coming up', ['n' => $stats['upcoming']]) }}</div>
                {!! UI::spark($series['bookings']) !!}
            </div>
            <div class="pk pk-rose">
                <div class="pk-top"><span class="pk-ic">{!! $svg('bag') !!}</span><span class="pk-l">{{ __('Customer money, last 30 days') }}</span></div>
                <b class="pk-money">{{ $money($stats['sales']) }}</b>
                <div class="pk-sub">{{ __(':n paid orders', ['n' => $stats['orders']]) }}</div>
            </div>
        </div>

        <div class="bz-two">
            <div class="card bz-card">
                <h3>{{ __('Usage') }}</h3>
                <p class="mute">{{ __('How much of each limit this business has used.') }} <a href="#plan" data-goto="plan">{{ __('Change limits') }}</a></p>
                @foreach ($kinds as $kind => $k)
                    @php $pct = $k['limit'] ? min(100, (int) round($k['used'] / max(1, $k['limit']) * 100)) : 0; $tone = ! $k['limit'] ? 'ok' : ($pct >= 100 ? 'hot' : ($pct >= 75 ? 'warn' : 'ok')); @endphp
                    <div class="umeter m-{{ $tone }} {{ $k['limit'] ? '' : 'unl' }}">
                        <div class="umeter-top"><span>{{ __($labels[$kind] ?? $kind) }}</span><b>{{ $k['used'] }} <small>/ {{ $k['limit'] ?? __('unlimited') }}</small></b></div>
                        <div class="umeter-bar"><i style="width: {{ $k['limit'] ? max($pct, $k['used'] > 0 ? 3 : 0) : 100 }}%"></i></div>
                    </div>
                @endforeach
            </div>
            <div class="card bz-card">
                <h3>{{ __('At a glance') }}</h3>
                <dl class="bz-facts">
                    <div><dt>{{ __('Plan') }}</dt><dd>{{ $planName }}</dd></div>
                    <div><dt>{{ __('Status') }}</dt><dd>{!! $paused ? $st(__('Paused'), 'hot') : $st(__($stateLabel[$state] ?? ucfirst(str_replace('_', ' ', $state))), $stateTone[$state] ?? 'mute') !!}</dd></div>
                    <div><dt>{{ __('Ends') }}</dt><dd>{{ $date($endsAt) }}</dd></div>
                    <div><dt>{{ __('Owner') }}</dt><dd>{{ $owners->pluck('email')->implode(', ') ?: '-' }}</dd></div>
                    <div><dt>{{ __('People') }}</dt><dd>{{ $team->count() }}</dd></div>
                    <div><dt>{{ __('Channels') }}</dt><dd>{{ __(':a connected of :b', ['a' => $channels->where('active', true)->whereNull('last_error')->count(), 'b' => $channels->count()]) }}</dd></div>
                    <div><dt>{{ __('Paid to you') }}</dt><dd>{{ strtoupper($paymentCurrency) }} {{ number_format($paidTotal / 100, 2) }}</dd></div>
                </dl>
            </div>
            <div class="card bz-card">
                <h3>{{ __('Recent activity') }} <a class="bz-more" href="#activity" data-goto="activity">{{ __('See all') }}</a></h3>
                @forelse ($audits->take(5) as $a)
                    @php [$ic, $tone] = UI::actionLook($a->action); @endphp
                    <div class="tl-item tl-{{ $tone }}"><span class="tl-dot">{!! $svg($ic) !!}</span><div><b>{{ __(\App\Support\PlatformActions::label($a->action)) }}</b> <span>{{ $a->details }}</span><small class="mute">{{ \App\Support\Lang::fmt($a->created_at, 'j M Y, H:i', 'Y年n月j日 H:i') }}</small></div></div>
                @empty
                    <p class="mute">{{ __('Nothing yet.') }}</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ===== Plan and limits ===== --}}
    <section data-bz="plan" hidden>
        <div class="bz-two">
            <div class="card bz-card">
                <h3>{{ __('Plan') }} {!! $paused ? $st(__('Paused'), 'hot') : $st(__($stateLabel[$state] ?? ucfirst(str_replace('_', ' ', $state))), $stateTone[$state] ?? 'mute') !!}</h3>
                <p class="mute">{{ $state === 'trial' || $state === 'trial_expired' ? __('Trial ends :d', ['d' => $date($b->trial_ends_at)]) : ($b->current_period_ends_at ? __('Paid until :d', ['d' => $date($b->current_period_ends_at)]) : __('No end date.')) }}</p>
                <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/plan') }}">@csrf
                    <label class="f"><span>{{ __('Plan') }}</span>
                        <select name="plan" id="plan-sel">
                            @foreach ($plans as $key => $p)<option value="{{ $key }}" @selected($b->plan === $key)>{{ $p['name'] }}@if ($p['price_cents'] > 0) ({{ number_format($p['price_cents'] / 100) }}/{{ __('month') }})@endif</option>@endforeach
                            <option value="internal" @selected($b->plan === null)>{{ __('Internal (no limits, no billing)') }}</option>
                        </select></label>
                    <label class="f"><span id="date-label">{{ __('Trial ends / paid until') }}</span><input type="date" name="date" value="{{ ($b->plan === 'trial' ? $b->trial_ends_at : $b->current_period_ends_at)?->format('Y-m-d') }}"></label>
                    <label class="f" id="extend-wrap"><span>{{ __('Or extend the trial by (days)') }}</span><input type="number" name="extend_days" min="1" max="365" placeholder="14"></label>
                    <p class="mute small-note" style="padding:0">{{ __('A paid plan set here is a manual plan: no payment is taken. Leave the date empty for no end.') }}</p>
                    <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save plan') }}</button></div>
                </form>
            </div>

            <div class="card bz-card">
                <h3>{{ __('Limits') }}</h3>
                <p class="mute">{{ __('Used against the limit. You can give this business its own limits instead of the plan\'s.') }}</p>
                <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/limits') }}">@csrf
                    <div class="lim-table">
                    @foreach ($kinds as $kind => $k)
                        @php $mode = $k['custom'] === 'unlimited' ? 'unlimited' : (is_numeric($k['custom']) ? 'number' : 'default'); @endphp
                        <div class="lim-row">
                            <div class="lim-l"><b>{{ __($labels[$kind] ?? $kind) }}</b><small class="mute">{{ __(':a of :b', ['a' => $k['used'], 'b' => $k['limit'] ?? __('unlimited')]) }} · {{ __('plan: :n', ['n' => $k['plan'] ?? __('unlimited')]) }}</small></div>
                            <select name="mode[{{ $kind }}]" data-kind="{{ $kind }}"><option value="default" @selected($mode === 'default')>{{ __('Plan default') }}</option><option value="number" @selected($mode === 'number')>{{ __('Custom number') }}</option><option value="unlimited" @selected($mode === 'unlimited')>{{ __('Unlimited') }}</option></select>
                            <input type="number" name="number[{{ $kind }}]" min="0" max="1000000" value="{{ is_numeric($k['custom']) ? $k['custom'] : '' }}" @disabled($mode !== 'number') aria-label="{{ __('Custom number') }}">
                        </div>
                    @endforeach
                    </div>
                    <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save limits') }}</button></div>
                </form>
            </div>
        </div>
    </section>

    {{-- ===== People and channels ===== --}}
    <section data-bz="people" hidden>
        <div class="bz-two">
            <div class="card bz-card">
                <h3>{{ __('Team') }} <span class="bz-count">{{ $team->count() }}</span></h3>
                @foreach ($team as $u)
                    <div class="person">
                        <span class="m-av">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        <div class="m-id"><b>{{ $u->name }}</b><small class="mute">{{ $u->email }}</small></div>
                        <div class="person-tags">{!! $st(__(ucfirst($u->role)), $role($u->role)) !!}@unless ($u->active){!! $st(__('Switched off'), 'hot') !!}@endunless</div>
                    </div>
                @endforeach
            </div>
            <div class="card bz-card">
                <h3>{{ __('Channels') }} <span class="bz-count">{{ $channels->count() }}</span></h3>
                @forelse ($channels as $c)
                    @php [$col, $nm] = $brand[$c->type === 'web' ? 'web' : $c->type] ?? ['#64748b', ucfirst($c->type)]; @endphp
                    <div class="chan" style="--c: {{ $col }}">
                        <span class="chan-ic">{{ mb_strtoupper(mb_substr($nm, 0, 1)) }}</span>
                        <div class="m-id"><b>{{ $nm }} <span class="mute" style="font-weight:500">{{ $c->name }}</span></b>
                            <small class="mute">{{ $c->last_inbound_at ? __('last message :when', ['when' => $c->last_inbound_at->diffForHumans()]) : __('no message received yet') }}@if ($c->last_error) · {{ __('has a problem') }}@endif</small></div>
                        {!! $st(! $c->active ? __('Switched off') : ($c->last_error ? __('Needs attention') : __('Connected')), ! $c->active ? 'hot' : ($c->last_error ? 'warn' : 'ok')) !!}
                    </div>
                @empty
                    <p class="mute">{{ __('No channels connected. The website chat works without one.') }}</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ===== Support ===== --}}
    <section data-bz="support" hidden>
        <div class="bz-two">
            <div class="card bz-card bz-opt">
                <span class="bz-opt-ic">{!! $svg('eye') !!}</span>
                <h3>{{ __('View (read-only)') }}</h3>
                <p class="mute">{{ __('See what the owner sees. Nothing can be changed. An orange banner shows on every page.') }}</p>
                @if ($hasOwner)
                    <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/support') }}">@csrf<input type="hidden" name="mode" value="read"><button class="btn primary" type="submit">{{ __('View (read-only)') }}</button></form>
                @else
                    <p class="note warn">{{ __('This business has no active owner to open.') }}</p>
                @endif
            </div>
            <div class="card bz-card bz-opt bz-opt-warn">
                <span class="bz-opt-ic">{!! $svg('lifebuoy') !!}</span>
                <h3>{{ __('Act as the owner') }}</h3>
                <p class="mute">{{ __('You are signed in as the owner and can make changes for them. It is written in the activity log.') }}</p>
                @if ($hasOwner)
                    <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/support') }}" onsubmit="return confirm({{ json_encode(__('You will be signed in as this business\'s owner and can change things. Continue?'), JSON_UNESCAPED_UNICODE) }})">@csrf<input type="hidden" name="mode" value="edit"><button class="btn" type="submit">{{ __('Act as the owner') }}</button></form>
                @endif
            </div>
            <div class="card bz-card bz-opt" style="grid-column: 1 / -1">
                <span class="bz-opt-ic">{!! $svg('key') !!}</span>
                <h3>{{ __('Locked out? Set a new password') }}</h3>
                <p class="mute">{{ __('Pick the person and type a new password for them. The password is never written to the log.') }}</p>
                <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/password') }}" class="bar" style="gap:8px;flex-wrap:wrap" autocomplete="off">@csrf
                    <select name="user_id" aria-label="{{ __('Person') }}">@foreach ($team as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>@endforeach</select>
                    <input type="text" name="new_password" minlength="8" maxlength="100" required placeholder="{{ __('New password (8 or more characters)') }}" autocomplete="off" style="min-width:260px">
                    <button class="btn" type="submit">{{ __('Set password') }}</button>
                </form>
            </div>
        </div>
    </section>

    {{-- ===== Payments ===== --}}
    <section data-bz="payments" hidden>
        <div class="card bz-card">
            <div class="bar spread"><h3 style="margin:0">{{ __('Payments to you') }}</h3><span class="mute">{{ __(':amount in :n payments', ['amount' => strtoupper($paymentCurrency).' '.number_format($paidTotal / 100, 2), 'n' => $paymentCount]) }}</span></div>
            <table class="table bz-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Plan') }}</th><th class="num">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr><td>{{ $date($p->created_at) }}</td><td>{{ ucfirst((string) $p->plan) }}</td><td class="num">{{ strtoupper($p->currency ?? '') }} {{ number_format($p->amount_cents / 100, 2) }}</td><td>{!! $st(__(ucfirst((string) $p->status)), $p->status === 'paid' ? 'ok' : 'warn') !!}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty">{{ __('No payments yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ===== Activity ===== --}}
    <section data-bz="activity" hidden>
        <div class="card bz-card">
            <h3>{{ __('Activity on this business') }}</h3>
            @forelse ($audits as $a)
                @php [$ic, $tone] = UI::actionLook($a->action); @endphp
                <div class="tl-item tl-{{ $tone }}"><span class="tl-dot">{!! $svg($ic) !!}</span><div><b>{{ __(\App\Support\PlatformActions::label($a->action)) }}</b> <span>{{ $a->details }}</span><small class="mute">{{ \App\Support\Lang::fmt($a->created_at, 'j M Y, H:i', 'Y年n月j日 H:i') }} · {{ $a->user?->email ?? '-' }}</small></div></div>
            @empty
                <p class="mute">{{ __('Nothing yet. Plan changes, pauses and support views will show here.') }}</p>
            @endforelse
        </div>
    </section>
</main>

<dialog id="pause-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Pause :name?', ['name' => $b->name]) }}</h3>
    <p class="mute" style="margin-top:4px">{{ __('Its customer chat and staff area are switched off until you resume it. Nothing is deleted.') }}</p>
    <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/pause') }}">@csrf
        <label class="f" style="margin-top:12px"><span>{{ __('Reason (optional, shown to you only)') }}</span><input type="text" name="reason" maxlength="150"></label>
        <div class="dlg-foot"><button class="btn danger" type="submit">{{ __('Pause it') }}</button></div>
    </form>
</dialog>
<script>
(function () {
  // Tabs: the one you used stays open after you save something.
  var key = 'plat-tab-{{ $b->id }}', tabs = document.querySelectorAll('#bz-tabs a'), panels = document.querySelectorAll('[data-bz]');
  function show(name, remember) {
    if (!document.querySelector('[data-bz="' + name + '"]')) { name = 'overview'; }
    tabs.forEach(function (a) { a.classList.toggle('on', a.dataset.tab === name); a.setAttribute('aria-selected', a.dataset.tab === name ? 'true' : 'false'); });
    panels.forEach(function (p) { p.hidden = p.dataset.bz !== name; });
    if (remember) { try { sessionStorage.setItem(key, name); } catch (e) { /* storage blocked: fine */ } }
  }
  tabs.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); show(a.dataset.tab, true); history.replaceState(null, '', '#' + a.dataset.tab); }); });
  document.querySelectorAll('[data-goto]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); show(a.dataset.goto, true); history.replaceState(null, '', '#' + a.dataset.goto); window.scrollTo(0, 0); }); });
  var saved = ''; try { saved = sessionStorage.getItem(key) || ''; } catch (e) { /* ignore */ }
  var fromHash = location.hash.replace('#', '');
  show(fromHash || saved || 'overview', !!fromHash);

  var pd = document.getElementById('pause-dialog'), pb = document.getElementById('pause-btn');
  if (pb) { pb.addEventListener('click', function () { pd.showModal(); }); }
  pd.addEventListener('click', function (e) { if (e.target === pd || e.target.closest('.dlg-x')) { pd.close(); } });
  // Limits: the number box is only used for "Custom number".
  document.querySelectorAll('select[data-kind]').forEach(function (s) {
    var box = s.parentNode.querySelector('input[type=number]');
    s.addEventListener('change', function () { box.disabled = s.value !== 'number'; if (!box.disabled) { box.focus(); } });
  });
  // Plan: the extend box only makes sense for a trial.
  var sel = document.getElementById('plan-sel'), ext = document.getElementById('extend-wrap'), lab = document.getElementById('date-label');
  function planUi() { ext.hidden = sel.value !== 'trial'; lab.textContent = sel.value === 'trial' ? @json(__('Trial ends')) : (sel.value === 'internal' ? @json(__('(not used)')) : @json(__('Paid until (optional)'))); }
  sel.addEventListener('change', planUi); planUi();
})();
</script>
</body>
</html>
