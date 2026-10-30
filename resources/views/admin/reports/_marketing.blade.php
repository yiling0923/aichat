@php
    $q = fn (string $section) => route('reports.export', ['section' => $section] + $query);
    $wb = $marketing['winBack'];
@endphp
<div class="rp-tiles">
    <div class="tile"><small>{{ __('People you can message') }}</small><b>{{ $marketing['contactable'] }}</b><span>{{ __('of :n customers agreed to hear from you', ['n' => $marketing['customersTotal']]) }}</span></div>
    <div class="tile"><small>{{ __('New opt-ins') }}</small><b>{{ $marketing['newOptIns'] }}</b><span>{{ __('agreed in this period') }}</span></div>
    <div class="tile"><small>{{ __('Opted out') }}</small><b>{{ $marketing['optOuts'] }}</b><span>{{ __('sent STOP in this period') }}</span></div>
    <div class="tile"><small>{{ __('Survey answers') }}</small><b>{{ $marketing['surveyRate'] === null ? '-' : $marketing['surveyRate'].'%' }}</b><span>{{ __(':a of :b answered', ['a' => $marketing['surveyAnswered'], 'b' => $marketing['surveyAsked']]) }}</span></div>
    <div class="tile"><small>{{ __('Happy customers') }}</small><b>{{ $marketing['happy'] }}</b><span>{{ __('rated 4 or 5, :n unhappy (1 or 2)', ['n' => $marketing['unhappy']]) }}</span></div>
    <div class="tile"><small>{{ __('Review links sent') }}</small><b>{{ $marketing['reviewLinks'] }}</b><span>{{ $marketing['avgRating'] === null ? __('no ratings yet') : __('average rating :n / 5', ['n' => $marketing['avgRating']]) }}</span></div>
</div>

<div class="card">
    <div class="bar spread"><h3>{{ __('Where customers come from') }}</h3><a class="btn small" href="{{ $q('mk_channels') }}">{{ __('Export CSV') }}</a></div>
    @if ($marketing['channels'])
        <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('Channel') }}</th><th class="num">{{ __('Chats') }}</th><th class="num">{{ __('Left their details') }}</th><th class="num">{{ __('Bookings') }}</th><th>{{ __('Chats that became a booking') }}</th></tr></thead><tbody>
            @foreach ($marketing['channels'] as $c)<tr><td><b>{{ __($c['label']) }}</b></td><td class="num">{{ $c['chats'] }}</td><td class="num">{{ $c['leads'] }}</td><td class="num">{{ $c['bookings'] }}</td><td><div class="mini-track"><i style="width: {{ min(100, $c['rate']) }}%"></i></div> <b>{{ $c['rate'] }}%</b></td></tr>@endforeach
        </tbody></table></div>
    @else<p class="mute">{{ __('No chats in this period.') }}</p>@endif
</div>

<div class="rp-two">
    <div class="card"><div class="bar spread"><h3>{{ __('What customers ask about') }}</h3><a class="btn small" href="{{ $q('mk_topics') }}">{{ __('Export CSV') }}</a></div><p class="mute">{{ __('From the automatic chat tags. Use it to decide what to promote and what to explain better.') }}</p>@include('admin._bars', ['rows' => $marketing['topics'], 'rank' => true])</div>
    <div class="card"><h3>{{ __('When customers write') }}</h3><p class="mute">{{ __('Chats started by weekday, and the busiest hours. Good times to be online and to send messages.') }}</p>@include('admin._bars', ['rows' => $marketing['weekdays']])<div style="height:10px"></div>@include('admin._bars', ['rows' => $marketing['busyHours']])</div>
    <div class="card"><h3>{{ __('Customers by language') }}</h3>@include('admin._bars', ['rows' => $marketing['languages']])</div>
    <div class="card"><h3>{{ __('Customers by tag') }}</h3>@include('admin._bars', ['rows' => $marketing['customerTags'], 'rank' => true])</div>
</div>

<div class="card">
    <div class="bar spread"><h3>{{ __('Did your broadcasts bring bookings?') }}</h3><a class="btn small" href="{{ $q('mk_broadcasts') }}">{{ __('Export CSV') }}</a></div>
    <p class="mute">{{ __('Counts people who booked, and what they paid, within 7 days after the message. It shows a link, not proof that the message caused it.') }}</p>
    @if ($marketing['broadcasts'])
        <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('Broadcast') }}</th><th>{{ __('Sent') }}</th><th class="num">{{ __('Delivered') }}</th><th class="num">{{ __('Skipped') }}</th><th class="num">{{ __('Failed') }}</th><th class="num">{{ __('Booked in 7 days') }}</th><th class="num">{{ __('Paid in 7 days') }}</th></tr></thead><tbody>
            @foreach ($marketing['broadcasts'] as $b)<tr><td><a href="{{ route('broadcasts.show', $b['id']) }}">{{ $b['name'] }}</a></td><td>{{ \App\Support\Lang::fmt($b['sent_at']->copy()->setTimezone($r->timezone()), 'j M', 'n月j日') }}</td><td class="num">{{ $b['sent'] }} <small class="mute">{{ $b['delivery'] }}%</small></td><td class="num">{{ $b['skipped'] }}</td><td class="num">{{ $b['failed'] }}</td><td class="num"><b>{{ $b['booked'] }}</b></td><td class="num">{{ $cur }} {{ number_format($b['revenue'], 2) }}</td></tr>@endforeach
        </tbody></table></div>
    @else<p class="mute">{{ __('No broadcasts were sent in this period.') }} <a href="{{ route('broadcasts') }}">{{ __('Open Broadcasts') }}</a></p>@endif
</div>

<div class="card">
    <div class="bar spread"><h3>{{ __('Automatic messages') }}</h3><a class="btn small" href="{{ $q('mk_automations') }}">{{ __('Export CSV') }}</a></div>
    @if ($marketing['automations'])
        <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('Message') }}</th><th class="num">{{ __('Sent') }}</th><th class="num">{{ __('Skipped') }}</th><th class="num">{{ __('Failed') }}</th></tr></thead><tbody>
            @foreach ($marketing['automations'] as $a)<tr><td>{{ __($a['label']) }}</td><td class="num"><b>{{ $a['sent'] }}</b></td><td class="num">{{ $a['skipped'] }}</td><td class="num">{{ $a['failed'] }}</td></tr>@endforeach
        </tbody></table></div>
    @else<p class="mute">{{ __('No automatic messages in this period.') }} <a href="{{ route('automations') }}">{{ __('Open Automations') }}</a></p>@endif
</div>

<div class="card">
    <div class="bar spread"><h3>{{ __('Customers to win back') }}</h3><a class="btn small" href="{{ $q('mk_winback') }}">{{ __('Export CSV') }}</a></div>
    <p class="mute">{{ __('Customers who booked before but not in the last :d days, with nothing booked now, best spenders first.', ['d' => \App\Reports\SalesMarketing::WINBACK_DAYS]) }}</p>
    <div class="two-nums" style="margin-bottom:12px">
        <div><small>{{ __('To win back') }}</small><b>{{ $wb['count'] }}</b></div>
        <div><small>{{ __('You may message') }}</small><b>{{ $wb['reachable'] }}</b><span class="mute">{{ __('agreed and did not opt out') }}</span></div>
    </div>
    @if ($wb['list'])
        <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Last visit') }}</th><th class="num">{{ __('Visits') }}</th><th class="num">{{ __('Spent') }}</th><th></th></tr></thead><tbody>
            @foreach ($wb['list'] as $c)<tr><td><a href="{{ route('customers.show', $c['id']) }}">{{ $c['name'] }}</a> <small class="mute">{{ $c['phone'] }}</small></td><td>{{ \App\Support\Lang::fmt($c['last'], 'j M Y', 'Y年n月j日') }}</td><td class="num">{{ $c['visits'] }}</td><td class="num">{{ $cur }} {{ number_format($c['spent'], 2) }}</td><td>@unless ($c['reachable'])<span class="tag">{{ __('not agreed') }}</span>@endunless</td></tr>@endforeach
        </tbody></table></div>
        <p class="mute rp-note"><a class="btn small primary" href="{{ route('broadcasts') }}">{{ __('Write a message to them in Broadcasts') }}</a></p>
    @else<p class="mute">{{ __('Nobody to win back right now.') }}</p>@endif
</div>
<p class="mute rp-note">{{ __('Only the period filter applies on this tab, except customers by language and tag, the list size and win-back, which show everyone.') }}</p>
