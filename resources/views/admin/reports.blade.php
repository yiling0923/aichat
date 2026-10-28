@extends('admin.layout')
@section('title', 'Reports')
@section('content')
    <div class="bar spread">
        <div><h1>Reports</h1><p class="mute">The last {{ $days }} days.</p></div>
        <div class="bar">
            @foreach ([7, 30, 90] as $d)<a class="btn {{ $days === $d ? 'primary' : '' }}" href="{{ route('reports', ['days' => $d]) }}">{{ $d }} days</a>@endforeach
        </div>
    </div>

    <div class="grid3">
        <div class="stat"><b>{{ $chatsTotal }}</b><span>chats started</span></div>
        <div class="stat"><b>{{ $newCustomers }}</b><span>new customers</span></div>
        <div class="stat"><b>{{ $bookingsByStatus->sum() }}</b><span>bookings made</span></div>
        <div class="stat"><b>{{ $handovers }}</b><span>handed over to staff</span></div>
        <div class="stat"><b>{{ $avgWait === null ? '-' : $avgWait.' min' }}</b><span>average wait for a staff reply</span></div>
        <div class="stat"><b>{{ $surveyAvg === null ? '-' : number_format($surveyAvg, 1).' / 5' }}</b><span>survey score ({{ $surveyCount }} answers)</span></div>
        <div class="stat"><b>{{ number_format($revenue, 2) }}</b><span>paid orders</span></div>
    </div>

    <div class="cols">
        @php
            $bars = [
                ['Chats by channel', $byChannel->map(fn ($n, $k) => ['label' => ucfirst($k), 'n' => $n])->values()],
                ['New customers by score', collect($leadsByScore)->map(fn ($n, $k) => ['label' => $k ?: 'no score', 'n' => $n])->values()],
                ['Bookings by status', collect($bookingsByStatus)->map(fn ($n, $k) => ['label' => str_replace('_', ' ', $k), 'n' => $n])->values()],
                ['Top booked services', $topServices],
                ['Most tapped buttons (top questions)', $topTaps],
            ];
        @endphp
        @foreach ($bars as [$title, $rows])
            @php $max = max(1, collect($rows)->max('n') ?? 1); @endphp
            <div class="card">
                <h3>{{ $title }}</h3>
                @forelse ($rows as $r)
                    <div style="margin:8px 0">
                        <div style="display:flex;justify-content:space-between;font-size:13px"><span>{{ $r['label'] }}</span><b>{{ $r['n'] }}</b></div>
                        <div style="background:var(--accent-l);border-radius:6px;height:8px"><div style="background:var(--accent);height:8px;border-radius:6px;width:{{ round($r['n'] / $max * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="mute">No data yet.</p>
                @endforelse
            </div>
        @endforeach
        <div class="card">
            <h3>Broadcasts sent</h3>
            @forelse ($broadcasts as $b)
                <ul><li><a href="{{ route('broadcasts.show', $b->id) }}">{{ $b->name }}</a><span class="mute">{{ $b->sent_count }} sent, {{ $b->skipped_count }} skipped</span></li></ul>
            @empty
                <p class="mute">None in this period.</p>
            @endforelse
        </div>
    </div>
@endsection
