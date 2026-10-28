@extends('admin.layout')
@section('title', $broadcast->name)
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'broadcasts'])
    <p><a href="{{ route('broadcasts') }}">&larr; Broadcasts</a></p>
    <h1>{{ $broadcast->name }}</h1>
    <p class="mute">{{ $broadcast->sent_at?->copy()->setTimezone($business->timezone)->format('d M Y H:i') }}</p>
    <div class="grid3">
        <div class="stat"><b>{{ $broadcast->sent_count }}</b><span>sent</span></div>
        <div class="stat"><b>{{ $broadcast->skipped_count }}</b><span>skipped</span></div>
        <div class="stat"><b>{{ $broadcast->failed_count }}</b><span>failed</span></div>
    </div>
    <div class="card"><h3>Message</h3><p style="white-space:pre-wrap">{{ $broadcast->message['en'] ?? '' }}</p>@if (! empty($broadcast->message['zh']))<p style="white-space:pre-wrap;margin-top:8px">{{ $broadcast->message['zh'] }}</p>@endif</div>
    <table class="table">
        <thead><tr><th>Customer</th><th>Result</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse ($recipients as $r)
            <tr>
                <td>@if ($r->customer)<a href="{{ route('customers.show', $r->customer_id) }}">{{ $r->customer->name ?: $r->customer->phone }}</a>@endif</td>
                <td><span class="tag {{ $r->status === 'sent' ? 'ok' : ($r->status === 'failed' ? 'hot' : 'warn') }}">{{ $r->status }}</span></td>
                <td class="mute">{{ $r->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="empty">No recipients.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
