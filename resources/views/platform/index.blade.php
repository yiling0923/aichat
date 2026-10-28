<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform console</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<main class="page" style="max-width:1100px;margin:0 auto;padding:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h1>Platform console</h1>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn small" type="submit">Sign out</button></form>
    </div>
    @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
    <div class="stats">
        <div class="stat"><b>{{ $totals['businesses'] }}</b><span>Businesses</span></div>
        <div class="stat"><b>{{ $totals['trials'] }}</b><span>On trial</span></div>
        <div class="stat"><b>{{ $totals['paying'] }}</b><span>Paying</span></div>
        <div class="stat"><b>{{ $currency }} {{ number_format($totals['mrr'] / 100) }}</b><span>Monthly recurring</span></div>
        <div class="stat"><b>{{ $currency }} {{ number_format($totals['revenue30'] / 100) }}</b><span>Taken, last 30 days</span></div>
        <div class="stat"><b>{{ $totals['ai'] }}</b><span>AI calls this month</span></div>
    </div>
    <table class="table">
        <thead><tr><th>Business</th><th>Plan</th><th>Users</th><th>Chats (30 d)</th><th>AI (month)</th><th>Revenue</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($rows as $r)
            @php $b = $r['business']; @endphp
            <tr>
                <td><b>{{ $b->name }}</b><br><span class="mute">{{ $b->slug }}</span></td>
                <td>{{ $b->plan ?? 'internal' }} <span class="tag {{ in_array($r['state'], ['active', 'internal']) ? 'ok' : (str_contains($r['state'], 'trial') ? 'warn' : 'hot') }}">{{ str_replace('_', ' ', $r['state']) }}</span></td>
                <td>{{ $r['users'] }}</td>
                <td>{{ $r['chats'] }}</td>
                <td>{{ $r['ai'] }}</td>
                <td>{{ $currency }} {{ number_format($r['revenue'] / 100, 2) }}</td>
                <td>@if ($b->status === 'paused')<span class="tag hot">paused</span> {{ $b->paused_reason }}@else <span class="tag ok">active</span>@endif</td>
                <td>
                    @if ($b->status === 'paused')
                        <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/resume') }}">@csrf<button class="btn small" type="submit">Resume</button></form>
                    @else
                        <form method="post" action="{{ url('/platform/businesses/'.$b->id.'/pause') }}" style="display:flex;gap:6px">@csrf<input type="text" name="reason" placeholder="Reason" style="width:130px;height:28px"><button class="btn small danger" type="submit">Pause</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <h2 style="margin-top:24px">Invitations @if ($inviteOnly)<span class="tag warn">invite-only beta is ON</span>@else<span class="tag">sign-up is open (SIGNUP_MODE=open)</span>@endif</h2>
    <form method="post" action="{{ url('/platform/invites') }}" class="card row">
        @csrf
        <label class="f"><span>Only for this email (optional)</span><input type="email" name="email"></label>
        <label class="f"><span>Note (optional)</span><input type="text" name="note" placeholder="Who is it for?"></label>
        <button class="btn primary" type="submit">Make a code</button>
    </form>
    <table class="table" style="margin-top:12px">
        <thead><tr><th>Code</th><th>Email</th><th>Note</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($invites as $i)
            <tr><td><code>{{ $i->code }}</code></td><td>{{ $i->email ?? 'anyone' }}</td><td>{{ $i->note }}</td><td>{!! $i->used_at ? '<span class="tag ok">used '.$i->used_at->format('j M').'</span>' : '<span class="tag warn">unused</span>' !!}</td></tr>
        @empty
            <tr><td colspan="4" class="mute empty">No invitations yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</main>
</body>
</html>
