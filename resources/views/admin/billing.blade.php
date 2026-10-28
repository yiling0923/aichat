@extends('admin.layout')
@section('title', 'Plan & billing')
@section('content')
    <h1>Plan &amp; billing</h1>
    @if ($testMode)<div class="note warn">Test mode: no real money is taken. Payments are pretend until a payment provider is connected at go-live.</div>@endif

    <div class="card">
        <b>{{ config('plans.plans.'.($business->plan ?? 'trial').'.name', 'Internal') }}</b>
        @switch ($state)
            @case ('internal') <span class="tag ok">No limits</span> @break
            @case ('trial') <span class="tag warn">Trial: {{ $daysLeft }} day(s) left</span> @break
            @case ('trial_expired') <span class="tag hot">Trial ended</span> <span class="mute">The AI is paused. Messages still reach your inbox.</span> @break
            @case ('active') <span class="tag ok">Active</span> @if ($business->current_period_ends_at)<span class="mute">Renews {{ $business->current_period_ends_at->format('j M Y') }}</span>@endif @break
            @case ('past_due') <span class="tag hot">Payment failed</span> <span class="mute">The AI is paused until a payment works.</span> @break
            @case ('canceled') <span class="tag hot">Ended</span> @break
        @endswitch
        @if ($state === 'active' && $business->subscription_status === 'canceled')<span class="tag warn">Not renewing</span>@endif
    </div>

    <h2>Usage</h2>
    <div class="stats">
        @foreach ($usage as $kind => $u)
            <div class="stat"><b>{{ $u['used'] }}{{ $u['limit'] === null ? '' : ' / '.$u['limit'] }}</b><span>{{ config('plans.labels.'.$kind) }}</span></div>
        @endforeach
    </div>

    <h2>Plans</h2>
    <div class="grid3">
        @foreach ($catalog as $key => $p)
            @continue ($key === 'trial')
            <form method="post" action="{{ url('/admin/billing/subscribe') }}" class="card">
                @csrf <input type="hidden" name="plan" value="{{ $key }}">
                <h3>{{ $p['name'] }}</h3>
                <p><b style="font-size:22px">${{ number_format($p['price_cents'] / 100) }}</b> <span class="mute">/ month</span></p>
                <ul>
                    @foreach ($p['limits'] as $kind => $limit)<li>{{ config('plans.labels.'.$kind) }}<b>{{ $limit }}</b></li>@endforeach
                </ul>
                <button class="btn primary" type="submit" @disabled($business->plan === $key && $state === 'active')>{{ $business->plan === $key && $state === 'active' ? 'Current plan' : 'Choose '.$p['name'] }}</button>
            </form>
        @endforeach
    </div>
    @if (in_array($business->plan, ['starter', 'growth'], true) && $business->subscription_status === 'active')
        <form method="post" action="{{ url('/admin/billing/cancel') }}" onsubmit="return confirm('Stop renewing this plan?')">@csrf<button class="btn danger small" type="submit">Cancel renewal</button></form>
    @endif

    <h2>Payments</h2>
    <table class="table">
        <thead><tr><th>Date</th><th>Plan</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr><td>{{ $p->created_at->format('j M Y H:i') }}</td><td>{{ $p->plan }}</td><td>{{ $p->currency }} {{ number_format($p->amount_cents / 100, 2) }}</td><td>{{ $p->status }}</td></tr>
        @empty
            <tr><td colspan="4" class="mute empty">No payments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
