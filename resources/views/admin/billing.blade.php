@extends('admin.layout')
@section('title', 'Plan & billing')
@section('content')
    @php
        $planName = config('plans.plans.'.($business->plan ?? 'trial').'.name', 'Internal');
        $current = $business->plan;
        $canceling = $state === 'active' && $business->subscription_status === 'canceled';
        $order = array_keys(array_filter($catalog, fn ($p, $k) => $k !== 'trial', ARRAY_FILTER_USE_BOTH));
        $price = fn ($c) => '$'.number_format($c / 100);
        $payTone = ['paid' => 'ok', 'failed' => 'hot', 'pending' => 'warn', 'refunded' => ''];
        $banner = match ($state) {
            'trial_expired' => ['hot', 'Your trial has ended. The AI is paused, but messages still reach your inbox. Choose a plan to switch it back on.'],
            'past_due' => ['hot', 'The last payment failed. The AI is paused until a payment works. Choose your plan again to retry.'],
            'canceled' => ['hot', 'Your plan has ended. Choose a plan to start again.'],
            'trial' => $daysLeft <= 3 ? ['warn', 'Your trial ends '.($daysLeft <= 0 ? 'today' : 'in '.$daysLeft.' '.($daysLeft === 1 ? 'day' : 'days')).'. Choose a plan to keep the AI running.'] : null,
            default => null,
        };
    @endphp
    <div class="page-head">
        <div><h1>Plan &amp; billing</h1><p class="mute">Your plan, what you have used this month, and your payments.</p></div>
    </div>

    @if ($testMode)<div class="note warn">Test mode: no real money is taken. Payments are pretend until a payment provider is connected at go-live.</div>@endif
    @if ($justPaid)<div class="note ok">Thank you! Your payment is being confirmed. This page updates in a few seconds.</div>@endif
    @if ($banner)<div class="note {{ $banner[0] === 'hot' ? 'err' : 'warn' }}">{{ $banner[1] }}</div>@endif

    <section class="card bill-now">
        <div class="bill-plan">
            <span class="kpi-l">Your plan</span>
            <h2>{{ $planName }}</h2>
            <div>
                @switch ($state)
                    @case ('internal') <span class="tag ok">No limits</span> @break
                    @case ('trial') <span class="tag warn">Trial: {{ $daysLeft }} {{ $daysLeft === 1 ? 'day' : 'days' }} left</span> @break
                    @case ('trial_expired') <span class="tag hot">Trial ended</span> @break
                    @case ('active') <span class="tag ok">Active</span> @break
                    @case ('past_due') <span class="tag hot">Payment failed</span> @break
                    @case ('canceled') <span class="tag hot">Ended</span> @break
                @endswitch
                @if ($canceling)<span class="tag warn">Not renewing</span>@endif
            </div>
            @if ($state === 'active' && $business->current_period_ends_at)
                <p class="mute">{{ $canceling ? 'Works until' : 'Renews' }} {{ $business->current_period_ends_at->format('j M Y') }}</p>
            @endif
            @if (in_array($business->plan, ['starter', 'growth'], true) && $business->subscription_status === 'active')
                <form method="post" action="{{ url('/admin/billing/cancel') }}" onsubmit="return confirm('Stop renewing this plan? It keeps working until the paid month ends.')">@csrf<button class="bill-link" type="submit">Cancel renewal</button></form>
            @endif
        </div>
        <div class="bill-use">
            @foreach ($usage as $kind => $u)
                @php $pct = $u['limit'] ? min(100, round($u['used'] / $u['limit'] * 100)) : 0; $hot = $u['limit'] && $pct >= 90; $warn = $u['limit'] && $pct >= 70; @endphp
                <div class="use-row">
                    <div class="bar spread"><span>{{ config('plans.labels.'.$kind) }}</span><b>{{ number_format($u['used']) }}{{ $u['limit'] === null ? ' · no limit' : ' of '.number_format($u['limit']) }}</b></div>
                    <div class="progress {{ $hot ? 'hot' : ($warn ? 'warn' : '') }}"><span style="width: {{ $u['limit'] === null ? 0 : $pct }}%"></span></div>
                    @if ($hot)<small class="mute">{{ $pct >= 100 ? 'Limit reached.' : 'Almost at the limit.' }} A bigger plan raises it.</small>@endif
                </div>
            @endforeach
        </div>
    </section>

    <div class="bill-h"><h3>Choose a plan</h3><span class="mute">Prices in US dollars per month</span></div>
    <div class="bill-plans">
        @foreach ($catalog as $key => $p)
            @continue ($key === 'trial')
            @php
                $isCurrent = $current === $key && $state === 'active';
                $idx = array_search($key, $order, true); $curIdx = array_search($current, $order, true);
                $verb = $isCurrent ? 'Current plan' : (($curIdx !== false && $state === 'active' && $idx < $curIdx) ? 'Switch to '.$p['name'] : (($curIdx !== false && $state === 'active') ? 'Upgrade to '.$p['name'] : 'Choose '.$p['name']));
            @endphp
            <form method="post" action="{{ url('/admin/billing/subscribe') }}" @class(['card', 'bill-card', 'cur' => $isCurrent, 'pop' => $key === 'starter' && ! $isCurrent])>
                @csrf <input type="hidden" name="plan" value="{{ $key }}">
                @if ($isCurrent)<span class="lp-badge">Your plan</span>@elseif ($key === 'starter')<span class="lp-badge">Most popular</span>@endif
                <h3>{{ $p['name'] }}</h3>
                <p class="amount">{{ $price($p['price_cents']) }}<small> / month</small></p>
                <ul>
                    @foreach ($p['limits'] as $kind => $limit)<li><b>{{ number_format($limit) }}</b> {{ str_replace('AI ', 'AI ', config('plans.labels.'.$kind)) }}</li>@endforeach
                </ul>
                <button class="btn {{ $isCurrent ? '' : 'primary' }} block" type="submit" @disabled($isCurrent)>{{ $verb }}</button>
            </form>
        @endforeach
    </div>
    <p class="mute small-note" style="padding:0;margin-top:10px">You can cancel renewal any time and keep the plan until the paid month ends.</p>
    <div class="bill-h"><h3>Payments</h3><span class="mute">{{ $payments->count() }} {{ $payments->count() === 1 ? 'payment' : 'payments' }}</span></div>
    <div class="table-wrap" style="max-height:none">
    <table class="table">
        <thead><tr><th>Date</th><th>Plan</th><th style="text-align:right">Amount</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr><td>{{ $p->created_at->format('j M Y, H:i') }}</td><td>{{ config('plans.plans.'.$p->plan.'.name', ucfirst((string) $p->plan)) }}</td><td style="text-align:right;white-space:nowrap">{{ $p->currency }} {{ number_format($p->amount_cents / 100, 2) }}</td><td><span class="tag {{ $payTone[$p->status] ?? '' }}">{{ ucfirst($p->status) }}</span></td></tr>
        @empty
            <tr><td colspan="4" class="empty">No payments yet. They will show here after you choose a plan.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
@endsection
