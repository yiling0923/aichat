<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule: {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => 'schedule'])
    <main class="page">
        <h1>Schedule</h1>
        <p class="mute">Bookings made in the chat. Times are in {{ $business->timezone }}.</p>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        <div class="bar">
            <a class="btn {{ $show === 'upcoming' ? 'primary' : '' }}" href="{{ route('schedule') }}">Upcoming</a>
            <a class="btn {{ $show === 'past' ? 'primary' : '' }}" href="{{ route('schedule', ['show' => 'past']) }}">Past and cancelled</a>
        </div>
        <table class="table">
            <thead><tr><th>When</th><th>Service</th><th>With</th><th>Customer</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($bookings as $b)
                <tr>
                    <td>{{ $b->starts_at->copy()->setTimezone($business->timezone)->format('D d M Y, H:i') }}</td>
                    <td>{{ $b->service->label('en') }}<br><small class="mute">{{ $b->service->duration_min }} min</small></td>
                    <td>{{ $b->practitioner->name }}</td>
                    <td>{{ $b->customer?->name ?: '-' }}<br><small class="mute">{{ $b->customer?->phone }}</small></td>
                    <td><span class="tag {{ $b->status === 'confirmed' ? 'ok' : 'warn' }}">{{ str_replace('_', ' ', $b->status) }}</span>
                        @if ($b->status === 'confirmed' && ! $b->google_event_id && $b->practitioner->google_calendar_id)
                            <br><small class="mute">not in Google Calendar</small>
                        @endif
                    </td>
                    <td class="actions">
                        @if ($b->status === 'confirmed')
                            @if ($b->starts_at->isPast())
                                <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="completed"><button class="btn small">Done</button></form>
                                <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="no_show"><button class="btn small">No-show</button></form>
                            @endif
                            <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/cancel') }}" onsubmit="return confirm('Cancel this booking?')">@csrf<button class="btn small danger">Cancel</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No bookings here yet. Book one in the test chat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </main>
</div>
</body>
</html>
