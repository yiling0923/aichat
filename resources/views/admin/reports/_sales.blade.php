@php
    $pill = fn (?int $d) => $d === null ? '' : '<span class="pill '.($d >= 0 ? 'up' : 'down').'">'.($d >= 0 ? '▲' : '▼').' '.abs($d).'%</span>';
    $q = fn (string $section) => route('reports.export', ['section' => $section] + $query);
    $fn = $sales['funnel'];
@endphp
<div class="rp-tiles">
    <div class="tile"><small>{{ __('Money taken') }}</small><b>{{ $cur }} {{ number_format($sales['revenue'], 2) }}</b>{!! $pill($sales['revenueDelta']) !!}<span>{{ __('paid orders in this period') }}</span></div>
    <div class="tile"><small>{{ __('Paid orders') }}</small><b>{{ $sales['orders'] }}</b>{!! $pill($sales['ordersDelta']) !!}<span>{{ __('orders marked paid') }}</span></div>
    <div class="tile"><small>{{ __('Average order') }}</small><b>{{ $cur }} {{ number_format($sales['aov'], 2) }}</b>{!! $pill($sales['aovDelta']) !!}<span>{{ __('per paid order') }}</span></div>
    <div class="tile"><small>{{ __('Waiting for payment') }}</small><b>{{ $cur }} {{ number_format($sales['pendingAmount'], 2) }}</b><span>{{ __(':n orders ordered in this period', ['n' => $sales['pendingCount']]) }}</span></div>
    <div @class(['tile', 'warn' => $sales['overdueCount'] > 0])><small>{{ __('Overdue unpaid') }}</small><b>{{ $cur }} {{ number_format($sales['overdueAmount'], 2) }}</b><span>{{ __(':n orders older than :d days', ['n' => $sales['overdueCount'], 'd' => \App\Reports\SalesMarketing::OVERDUE_DAYS]) }}</span></div>
    <div class="tile"><small>{{ __('Refunded') }}</small><b>{{ $cur }} {{ number_format($sales['refunded'], 2) }}</b><span>{{ __('in this period') }}</span></div>
</div>

<div class="card rp-funnel">
    <div class="bar spread"><h3>{{ __('From first message to payment') }}</h3><a class="btn small" href="{{ $q('sales_funnel') }}">{{ __('Export CSV') }}</a></div>
    <p class="mute">{{ __('How many people reach each step. The small number is the share of the step before.') }}</p>
    @php $top = max(1, $fn[0]['n']); @endphp
    @foreach ($fn as $i => $step)
        <div class="fn-row">
            <span class="fn-l">{{ __($step['label']) }}</span>
            <div class="fn-track"><i style="width: {{ round($step['n'] / $top * 100) }}%"></i></div>
            <b>{{ $step['n'] }}</b>
            <small>{{ $i > 0 && $fn[$i - 1]['n'] > 0 ? round($step['n'] / $fn[$i - 1]['n'] * 100).'%' : '' }}</small>
        </div>
    @endforeach
</div>

<div class="card"><div class="bar spread"><h3>{{ __('Money by channel') }}</h3><a class="btn small" href="{{ $q('sales_channel') }}">{{ __('Export CSV') }}</a></div>
    <p class="mute">{{ __('Money is credited to the channel the customer first wrote from. Use the Channel filter at the top to see only WhatsApp, Messenger, Instagram or the website.') }}</p>
    @include('admin.reports._money_bars', ['rows' => $sales['byChannel'], 'cur' => $cur])</div>

<div class="rp-two">
    <div class="card"><div class="bar spread"><h3>{{ __('Money by service') }}</h3><a class="btn small" href="{{ $q('sales_service') }}">{{ __('Export CSV') }}</a></div>@include('admin.reports._money_bars', ['rows' => $sales['byService'], 'cur' => $cur])</div>
    <div class="card"><div class="bar spread"><h3>{{ __('Money by practitioner') }}</h3><a class="btn small" href="{{ $q('sales_person') }}">{{ __('Export CSV') }}</a></div>@include('admin.reports._money_bars', ['rows' => $sales['byPerson'], 'cur' => $cur])</div>
    <div class="card"><h3>{{ __('Money by week') }}</h3>@include('admin.reports._money_bars', ['rows' => $sales['byWeek'], 'cur' => $cur])</div>
    <div class="card"><h3>{{ __('Money by weekday') }}</h3>@include('admin.reports._money_bars', ['rows' => $sales['byWeekday'], 'cur' => $cur])</div>
</div>

<div class="rp-two">
    <div class="card"><h3>{{ __('New and returning customers') }}</h3>
        @php $nr = $sales['newRevenue'] + $sales['returningRevenue']; @endphp
        @if ($nr > 0)
            <div class="stack"><i style="flex: {{ max(0.01, $sales['newRevenue']) }}; background: #2563eb" title="{{ __('New customers') }}"></i><i style="flex: {{ max(0.01, $sales['returningRevenue']) }}; background: #0f766e" title="{{ __('Returning customers') }}"></i></div>
            <div class="legend-list">
                <div><i style="background:#2563eb"></i><span>{{ __('First time paying') }}</span><b>{{ $cur }} {{ number_format($sales['newRevenue'], 2) }}</b><small>{{ round($sales['newRevenue'] / $nr * 100) }}%</small></div>
                <div><i style="background:#0f766e"></i><span>{{ __('Came back and paid again') }}</span><b>{{ $cur }} {{ number_format($sales['returningRevenue'], 2) }}</b><small>{{ round($sales['returningRevenue'] / $nr * 100) }}%</small></div>
            </div>
        @else<p class="mute">{{ __('No paid orders in this period.') }}</p>@endif
    </div>
    <div class="card"><h3>{{ __('What is coming and what was lost') }}</h3>
        <div class="two-nums">
            <div><small>{{ __('Booked for the next 30 days') }}</small><b>{{ $cur }} {{ number_format($sales['bookedAhead'], 2) }}</b><span class="mute">{{ __(':n confirmed bookings (service prices)', ['n' => $sales['bookedAheadCount']]) }}</span></div>
            <div><small>{{ __('Lost to no-shows and cancellations') }}</small><b class="bad">{{ $cur }} {{ number_format($sales['lostValue'], 2) }}</b><span class="mute">{{ __(':n bookings in this period (service prices)', ['n' => $sales['lostCount']]) }}</span></div>
        </div>
    </div>
</div>

<div class="rp-two">
    <div class="card"><div class="bar spread"><h3>{{ __('Best customers') }}</h3><a class="btn small" href="{{ $q('sales_customers') }}">{{ __('Export CSV') }}</a></div>
        @if ($sales['topCustomers'])
            <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('Customer') }}</th><th class="num">{{ __('Orders') }}</th><th class="num">{{ __('Spent') }}</th></tr></thead><tbody>
                @foreach ($sales['topCustomers'] as $c)<tr><td><a href="{{ route('customers.show', $c['id']) }}">{{ $c['name'] }}</a></td><td class="num">{{ $c['orders'] }}</td><td class="num"><b>{{ $cur }} {{ number_format($c['spent'], 2) }}</b></td></tr>@endforeach
            </tbody></table></div>
        @else<p class="mute">{{ __('No paid orders in this period.') }}</p>@endif
    </div>
    <div class="card"><div class="bar spread"><h3>{{ __('Unpaid for more than :d days', ['d' => \App\Reports\SalesMarketing::OVERDUE_DAYS]) }}</h3><a class="btn small" href="{{ route('orders') }}">{{ __('Open Orders') }}</a></div>
        @if ($sales['overdue'])
            <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('Customer') }}</th><th>{{ __('For') }}</th><th class="num">{{ __('Amount') }}</th><th class="num">{{ __('Days') }}</th></tr></thead><tbody>
                @foreach ($sales['overdue'] as $o)<tr><td>{{ $o['customer'] }}</td><td class="mute">{{ \Illuminate\Support\Str::limit((string) $o['items'], 30) }}</td><td class="num">{{ $cur }} {{ number_format($o['amount'], 2) }}</td><td class="num">{{ $o['days'] }}</td></tr>@endforeach
            </tbody></table></div>
        @else<p class="mute">{{ __('Nothing is overdue. Well done.') }}</p>@endif
    </div>
</div>
<p class="mute rp-note">{{ __('Money counts orders marked paid. Service and practitioner come from the visit the order was made from; orders typed in by hand are grouped as Other. Only the period and channel filters apply on this tab.') }} <a href="{{ $q('sales_summary') }}">{{ __('Export all sales numbers') }}</a></p>
