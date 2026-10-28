@extends('admin.layout')
@section('title', 'Booking setup')
@section('content')
        <h1>Booking setup</h1>
        <p class="mute">What customers can book, who does it, and when. Google Calendar:
            <span class="tag {{ $googleOn ? 'ok' : 'warn' }}">{{ $googleOn ? 'connected' : 'not connected (using the built-in calendar)' }}</span></p>
        <h2>Opening hours and rules</h2>
        <form method="post" action="{{ url('/admin/booking-setup/rules') }}" class="card split">
            @csrf
            <div>
            <div class="hours-quick" id="hours-quick">
                <b>Same hours on several days?</b>
                <div class="hq-row"><input type="time" id="hq-open" value="{{ $hours['mon'][0] ?? '09:00' }}" aria-label="Opens"> <span class="mute">to</span> <input type="time" id="hq-close" value="{{ $hours['mon'][1] ?? '18:00' }}" aria-label="Closes"></div>
                <div class="hq-days">@foreach ($days as $d)<label class="chk"><input type="checkbox" value="{{ $d }}" @checked(in_array($d, ['mon', 'tue', 'wed', 'thu', 'fri']))> {{ ucfirst($d) }}</label>@endforeach</div>
                <div class="hq-row">
                    <button type="button" class="btn small primary" id="hq-apply">Set ticked days</button>
                    <button type="button" class="btn small" id="hq-close-days">Close ticked days</button>
                    <span class="mute hq-quick">Quick pick: <a href="#" data-pick="mon-fri">Mon to Fri</a> · <a href="#" data-pick="mon-sat">Mon to Sat</a> · <a href="#" data-pick="all">Every day</a></span>
                </div>
                <small class="mute" id="hq-msg">Then press "Save hours and rules" to keep it.</small>
            </div>
            <table class="table small-table" id="hours-table">
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
            </div>
            <div>
            <div class="grid2">
                <div class="f closed-box">
                    <span>Days you are closed (holidays)</span>
                    <input type="hidden" name="closed_dates" id="closed-dates" value="{{ old('closed_dates', $closedDates) }}">
                    <div class="closed-add"><input type="date" id="closed-pick" aria-label="Pick a date"><button type="button" class="btn small" id="closed-add">Add</button></div>
                    <div class="closed-list" id="closed-list"></div>
                </div>
                <label class="f"><span>Offer a start time every (minutes)</span><input type="number" name="slot_step_min" value="{{ $rules['slot_step_min'] }}"></label>
                <label class="f"><span>Gap after each appointment (minutes)</span><input type="number" name="buffer_min" value="{{ $rules['buffer_min'] }}"></label>
                <label class="f"><span>Earliest booking from now (hours)</span><input type="number" name="min_notice_hours" value="{{ $rules['min_notice_hours'] }}"></label>
                <label class="f"><span>Book up to (days ahead)</span><input type="number" name="max_days_ahead" value="{{ $rules['max_days_ahead'] }}"></label>
                <label class="f"><span>Customers may cancel until (hours before)</span><input type="number" name="cancel_hours" value="{{ $rules['cancel_hours'] }}"></label>
            </div>
            <button class="btn primary" type="submit">Save hours and rules</button>
            </div>
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
@endsection
@push('scripts')
<script>
(function () {
  var box = document.getElementById('hours-quick'); if (!box) { return; }
  var open = document.getElementById('hq-open'), close = document.getElementById('hq-close'), msg = document.getElementById('hq-msg');
  var checks = box.querySelectorAll('.hq-days input');
  function ticked() { return Array.prototype.filter.call(checks, function (c) { return c.checked; }).map(function (c) { return c.value; }); }
  function setDay(d, o, c) {
    var a = document.querySelector('input[name="hours[' + d + '][open]"]'), b = document.querySelector('input[name="hours[' + d + '][close]"]');
    a.value = o; b.value = c; a.closest('tr').classList.add('hq-flash'); setTimeout(function () { a.closest('tr').classList.remove('hq-flash'); }, 1200);
  }
  document.getElementById('hq-apply').addEventListener('click', function () {
    var days = ticked();
    if (!days.length) { msg.textContent = 'Tick at least one day first.'; return; }
    if (!open.value || !close.value || open.value >= close.value) { msg.textContent = 'Opening time must be before closing time.'; return; }
    days.forEach(function (d) { setDay(d, open.value, close.value); });
    msg.textContent = 'Set ' + days.length + ' day' + (days.length === 1 ? '' : 's') + '. Now press "Save hours and rules" to keep it.';
  });
  document.getElementById('hq-close-days').addEventListener('click', function () {
    var days = ticked(); if (!days.length) { msg.textContent = 'Tick at least one day first.'; return; }
    days.forEach(function (d) { setDay(d, '', ''); });
    msg.textContent = 'Closed ' + days.length + ' day' + (days.length === 1 ? '' : 's') + '. Press "Save hours and rules" to keep it.';
  });
  box.querySelectorAll('[data-pick]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var on = { 'mon-fri': ['mon', 'tue', 'wed', 'thu', 'fri'], 'mon-sat': ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'], 'all': ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] }[a.dataset.pick];
      checks.forEach(function (c) { c.checked = on.indexOf(c.value) > -1; });
    });
  });
})();
</script>
<script>
    (function () {
        var hidden = document.getElementById('closed-dates'), list = document.getElementById('closed-list'), pick = document.getElementById('closed-pick');
        var dates = hidden.value.split(',').map(function (d) { return d.trim(); }).filter(function (d) { return /^\d{4}-\d{2}-\d{2}$/.test(d); });
        function show() {
            dates = Array.from(new Set(dates)).sort();
            hidden.value = dates.join(', ');
            list.innerHTML = '';
            if (!dates.length) { list.innerHTML = '<span class="mute">None. Add a day above.</span>'; }
            dates.forEach(function (d) {
                var c = document.createElement('span'); c.className = 'fchip';
                c.textContent = new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + ' ';
                var x = document.createElement('a'); x.href = '#'; x.innerHTML = '&times;'; x.setAttribute('aria-label', 'Remove');
                x.addEventListener('click', function (e) { e.preventDefault(); dates.splice(dates.indexOf(d), 1); show(); });
                c.appendChild(x); list.appendChild(c);
            });
        }
        document.getElementById('closed-add').addEventListener('click', function () { if (pick.value) { dates.push(pick.value); pick.value = ''; show(); } });
        show();
    })();
</script>
@endpush
