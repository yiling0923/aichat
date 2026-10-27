<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking setup: {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => 'setup'])
    <main class="page">
        <h1>Booking setup</h1>
        <p class="mute">What customers can book, who does it, and when. Google Calendar:
            <span class="tag {{ $googleOn ? 'ok' : 'warn' }}">{{ $googleOn ? 'connected' : 'not connected (using the built-in calendar)' }}</span></p>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        @foreach ($errors->all() as $error)<div class="note err">{{ $error }}</div>@endforeach

        <h2>Opening hours and rules</h2>
        <form method="post" action="{{ url('/admin/booking-setup/rules') }}" class="card">
            @csrf
            <table class="table small-table">
                <thead><tr><th>Day</th><th>Opens</th><th>Closes</th></tr></thead>
                @foreach ($days as $d)
                    <tr>
                        <td>{{ strtoupper($d) }}</td>
                        <td><input type="time" name="hours[{{ $d }}][open]" value="{{ $hours[$d][0] ?? '' }}"></td>
                        <td><input type="time" name="hours[{{ $d }}][close]" value="{{ $hours[$d][1] ?? '' }}"></td>
                    </tr>
                @endforeach
            </table>
            <p class="mute small-note">Leave a day empty to close it.</p>
            <div class="grid2">
                <label class="f"><span>Closed dates (YYYY-MM-DD, comma separated)</span><input type="text" name="closed_dates" value="{{ old('closed_dates', $closedDates) }}"></label>
                <label class="f"><span>Offer a start time every (minutes)</span><input type="number" name="slot_step_min" value="{{ $rules['slot_step_min'] }}"></label>
                <label class="f"><span>Gap after each appointment (minutes)</span><input type="number" name="buffer_min" value="{{ $rules['buffer_min'] }}"></label>
                <label class="f"><span>Earliest booking from now (hours)</span><input type="number" name="min_notice_hours" value="{{ $rules['min_notice_hours'] }}"></label>
                <label class="f"><span>Book up to (days ahead)</span><input type="number" name="max_days_ahead" value="{{ $rules['max_days_ahead'] }}"></label>
                <label class="f"><span>Customers may cancel until (hours before)</span><input type="number" name="cancel_hours" value="{{ $rules['cancel_hours'] }}"></label>
            </div>
            <button class="btn primary" type="submit">Save hours and rules</button>
        </form>

        <h2>Practitioners</h2>
        <p class="mute">One calendar per person or room. To use Google Calendar, share that calendar with the service account email (see README), then paste the calendar ID.</p>
        @foreach ($practitioners as $p)
            <form method="post" action="{{ url('/admin/booking-setup/practitioners/'.$p->id) }}" class="card row">
                @csrf
                <label class="f"><span>Name</span><input type="text" name="name" value="{{ $p->name }}"></label>
                <label class="f"><span>Google Calendar ID</span><input type="text" name="google_calendar_id" value="{{ $p->google_calendar_id }}" placeholder="optional"></label>
                <label class="f check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($p->active)><span>Active</span></label>
                <button class="btn" type="submit">Save</button>
            </form>
        @endforeach
        <form method="post" action="{{ url('/admin/booking-setup/practitioners') }}" class="card row">
            @csrf
            <label class="f"><span>New practitioner</span><input type="text" name="name" placeholder="Name"></label>
            <label class="f"><span>Google Calendar ID</span><input type="text" name="google_calendar_id" placeholder="optional"></label>
            <button class="btn primary" type="submit">Add</button>
        </form>

        <h2>Services</h2>
        @foreach ($services as $s)
            <form method="post" action="{{ url('/admin/booking-setup/services/'.$s->id) }}" class="card row">
                @csrf
                <label class="f"><span>Name (EN)</span><input type="text" name="name_en" value="{{ $s->name['en'] ?? '' }}"></label>
                <label class="f"><span>Name (中文)</span><input type="text" name="name_zh" value="{{ $s->name['zh'] ?? '' }}"></label>
                <label class="f"><span>Minutes</span><input type="number" name="duration_min" value="{{ $s->duration_min }}"></label>
                <label class="f"><span>Price</span><input type="number" step="0.01" name="price" value="{{ $s->price }}"></label>
                <div class="f"><span>Done by</span>
                    @foreach ($practitioners as $p)
                        <label class="check"><input type="checkbox" name="practitioners[]" value="{{ $p->id }}" @checked($s->practitioners->contains('id', $p->id))> {{ $p->name }}</label>
                    @endforeach
                </div>
                <label class="f check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($s->active)><span>Active</span></label>
                <button class="btn" type="submit">Save</button>
            </form>
        @endforeach
        <form method="post" action="{{ url('/admin/booking-setup/services') }}" class="card row">
            @csrf
            <label class="f"><span>New service (EN)</span><input type="text" name="name_en"></label>
            <label class="f"><span>Name (中文)</span><input type="text" name="name_zh"></label>
            <label class="f"><span>Minutes</span><input type="number" name="duration_min" value="60"></label>
            <label class="f"><span>Price</span><input type="number" step="0.01" name="price"></label>
            <div class="f"><span>Done by</span>
                @foreach ($practitioners as $p)
                    <label class="check"><input type="checkbox" name="practitioners[]" value="{{ $p->id }}" checked> {{ $p->name }}</label>
                @endforeach
            </div>
            <button class="btn primary" type="submit">Add service</button>
        </form>
    </main>
</div>
</body>
</html>
