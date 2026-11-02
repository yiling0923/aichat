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
    $ico = [
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10"/><path d="M8 7h4M8 11h4M8 15h4"/><path d="M3 21h18"/>',
        'hourglass' => '<path d="M6 2h12M6 22h12"/><path d="M7 2v4a5 5 0 0 0 2 4l3 2-3 2a5 5 0 0 0-2 4v4M17 2v4a5 5 0 0 1-2 4l-3 2 3 2a5 5 0 0 1 2 4v4"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-2z"/><circle cx="17" cy="14.5" r="1.2"/>',
        'coins' => '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M15 10.5c3 .2 6 1.4 6 3v4c0 1.7-2.7 3-6 3-1.6 0-3-.3-4.2-.8"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bag' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'open' => '<path d="M7 17L17 7M8 7h9v9"/>',
        'pause' => '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
        'play' => '<path d="M7 4l13 8-13 8z"/>',
        'download' => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'plan' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
    ];
    $svg = fn ($n) => '<svg viewBox="0 0 24 24" aria-hidden="true">'.$ico[$n].'</svg>';
    // A small trend line (one point per day).
    $spark = function (array $v) {
        $n = count($v); $max = max(1, max($v)); $w = 150; $h = 36; $pts = [];
        foreach ($v as $i => $x) { $pts[] = round($n > 1 ? $i / ($n - 1) * $w : $w / 2, 1).','.round($h - 3 - ($x / $max) * ($h - 8), 1); }
        $line = implode(' ', $pts);
        return '<svg class="pk-spark" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none" aria-hidden="true"><polygon points="0,'.$h.' '.$line.' '.$w.','.$h.'" class="pk-fill"/><polyline points="'.$line.'" class="pk-line"/></svg>';
    };
    // One look for every status: an outlined pill with a dot, coloured by meaning.
    $st = fn (string $label, string $tone = 'mute') => '<span class="st st-'.$tone.'">'.e($label).'</span>';
    $statusOf = function ($r) {
        if ($r['business']->status === 'paused') { return ['Paused', 'hot']; }
        return match ($r['state']) {
            // The Plan column says what they have; this says how they are doing, so no word is said twice.
            'internal' => ['Active', 'ok'], 'trial' => ['Active', 'ok'], 'trial_expired' => ['Expired', 'hot'],
            'active' => ['Active', 'ok'], 'past_due' => ['Past due', 'hot'], 'canceled' => ['Canceled', 'mute'],
            default => [ucfirst(str_replace('_', ' ', $r['state'])), 'mute'],
        };
    };
    // When the trial ends, or the paid period ends.
    $endsOf = fn ($r) => in_array($r['state'], ['trial', 'trial_expired'], true) ? $r['business']->trial_ends_at : $r['business']->current_period_ends_at;
    $delta = fn ($now, $before) => $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    $pill = fn (?int $d) => $d === null ? '' : '<span class="pill '.($d >= 0 ? 'up' : 'down').'">'.($d >= 0 ? '▲' : '▼').' '.abs($d).'%</span>';
    $planName = fn ($b) => $b->plan === null ? __('Internal') : __((string) (config('plans.plans.'.$b->plan.'.name') ?? ucfirst($b->plan)));
    $total = max(1, $totals['businesses']);
    $share = ['paying' => (int) ($counts['paying'] ?? 0), 'trial' => (int) ($counts['trial'] ?? 0), 'attention' => (int) ($counts['attention'] ?? 0), 'paused' => (int) ($counts['paused'] ?? 0), 'internal' => (int) ($counts['internal'] ?? 0)];
@endphp
<header class="plat-bar">
    <b><span class="logo">A</span> {{ __('Platform console') }}</b>
    <span class="mute">{{ auth()->user()->email }}</span>
    @include('_guest_lang')
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn small" type="submit">{{ __('Sign out') }}</button></form>
</header>
<main class="plat-main plat-wide">
    @if (session('status'))<div class="note ok" id="flash">{{ session('status') }}</div>@endif

    <div class="pk-grid">
        <div class="pk pk-teal">
            <div class="pk-top"><span class="pk-ic">{!! $svg('building') !!}</span><span class="pk-l">{{ __('Businesses') }}</span></div>
            <b>{{ $totals['businesses'] }}</b>
            <div class="pk-sub">{{ __(':n paused', ['n' => $counts['paused'] ?? 0]) }}</div>
            <div class="pk-stack" title="{{ __('Paying, trial, needs attention, paused, internal') }}">@foreach ($share as $k => $v)@if ($v > 0)<i class="s-{{ $k }}" style="flex: {{ $v }}"></i>@endif @endforeach</div>
        </div>
        <div class="pk pk-amber">
            <div class="pk-top"><span class="pk-ic">{!! $svg('hourglass') !!}</span><span class="pk-l">{{ __('On trial') }}</span></div>
            <b>{{ $totals['trials'] }}</b>
            <div class="pk-sub">{{ __(':n need attention', ['n' => $counts['attention'] ?? 0]) }}</div>
            <div class="pk-bar"><i style="width: {{ round($totals['trials'] / $total * 100) }}%"></i></div>
        </div>
        <div class="pk pk-green">
            <div class="pk-top"><span class="pk-ic">{!! $svg('wallet') !!}</span><span class="pk-l">{{ __('Paying') }}</span></div>
            <b>{{ $totals['paying'] }}</b>
            <div class="pk-sub">{{ __(':amount a month', ['amount' => $money($totals['mrr'])]) }}</div>
            <div class="pk-bar"><i style="width: {{ round($totals['paying'] / $total * 100) }}%"></i></div>
        </div>
        <div class="pk pk-gold">
            <div class="pk-top"><span class="pk-ic">{!! $svg('coins') !!}</span><span class="pk-l">{{ __('Taken, last 30 days') }}</span></div>
            <b class="pk-money">{{ $totals['revenue30'] }}</b>
            <div class="pk-sub">{{ __(':n AI calls this month', ['n' => number_format($totals['ai'])]) }}</div>
        </div>
        <div class="pk pk-blue">
            <div class="pk-top"><span class="pk-ic">{!! $svg('chat') !!}</span><span class="pk-l">{{ __('Chats, last :n days', ['n' => $days]) }}</span></div>
            <b>{{ number_format($totals['chats']) }} {!! $pill($delta($totals['chats'], $before['chats'])) !!}</b>
            <div class="pk-sub">{{ __('vs the :n days before', ['n' => $days]) }}</div>
            {!! $spark($series['chats']) !!}
        </div>
        <div class="pk pk-violet">
            <div class="pk-top"><span class="pk-ic">{!! $svg('calendar') !!}</span><span class="pk-l">{{ __('Bookings, last :n days', ['n' => $days]) }}</span></div>
            <b>{{ number_format($totals['bookings']) }} {!! $pill($delta($totals['bookings'], $before['bookings'])) !!}</b>
            <div class="pk-sub">{{ __('vs the :n days before', ['n' => $days]) }}</div>
            {!! $spark($series['bookings']) !!}
        </div>
        <div class="pk pk-rose">
            <div class="pk-top"><span class="pk-ic">{!! $svg('bag') !!}</span><span class="pk-l">{{ __('Customers paid their businesses, last :n days', ['n' => $days]) }}</span></div>
            <b class="pk-money">{{ $totals['sales'] }}</b>
            <div class="pk-sub">{{ __('by currency, paid orders only') }}</div>
        </div>
    </div>

    <div class="page-head">
        <div class="seg" id="plat-tabs">
            <a href="#businesses" data-tab="businesses" class="on">{{ __('Businesses') }} ({{ $totals['businesses'] }})</a>
            <a href="#invites" data-tab="invites">{{ __('Invitations') }} ({{ count($invites) }})</a>
            <a href="#activity" data-tab="activity">{{ __('Activity') }}</a>
        </div>
        <div class="bar" style="gap:8px;flex-wrap:wrap">
            <div class="seg" id="plat-days">@foreach ([7, 30, 90] as $d)<a href="{{ url('/platform?days='.$d) }}" @class(['on' => $days === $d])>{{ __(':n days', ['n' => $d]) }}</a>@endforeach</div>
            <a class="ibtn" href="{{ route('platform.export', ['days' => $days]) }}" data-tip="{{ __('Export CSV') }}" aria-label="{{ __('Export CSV') }}">{!! $svg('download') !!}</a>
            <button type="button" class="ibtn" id="billing-btn" data-tip="{{ __('Billing: currency and prices') }}" aria-label="{{ __('Billing: currency and prices') }}">{!! $svg('coins') !!}</button>
            <button type="button" class="ibtn ibtn-primary" id="new-business" data-tip="{{ __('Add a business') }}" aria-label="{{ __('Add a business') }}">{!! $svg('plus') !!}</button>
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
            <span class="mute" id="biz-count"></span>
        </div>
        @error('bulk')<div class="note err">{{ $message }}</div>@enderror
        <div class="biz-bulk" id="biz-bulk" hidden>
            <b><span id="bulk-n">0</span> {{ __('selected') }}</b>
            <button type="button" class="ibtn" id="bulk-plan" data-tip="{{ __('Change plan') }}" aria-label="{{ __('Change plan') }}">{!! $svg('plan') !!}</button>
            <button type="button" class="ibtn" id="bulk-pause" data-tip="{{ __('Pause') }}" aria-label="{{ __('Pause') }}">{!! $svg('pause') !!}</button>
            <form method="post" action="{{ url('/platform/businesses/bulk') }}" id="bulk-resume" style="display:inline">@csrf<input type="hidden" name="action" value="resume"><button type="submit" class="ibtn" data-tip="{{ __('Resume') }}" aria-label="{{ __('Resume') }}">{!! $svg('play') !!}</button></form>
            <button type="button" class="ibtn" id="bulk-clear" data-tip="{{ __('Clear selection') }}" aria-label="{{ __('Clear selection') }}">{!! $svg('x') !!}</button>
        </div>
        <div class="table-wrap" style="max-height:none">
        <table class="table" id="biz-table">
            @php
            $curChoices = collect([$currency])->merge($paidCurrencies)->merge(array_keys(\App\Support\Currencies::isoAll()))->unique()->values();
            @endphp
            <thead><tr>
                <th class="col-check"><input type="checkbox" id="biz-all" aria-label="{{ __('Select all') }}"></th>
                <th aria-sort="none"><button type="button" class="sortbtn" data-sort="name" data-type="text">{{ __('Business') }}</button></th>
                <th>{{ __('Plan') }}</th>
                <th>{{ __('Status') }}</th>
                <th aria-sort="descending" class="sorted desc"><button type="button" class="sortbtn" data-sort="joined" data-type="num">{{ __('Joined') }}</button></th>
                <th aria-sort="none"><button type="button" class="sortbtn" data-sort="ends" data-type="num">{{ __('Ends') }}</button></th>
                <th class="num">{{ __('People') }}</th>
                <th class="num" aria-sort="none"><button type="button" class="sortbtn" data-sort="chats" data-type="num">{{ __('Chats') }}</button></th>
                <th class="num">{{ __('New customers') }}</th>
                <th class="num">{{ __('Bookings') }}</th>
                <th class="num">{{ __('Customer money') }}</th>
                <th class="num">{{ __('AI (month)') }}</th>
                <th class="num" aria-sort="none"><button type="button" class="sortbtn" data-sort="paid" data-type="num">{{ __('Paid to you') }}</button></th>
                <th class="num">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($rows as $r)
                @php $b = $r['business']; [$stLabel, $stTone] = $statusOf($r); $ends = $endsOf($r); @endphp
                <tr data-g="{{ $group($r) }}" data-s="{{ mb_strtolower($b->name.' '.$b->slug) }}" data-id="{{ $b->id }}"
                    data-name="{{ mb_strtolower($b->name) }}" data-plan="{{ mb_strtolower($planName($b)) }}" data-status="{{ mb_strtolower($stLabel) }}" data-ends="{{ $ends?->timestamp ?? 0 }}" data-joined="{{ $b->created_at?->timestamp }}" data-people="{{ $r['users'] }}" data-chats="{{ $r['chats'] }}"
                    data-customers="{{ $r['customers'] }}" data-bookings="{{ $r['bookings'] }}" data-money="{{ $r['sales'] }}" data-ai="{{ $r['ai'] }}" data-paid="{{ $r['revenue'] }}" @class(['is-paused' => $b->status === 'paused'])>
                    <td class="col-check"><input type="checkbox" class="biz-sel" value="{{ $b->id }}" aria-label="{{ __('Select :name', ['name' => $b->name]) }}"></td>
                    <td><a href="{{ route('platform.show', $b->id) }}"><b>{{ $b->name }}</b></a><br><span class="mute">{{ $b->slug }}</span>@if ($b->status === 'paused' && $b->paused_reason)<br><span class="reason-box" title="{{ __('Why it was paused') }}">{{ __('Reason: :why', ['why' => $b->paused_reason]) }}</span>@endif</td>
                    <td>{{ $planName($b) }}</td>
                    <td>{!! $st(__($stLabel), $stTone) !!}</td>
                    <td style="white-space:nowrap">{{ \App\Support\Lang::fmt($b->created_at, 'j M Y', 'Y年n月j日') }}</td>
                    <td style="white-space:nowrap" @if ($ends) title="{{ in_array($r['state'], ['trial', 'trial_expired'], true) ? __('Trial ends') : __('Paid until') }}" @endif>{{ $ends ? \App\Support\Lang::fmt($ends, 'j M Y', 'Y年n月j日') : '-' }}</td>
                    <td class="num">{{ $r['users'] }}</td>
                    <td class="num">{{ $r['chats'] }}</td>
                    <td class="num">{{ $r['customers'] }}</td>
                    <td class="num">{{ $r['bookings'] }}</td>
                    <td class="num" style="white-space:nowrap">{{ $r['sales'] > 0 ? $r['currency'].' '.number_format($r['sales'], 0) : '-' }}</td>
                    <td class="num">{{ $r['ai'] }}</td>
                    <td class="num paid-cell" style="white-space:nowrap" data-id="{{ $b->id }}" data-text="{{ $r['paidText'] }}" data-by="{{ json_encode($r['paidBy']) }}"><span class="paid-val">{{ $r['paidText'] }}</span>
                        <select class="paid-cur" aria-label="{{ __('Show amounts in') }}" title="{{ __('Show amounts in') }}"><option value="">{{ __('As paid') }}</option>@foreach ($curChoices as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></td>
                    <td class="num tm-actions">
                        <a class="ibtn ibtn-primary" href="{{ route('platform.show', $b->id) }}" data-tip="{{ __('Open') }}" aria-label="{{ __('Open') }}">{!! $svg('open') !!}</a>
                        <a class="ibtn" href="{{ route('chat.show', $b->slug) }}" target="_blank" rel="noopener" data-tip="{{ __('Customer chat') }}" aria-label="{{ __('Customer chat') }}">{!! $svg('chat') !!}</a>
                        @if ($b->status === 'paused')
                            <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/resume') }}" style="display:inline">@csrf<button class="ibtn ibtn-ok" type="submit" data-tip="{{ __('Resume') }}" aria-label="{{ __('Resume') }}">{!! $svg('play') !!}</button></form>
                        @else
                            <button type="button" class="ibtn ibtn-danger" data-pause data-action="{{ url('/platform/businesses/'.$b->id.'/pause') }}" data-name="{{ $b->name }}" data-tip="{{ __('Pause') }}" aria-label="{{ __('Pause') }}">{!! $svg('pause') !!}</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr id="biz-empty" hidden><td colspan="14" class="empty">{{ __('No businesses match.') }}</td></tr>
            </tbody>
        </table>
        </div>
    </section>

    <section data-panel="invites" hidden>
        <div class="bar spread" style="margin-bottom:10px">
            <span class="mute">@if ($inviteOnly){!! $st(__('Invite-only beta is ON'), 'warn') !!} {{ __('People need a code to sign up.') }}@else{!! $st(__('Sign-up is open'), 'info') !!} {!! __('Codes are ignored. Set :setting to require them.', ['setting' => '<code>SIGNUP_MODE=invite</code>']) !!}@endif</span>
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
                    <td>@if ($i->used_at){!! $st(__('Used :date', ['date' => \App\Support\Lang::fmt($i->used_at, 'j M', 'n月j日')]), 'ok') !!}@else{!! $st(__('Unused'), 'warn') !!}@endif</td>
                    <td style="text-align:right">@unless ($i->used_at)<button type="button" class="btn small" data-link="{{ $link }}">{{ __('Copy link') }}</button>@endunless</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ __('No invitations yet. Press "Make a code" to invite your first business.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>

    <section data-panel="activity" hidden>
        <p class="mute" style="margin-bottom:10px">{{ __('What you did on the platform: plan changes, pauses, support views and more. The latest 100.') }}</p>
        <div class="table-wrap" style="max-height:none">
        <table class="table">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('What') }}</th><th>{{ __('Business') }}</th><th>{{ __('Details') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody>
            @forelse ($audits as $a)
                <tr>
                    <td style="white-space:nowrap">{{ \App\Support\Lang::fmt($a->created_at, 'j M Y, H:i', 'Y年n月j日 H:i') }}</td>
                    <td><span class="tag">{{ __(\App\Support\PlatformActions::label($a->action)) }}</span></td>
                    <td>@if ($a->business)<a href="{{ route('platform.show', $a->business_id) }}">{{ $a->business->name }}</a>@else - @endif</td>
                    <td>{{ $a->details ?: '-' }}</td>
                    <td class="mute">{{ $a->user?->email ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ __('Nothing yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>
</main>

<dialog id="business-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Add a business') }}</h3>
    <p class="mute" style="margin-top:4px">{{ __('Makes the business and its first owner, on a free trial. Give the owner the email and password to sign in; they can change the password in My account.') }}</p>
    <form method="post" action="{{ url('/platform/businesses') }}">@csrf
        <label class="f" style="margin-top:12px"><span>{{ __('Business name') }}</span><input type="text" name="business_name" required minlength="2" maxlength="100"></label>
        <label class="f"><span>{{ __('Owner name') }}</span><input type="text" name="name" required maxlength="100"></label>
        <label class="f"><span>{{ __('Owner email') }}</span><input type="email" name="email" required maxlength="150"></label>
        <label class="f"><span>{{ __('Password (8 or more characters)') }}</span><input type="text" name="password" required minlength="8" maxlength="100" autocomplete="off"></label>
        <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Create business') }}</button></div>
    </form>
</dialog>

<dialog id="billing-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Billing: currency and prices') }}</h3>
    <p class="mute" style="margin-top:4px">{{ __('What your plans are charged in and what the paid plans cost. It is used for new payments, the Billing page and the sign-up page. Payments already taken keep the currency they were paid in.') }}</p>
    <form method="post" action="{{ url('/platform/settings/billing') }}">@csrf
        <label class="f" style="margin-top:12px"><span>{{ __('Currency') }}</span>
            <select name="currency" id="bill-cur">
                @foreach ($isoGroups as $group => $list)
                    <optgroup label="{{ __($group) }}">@foreach ($list as $code => [$name, $sym])<option value="{{ $code }}" @selected($currency === $code)>{{ $code }} · {{ __($name) }}</option>@endforeach</optgroup>
                @endforeach
                @unless (array_key_exists($currency, \App\Support\Currencies::isoAll()))<option value="{{ $currency }}" selected>{{ $currency }}</option>@endunless
                <option value="__other">{{ __('Other: type its 3-letter code...') }}</option>
            </select>
            <input type="text" name="currency_custom" id="bill-custom" maxlength="3" placeholder="BND" style="margin-top:8px;text-transform:uppercase" hidden>
        </label>
        @php($others = collect($paidCurrencies)->reject(fn ($c) => $c === $currency)->values())
        @if ($others->isNotEmpty())
            <p class="mute small-note" style="padding:0;margin-top:12px">{{ __('Exchange rates, only used to show the "Paid to you" column in one currency. How many :cur is 1 of each?', ['cur' => $currency]) }}</p>
            @foreach ($others as $c)
                <label class="f"><span>1 {{ $c }} = ? {{ $currency }}</span><input type="number" name="rate[{{ $c }}]" value="{{ $rates[$c] ?? '' }}" min="0" step="0.0001"></label>
            @endforeach
        @endif
        @foreach ($plans as $key => $pl)
            @if (($pl['price_cents'] ?? 0) > 0)
                <label class="f"><span>{{ __(':plan, per month', ['plan' => $pl['name']]) }}</span><input type="number" name="price[{{ $key }}]" value="{{ number_format($pl['price_cents'] / 100, 2, '.', '') }}" min="0" max="100000" step="0.01"></label>
            @endif
        @endforeach
        <p class="mute small-note" style="padding:0">{{ __('Changing the currency does not convert the prices: type the prices you want to charge in the new currency. People who already pay by card keep their current price until they change plan.') }}</p>
        <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save billing settings') }}</button></div>
    </form>
</dialog>

<dialog id="bulk-pause-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Pause the selected businesses?') }}</h3>
    <p class="mute" style="margin-top:4px">{{ __('Their customer chat and staff area are switched off until you resume them. Nothing is deleted.') }}</p>
    <form method="post" action="{{ url('/platform/businesses/bulk') }}" class="bulk-form">@csrf<input type="hidden" name="action" value="pause">
        <label class="f" style="margin-top:12px"><span>{{ __('Reason (optional, shown to you only)') }}</span><input type="text" name="reason" maxlength="150"></label>
        <div class="dlg-foot"><button class="btn danger" type="submit">{{ __('Pause them') }}</button></div>
    </form>
</dialog>

<dialog id="bulk-plan-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Change the plan of the selected businesses') }}</h3>
    <form method="post" action="{{ url('/platform/businesses/bulk') }}" class="bulk-form">@csrf<input type="hidden" name="action" value="plan">
        <label class="f" style="margin-top:12px"><span>{{ __('Plan') }}</span>
            <select name="plan" id="bp-plan">@foreach ($plans as $key => $pl)<option value="{{ $key }}">{{ $pl['name'] }}</option>@endforeach<option value="internal">{{ __('Internal (no limits, no billing)') }}</option></select></label>
        <label class="f"><span id="bp-date-label">{{ __('Trial ends / paid until') }}</span><input type="date" name="date"></label>
        <label class="f" id="bp-extend"><span>{{ __('Or extend the trial by (days)') }}</span><input type="number" name="extend_days" min="1" max="365" placeholder="14"></label>
        <p class="mute small-note" style="padding:0">{{ __('A paid plan set here is a manual plan: no payment is taken. Leave the date empty for no end.') }}</p>
        <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Apply to the selected') }}</button></div>
    </form>
</dialog>

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
  var tab = location.hash === '#activity' ? 'activity' : location.hash === '#invites' || @json(session('status') && (str_contains((string) session('status'), 'Invitation code') || str_contains((string) session('status'), '邀请码'))) ? 'invites' : 'businesses';
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
    refreshBulk(n);
  }
  q.addEventListener('input', filter);
  document.querySelectorAll('#biz-filter a').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); g = a.dataset.g; document.querySelectorAll('#biz-filter a').forEach(function (x) { x.classList.toggle('on', x === a); }); filter(); });
  });

  function dlg(id) { var d = document.getElementById(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('.dlg-x')) { d.close(); } }); return d; }
  var pd = dlg('pause-dialog'), iv = dlg('invite-dialog'), bd = dlg('business-dialog');
  var bbd = dlg('billing-dialog'), bcur = document.getElementById('bill-cur'), bcus = document.getElementById('bill-custom');
  document.getElementById('billing-btn').addEventListener('click', function () { bbd.showModal(); });
  bcur.addEventListener('change', function () { bcus.hidden = bcur.value !== '__other'; if (!bcus.hidden) { bcus.focus(); } });
  @if ($errors->has('currency')) bbd.showModal(); @endif
  document.getElementById('new-business').addEventListener('click', function () { bd.showModal(); });
  @if ($errors->any()) bd.showModal(); @endif
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

  // ---- Table: sort by any column, pick several, change them together ----
  var table = document.getElementById('biz-table'), tbody = table.tBodies[0], emptyRow = document.getElementById('biz-empty');
  var allBox = document.getElementById('biz-all'), bulkBar = document.getElementById('biz-bulk'), bulkN = document.getElementById('bulk-n');
  var T = { showing: @json(__('Showing :a of :b', ['a' => '@@A@@', 'b' => '@@B@@'])), confirmResume: @json(__('Resume the selected businesses?')) };
  function allRows() { return Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]')); }
  function picked() { return allRows().filter(function (r) { return !r.hidden && r.querySelector('.biz-sel').checked; }); }
  function refreshBulk(shown) {
    var rows = allRows(), vis = rows.filter(function (r) { return !r.hidden; });
    rows.forEach(function (r) { if (r.hidden) { r.querySelector('.biz-sel').checked = false; } });
    var sel = picked();
    bulkBar.hidden = sel.length === 0; bulkN.textContent = sel.length;
    allBox.checked = vis.length > 0 && sel.length === vis.length; allBox.indeterminate = sel.length > 0 && sel.length < vis.length;
    document.getElementById('biz-count').textContent = vis.length === rows.length ? '' : T.showing.replace('@@A@@', vis.length).replace('@@B@@', rows.length);
  }
  allRows().forEach(function (r) { r.querySelector('.biz-sel').addEventListener('change', function () { refreshBulk(); }); });
  allBox.addEventListener('change', function () { allRows().forEach(function (r) { if (!r.hidden) { r.querySelector('.biz-sel').checked = allBox.checked; } }); refreshBulk(); });
  document.getElementById('bulk-clear').addEventListener('click', function () { allRows().forEach(function (r) { r.querySelector('.biz-sel').checked = false; }); refreshBulk(); });

  // Sorting: click a heading to sort by it, click again to flip the direction. Newest first to begin with.
  var sortKey = 'joined', sortDir = -1;
  function applySort() {
    var type = table.querySelector('.sortbtn[data-sort="' + sortKey + '"]').dataset.type;
    allRows().sort(function (a, b) {
      var x = a.dataset[sortKey], y = b.dataset[sortKey];
      var c = type === 'num' ? (parseFloat(x) || 0) - (parseFloat(y) || 0) : x.localeCompare(y);
      return c * sortDir || (parseInt(b.dataset.id, 10) - parseInt(a.dataset.id, 10));
    }).forEach(function (r) { tbody.insertBefore(r, emptyRow); });
    table.querySelectorAll('thead th').forEach(function (th) {
      var btn = th.querySelector('.sortbtn'), on = btn && btn.dataset.sort === sortKey;
      th.classList.toggle('sorted', !!on); th.classList.toggle('asc', !!on && sortDir > 0); th.classList.toggle('desc', !!on && sortDir < 0);
      if (btn) { th.setAttribute('aria-sort', on ? (sortDir > 0 ? 'ascending' : 'descending') : 'none'); }
    });
  }
  table.querySelectorAll('.sortbtn').forEach(function (b) {
    b.addEventListener('click', function () {
      if (sortKey === b.dataset.sort) { sortDir = -sortDir; } else { sortKey = b.dataset.sort; sortDir = b.dataset.type === 'num' ? -1 : 1; }
      applySort();
    });
  });
  applySort();

  // Show the "Paid to you" column in a chosen currency, using the rates saved in Billing settings (1 unit = n of the plan currency).
  var RATES = @json((object) $rates), BASE = @json($currency), PICKS = {};
  RATES[BASE] = 1;
  try { PICKS = JSON.parse(sessionStorage.getItem('paidpicks') || '{}') || {}; } catch (e) { PICKS = {}; }
  function showCell(td) {
    var want = td.querySelector('.paid-cur').value, val = td.querySelector('.paid-val');
    if (!want) { val.textContent = td.dataset.text; td.title = ''; return; }
    var by = JSON.parse(td.dataset.by || '{}'), total = 0, ok = true;
    Object.keys(by).forEach(function (c) { if (c === want) { total += by[c]; } else if (RATES[c] && RATES[want]) { total += by[c] * RATES[c] / RATES[want]; } else { ok = false; } });
    val.textContent = ok ? want + ' ' + (total / 100).toFixed(2) : td.dataset.text;
    td.title = ok ? '' : '@lang('No exchange rate saved for this currency yet. Add one in Billing settings.')';
  }
  table.querySelectorAll('.paid-cell').forEach(function (td) {
    var sel = td.querySelector('.paid-cur');
    if (PICKS[td.dataset.id]) { sel.value = PICKS[td.dataset.id]; }
    sel.addEventListener('change', function () { PICKS[td.dataset.id] = sel.value; try { sessionStorage.setItem('paidpicks', JSON.stringify(PICKS)); } catch (e) {} showCell(td); });
    showCell(td);
  });

  // Bulk actions.
  function fillIds(form) {
    form.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
    picked().forEach(function (r) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = r.dataset.id; form.appendChild(i); });
  }
  var bpd = dlg('bulk-pause-dialog'), bld = dlg('bulk-plan-dialog');
  document.querySelectorAll('.bulk-form').forEach(function (f) { f.addEventListener('submit', function () { fillIds(f); }); });
  document.getElementById('bulk-pause').addEventListener('click', function () { bpd.showModal(); });
  document.getElementById('bulk-plan').addEventListener('click', function () { bld.showModal(); });
  document.getElementById('bulk-resume').addEventListener('submit', function (e) { if (!confirm(T.confirmResume)) { e.preventDefault(); return; } fillIds(e.target); });
  var bp = document.getElementById('bp-plan');
  function bpUi() { document.getElementById('bp-extend').hidden = bp.value !== 'trial'; }
  bp.addEventListener('change', bpUi); bpUi();
  refreshBulk();
})();
</script>
</body>
</html>
