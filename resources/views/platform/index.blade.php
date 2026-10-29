<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Platform console') }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body class="plat">
@php
    $money = fn ($cents, $dec = 0) => $currency.' '.number_format($cents / 100, $dec);
    $group = function ($r) {
        $b = $r['business'];
        if ($b->status === 'paused') { return 'paused'; }
        if (in_array($r['state'], ['trial_expired', 'past_due', 'canceled'], true)) { return 'attention'; }
        if ($r['state'] === 'trial') { return 'trial'; }
        return $r['state'] === 'internal' ? 'internal' : 'paying';
    };
    $counts = collect($rows)->countBy($group);
    $tone = fn ($s) => in_array($s, ['active', 'internal'], true) ? 'ok' : (str_contains($s, 'trial') && $s !== 'trial_expired' ? 'warn' : 'hot');
@endphp
<header class="plat-bar">
    <b><span class="logo">A</span> {{ __('Platform console') }}</b>
    <span class="mute">{{ auth()->user()->email }}</span>
    @include('_guest_lang')
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn small" type="submit">{{ __('Sign out') }}</button></form>
</header>
<main class="plat-main">
    @if (session('status'))<div class="note ok" id="flash">{{ session('status') }}</div>@endif

    <div class="kpis main">
        <div class="kpi"><span class="kpi-l">{{ __('Businesses') }}</span><b>{{ $totals['businesses'] }}</b><span class="kpi-s">{{ __(':n paused', ['n' => $counts['paused'] ?? 0]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('On trial') }}</span><b>{{ $totals['trials'] }}</b><span class="kpi-s">{{ __(':n need attention', ['n' => $counts['attention'] ?? 0]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Paying') }}</span><b>{{ $totals['paying'] }}</b><span class="kpi-s">{{ __(':amount a month', ['amount' => $money($totals['mrr'])]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Taken, last 30 days') }}</span><b>{{ $money($totals['revenue30']) }}</b><span class="kpi-s">{{ __(':n AI calls this month', ['n' => number_format($totals['ai'])]) }}</span></div>
    </div>

    <div class="page-head">
        <div class="seg" id="plat-tabs">
            <a href="#businesses" data-tab="businesses" class="on">{{ __('Businesses') }} ({{ $totals['businesses'] }})</a>
            <a href="#invites" data-tab="invites">{{ __('Invitations') }} ({{ count($invites) }})</a>
        </div>
    </div>

    <section data-panel="businesses">
        <div class="fbar">
            <input type="search" id="biz-q" placeholder="{{ __('Search business name') }}" aria-label="{{ __('Search') }}">
            <div class="seg" id="biz-filter">
                <a href="#" data-g="all" class="on">{{ __('All') }}</a>
                <a href="#" data-g="trial">{{ __('Trial') }} ({{ $counts['trial'] ?? 0 }})</a>
                <a href="#" data-g="paying">{{ __('Paying') }} ({{ $counts['paying'] ?? 0 }})</a>
                <a href="#" data-g="attention">{{ __('Needs attention') }} ({{ $counts['attention'] ?? 0 }})</a>
                <a href="#" data-g="paused">{{ __('Paused') }} ({{ $counts['paused'] ?? 0 }})</a>
            </div>
        </div>
        <div class="table-wrap" style="max-height:none">
        <table class="table" id="biz-table">
            <thead><tr><th>{{ __('Business') }}</th><th>{{ __('Plan') }}</th><th style="text-align:right">{{ app()->getLocale() === 'zh' ? '员工' : 'People' }}</th><th style="text-align:right">{{ __('Chats (30 d)') }}</th><th style="text-align:right">{{ __('AI (month)') }}</th><th style="text-align:right">{{ __('Revenue') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                @php $b = $r['business']; @endphp
                <tr data-g="{{ $group($r) }}" data-s="{{ mb_strtolower($b->name.' '.$b->slug) }}" @class(['is-paused' => $b->status === 'paused'])>
                    <td><b>{{ $b->name }}</b> @if ($b->status === 'paused')<span class="tag hot">{{ __('Paused') }}</span>@endif<br><span class="mute">{{ $b->slug }} · {{ __('joined :date', ['date' => \App\Support\Lang::fmt($b->created_at, 'j M Y', 'Y年n月j日')]) }}</span>@if ($b->status === 'paused' && $b->paused_reason)<br><small class="mute">{{ __('Reason:') }} {{ $b->paused_reason }}</small>@endif</td>
                    <td>{{ __(ucfirst($b->plan ?? 'internal')) }}<br><span class="tag {{ $tone($r['state']) }}">{{ __(ucfirst(str_replace('_', ' ', $r['state']))) }}</span>@if ($r['state'] === 'trial' && $b->trial_ends_at)<br><small class="mute">{{ __('ends :date', ['date' => \App\Support\Lang::fmt($b->trial_ends_at, 'j M', 'n月j日')]) }}</small>@endif</td>
                    <td style="text-align:right">{{ $r['users'] }}</td>
                    <td style="text-align:right">{{ $r['chats'] }}</td>
                    <td style="text-align:right">{{ $r['ai'] }}</td>
                    <td style="text-align:right;white-space:nowrap">{{ $money($r['revenue'], 2) }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a class="btn small" href="{{ route('chat.show', $b->slug) }}" target="_blank" rel="noopener">{{ __('Chat') }}</a>
                        @if ($b->status === 'paused')
                            <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/resume') }}" style="display:inline">@csrf<button class="btn small primary" type="submit">{{ __('Resume') }}</button></form>
                        @else
                            <button type="button" class="btn small danger" data-pause data-action="{{ url('/platform/businesses/'.$b->id.'/pause') }}" data-name="{{ $b->name }}">{{ __('Pause') }}</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr id="biz-empty" hidden><td colspan="7" class="empty">{{ __('No businesses match.') }}</td></tr>
            </tbody>
        </table>
        </div>
    </section>

    <section data-panel="invites" hidden>
        <div class="bar spread" style="margin-bottom:10px">
            <span class="mute">@if ($inviteOnly)<span class="tag warn">{{ __('Invite-only beta is ON') }}</span> {{ __('People need a code to sign up.') }}@else<span class="tag">{{ __('Sign-up is open') }}</span> {!! __('Codes are ignored. Set :setting to require them.', ['setting' => '<code>SIGNUP_MODE=invite</code>']) !!}@endif</span>
            <button type="button" class="btn primary" id="new-invite">{{ __('Make a code') }}</button>
        </div>
        <div class="table-wrap" style="max-height:none">
        <table class="table">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('For') }}</th><th>{{ __('Note') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($invites as $i)
                @php $link = url('/signup?invite='.$i->code); @endphp
                <tr>
                    <td><code>{{ $i->code }}</code></td>
                    <td>{{ $i->email ?? __('Anyone') }}</td>
                    <td>{{ $i->note ?: '-' }}</td>
                    <td>@if ($i->used_at)<span class="tag ok">{{ __('Used :date', ['date' => \App\Support\Lang::fmt($i->used_at, 'j M', 'n月j日')]) }}</span>@else<span class="tag warn">{{ __('Unused') }}</span>@endif</td>
                    <td style="text-align:right">@unless ($i->used_at)<button type="button" class="btn small" data-link="{{ $link }}">{{ __('Copy link') }}</button>@endunless</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ __('No invitations yet. Press "Make a code" to invite your first business.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>
</main>

<dialog id="pause-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3 id="pd-title">{{ __('Pause business') }}</h3>
    <p class="mute" style="margin-top:4px">{{ __('Its customer chat and staff area are switched off until you resume it. Nothing is deleted.') }}</p>
    <form method="post" id="pd-form">@csrf
        <label class="f" style="margin-top:12px"><span>{{ __('Reason (optional, shown to you only)') }}</span><input type="text" name="reason" maxlength="150" placeholder="{{ __('Unpaid, spam, asked to pause...') }}"></label>
        <div class="dlg-foot"><button class="btn danger" type="submit">{{ __('Pause it') }}</button></div>
    </form>
</dialog>

<dialog id="invite-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Make an invitation code') }}</h3>
    <form method="post" action="{{ url('/platform/invites') }}">@csrf
        <label class="f" style="margin-top:12px"><span>{{ __('Only for this email (optional)') }}</span><input type="email" name="email" maxlength="150"></label>
        <label class="f"><span>{{ __('Note (optional)') }}</span><input type="text" name="note" maxlength="120" placeholder="{{ __('Who is it for?') }}"></label>
        <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Make a code') }}</button></div>
    </form>
</dialog>
<div class="toast" id="toast" hidden></div>
<script>
(function () {
  var tab = location.hash === '#invites' || @json(session('status') && (str_contains((string) session('status'), 'Invitation code') || str_contains((string) session('status'), '邀请码'))) ? 'invites' : 'businesses';
  function showTab(t) {
    document.querySelectorAll('#plat-tabs a').forEach(function (a) { a.classList.toggle('on', a.dataset.tab === t); });
    document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== t; });
  }
  document.querySelectorAll('#plat-tabs a').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); showTab(a.dataset.tab); history.replaceState(null, '', '#' + a.dataset.tab); }); });
  showTab(tab);

  var q = document.getElementById('biz-q'), g = 'all', rows = document.querySelectorAll('#biz-table tr[data-g]');
  function filter() {
    var n = 0, t = q.value.trim().toLowerCase();
    rows.forEach(function (r) { var ok = (g === 'all' || r.dataset.g === g) && (!t || r.dataset.s.indexOf(t) > -1); r.hidden = !ok; if (ok) { n++; } });
    document.getElementById('biz-empty').hidden = n > 0;
  }
  q.addEventListener('input', filter);
  document.querySelectorAll('#biz-filter a').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); g = a.dataset.g; document.querySelectorAll('#biz-filter a').forEach(function (x) { x.classList.toggle('on', x === a); }); filter(); });
  });

  function dlg(id) { var d = document.getElementById(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('.dlg-x')) { d.close(); } }); return d; }
  var pd = dlg('pause-dialog'), iv = dlg('invite-dialog');
  document.querySelectorAll('[data-pause]').forEach(function (b) {
    b.addEventListener('click', function () { document.getElementById('pd-form').action = b.dataset.action; document.getElementById('pd-title').textContent = @json(__('Pause :name?', ['name' => '__N__'])).replace('__N__', b.dataset.name); pd.showModal(); });
  });
  document.getElementById('new-invite').addEventListener('click', function () { iv.showModal(); });

  var toast = document.getElementById('toast');
  document.querySelectorAll('[data-link]').forEach(function (b) {
    b.addEventListener('click', function () {
      var done = function () { toast.textContent = @json(__('Invitation link copied')); toast.hidden = false; setTimeout(function () { toast.hidden = true; }, 1600); };
      if (navigator.clipboard) { navigator.clipboard.writeText(b.dataset.link).then(done); } else { var t = document.createElement('input'); t.value = b.dataset.link; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
    });
  });
})();
</script>
</body>
</html>
