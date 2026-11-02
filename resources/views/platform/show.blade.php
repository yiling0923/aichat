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
    $tone = fn ($s) => in_array($s, ['active', 'internal'], true) ? 'ok' : (str_contains($s, 'trial') && $s !== 'trial_expired' ? 'warn' : 'hot');
    $st = fn (string $label, string $tone = 'mute') => '<span class="st st-'.$tone.'">'.e($label).'</span>';
    $stateTone = ['internal' => 'ok', 'trial' => 'ok', 'trial_expired' => 'hot', 'active' => 'ok', 'past_due' => 'hot', 'canceled' => 'mute'];
    $stateLabel = ['internal' => 'Active', 'trial' => 'Active', 'trial_expired' => 'Expired', 'active' => 'Active', 'past_due' => 'Past due', 'canceled' => 'Canceled'];
    $money = fn ($v) => $currency.' '.number_format($v, 2);
    $date = fn ($d) => $d ? \App\Support\Lang::fmt($d, 'j M Y', 'Y年n月j日') : '-';
@endphp
<header class="plat-bar">
    <b><span class="logo">A</span> <a href="{{ route('platform.index') }}" style="color:inherit;text-decoration:none">{{ __('Platform console') }}</a></b>
    <span class="mute">{{ auth()->user()->email }}</span>
    @include('_guest_lang')
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn small" type="submit">{{ __('Sign out') }}</button></form>
</header>
<main class="plat-main plat-wide">
    <a class="crumb" href="{{ route('platform.index') }}">&larr; {{ __('All businesses') }}</a>
    @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
    @foreach ($errors->all() as $error)<div class="note err">{{ $error }}</div>@endforeach

    <div class="page-head">
        <div>
            <h1>{{ $b->name }} @if ($b->status === 'paused') {!! $st(__('Paused'), 'hot') !!}@endif</h1>
            <p class="mute">{{ $b->slug }} · {{ __('joined :date', ['date' => $date($b->created_at)]) }} · {{ $b->timezone }}</p>
            @if ($b->status === 'paused' && $b->paused_reason)<p style="margin:8px 0 0"><span class="reason-box" title="{{ __('Why it was paused') }}">{{ __('Reason: :why', ['why' => $b->paused_reason]) }}</span></p>@endif
        </div>
        <div class="bar" style="gap:8px;flex-wrap:wrap">
            <a class="btn small" href="{{ route('chat.show', $b->slug) }}" target="_blank" rel="noopener">{{ __('Customer chat') }}</a>
            @if ($b->status === 'paused')
                <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/resume') }}">@csrf<button class="btn small primary" type="submit">{{ __('Resume') }}</button></form>
            @else
                <button type="button" class="btn small danger" id="pause-btn">{{ __('Pause') }}</button>
            @endif
        </div>
    </div>

    <div class="kpis main four">
        <div class="kpi"><span class="kpi-l">{{ __('Chats, last 30 days') }}</span><b>{{ $stats['chats'] }}</b><span class="kpi-s">{{ $stats['lastChat'] ? __('last message :when', ['when' => \Illuminate\Support\Carbon::parse($stats['lastChat'])->diffForHumans()]) : __('no messages yet') }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Customers') }}</span><b>{{ $stats['customers'] }}</b><span class="kpi-s">{{ __(':n new in 30 days', ['n' => $stats['newCustomers']]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Bookings, last 30 days') }}</span><b>{{ $stats['bookings'] }}</b><span class="kpi-s">{{ __(':n coming up', ['n' => $stats['upcoming']]) }}</span></div>
        <div class="kpi kpi-money"><span class="kpi-l">{{ __('Customer money, last 30 days') }}</span><b>{{ $money($stats['sales']) }}</b><span class="kpi-s">{{ __(':n paid orders', ['n' => $stats['orders']]) }}</span></div>
    </div>

    <div class="plat-grid">
    <div class="rp-two">
        <div class="card">
            <h3>{{ __('Plan') }} {!! $st(__($stateLabel[$state] ?? ucfirst(str_replace('_', ' ', $state))), $stateTone[$state] ?? 'mute') !!}</h3>
            <p class="mute">{{ $state === 'trial' ? __('Trial ends :d', ['d' => $date($b->trial_ends_at)]) : ($b->current_period_ends_at ? __('Paid until :d', ['d' => $date($b->current_period_ends_at)]) : '') }}</p>
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

        <div class="card">
            <h3>{{ __('Usage and limits') }}</h3>
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

    <div class="rp-two">
        <div class="card">
            <h3>{{ __('Look inside (support)') }}</h3>
            <p class="mute">{{ __('Open this business\'s staff area to help its owner. A banner shows on every page, and starting and ending it is written in the activity log.') }}</p>
            @if ($hasOwner)
                <div class="bar" style="gap:8px;flex-wrap:wrap">
                    <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/support') }}">@csrf<input type="hidden" name="mode" value="read"><button class="btn primary" type="submit">{{ __('View (read-only)') }}</button></form>
                    <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/support') }}" onsubmit="return confirm({{ json_encode(__('You will be signed in as this business\'s owner and can change things. Continue?'), JSON_UNESCAPED_UNICODE) }})">@csrf<input type="hidden" name="mode" value="edit"><button class="btn" type="submit">{{ __('Act as the owner') }}</button></form>
                </div>
            @else
                <p class="note warn">{{ __('This business has no active owner to open.') }}</p>
            @endif
            <h4 style="margin-top:18px">{{ __('Locked out? Set a new password') }}</h4>
            <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/password') }}" class="bar" style="gap:8px;flex-wrap:wrap" autocomplete="off">@csrf
                <select name="user_id" aria-label="{{ __('Person') }}">@foreach ($team as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>@endforeach</select>
                <input type="text" name="new_password" minlength="8" maxlength="100" required placeholder="{{ __('New password (8 or more characters)') }}" autocomplete="off">
                <button class="btn" type="submit">{{ __('Set password') }}</button>
            </form>
        </div>

        <div class="card">
            <h3>{{ __('Team') }} ({{ $team->count() }})</h3>
            <div class="table-wrap" style="max-height:none"><table class="table"><tbody>
                @foreach ($team as $u)
                    <tr><td><b>{{ $u->name }}</b><br><span class="mute">{{ $u->email }}</span></td><td><span class="tag {{ $u->role === 'owner' ? 'ok' : ($u->role === 'manager' ? 'warn' : '') }}">{{ __(ucfirst($u->role)) }}</span></td><td>@unless ($u->active){!! $st(__('Switched off'), 'hot') !!}@endunless</td></tr>
                @endforeach
            </tbody></table></div>
            <h4 style="margin-top:16px">{{ __('Channels') }} ({{ $channels->count() }})</h4>
            @forelse ($channels as $c)
                <div class="lim-row" style="grid-template-columns: 1fr auto"><span><b>{{ ucfirst($c->type) }}</b> {{ $c->name }}<br><small class="mute">{{ $c->last_inbound_at ? __('last message :when', ['when' => $c->last_inbound_at->diffForHumans()]) : __('no message received yet') }}@if ($c->last_error) · {{ __('has a problem') }}@endif</small></span>{!! $st(! $c->active ? __('Switched off') : ($c->last_error ? __('Needs attention') : __('Connected')), ! $c->active ? 'hot' : ($c->last_error ? 'warn' : 'ok')) !!}</div>
            @empty
                <p class="mute">{{ __('No channels connected. The website chat works without one.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="rp-two">
        <div class="card">
            <h3>{{ __('Payments to you') }}</h3>
            <div class="table-wrap" style="max-height:none"><table class="table"><tbody>
                @forelse ($payments as $p)
                    <tr><td>{{ $date($p->created_at) }}</td><td>{{ ucfirst((string) $p->plan) }}</td><td style="text-align:right">{{ strtoupper($p->currency ?? '') }} {{ number_format($p->amount_cents / 100, 2) }}</td><td>{!! $st(__(ucfirst((string) $p->status)), $p->status === 'paid' ? 'ok' : 'warn') !!}</td></tr>
                @empty
                    <tr><td class="empty">{{ __('No payments yet.') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="card">
            <h3>{{ __('Activity on this business') }}</h3>
            @forelse ($audits as $a)
                <div class="lim-row" style="grid-template-columns: 1fr"><span><span class="tag">{{ __(\App\Support\PlatformActions::label($a->action)) }}</span> {{ $a->details }}<br><small class="mute">{{ \App\Support\Lang::fmt($a->created_at, 'j M Y, H:i', 'Y年n月j日 H:i') }} · {{ $a->user?->email ?? '-' }}</small></span></div>
            @empty
                <p class="mute">{{ __('Nothing yet.') }}</p>
            @endforelse
        </div>
    </div>
    </div>
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
