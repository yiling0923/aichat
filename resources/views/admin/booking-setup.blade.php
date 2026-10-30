@extends('admin.layout')
@section('title', __('Booking setup'))
@section('content')
        <div class="page-head">
            <div>
                <h1>{{ __('Booking setup') }}</h1>
                <p class="mute">{{ __('What customers can book, who does it, and when. Google Calendar:') }}
                    <span class="tag {{ $googleOn ? 'ok' : 'warn' }}">{{ $googleOn ? __('connected') : __('not connected (using the built-in calendar)') }}</span></p>
            </div>
        </div>
        <div class="tabs bk-tabs" id="bk-tabs" role="tablist">
            <a href="#hours" data-tab="hours" class="on">{{ __('1. Opening hours and rules') }}</a>
            <a href="#people" data-tab="people">{{ __('2. Practitioners') }} <span class="bk-n">{{ $practitioners->count() }}</span></a>
            <a href="#services" data-tab="services">{{ __('3. Services') }} <span class="bk-n">{{ $services->count() }}</span></a>
            <a href="#google" data-tab="google">{{ __('4. Google Calendar') }} <span class="bk-opt">{{ __('optional') }}</span></a>
        </div>
        <section data-tab-panel="hours">
        <h2 class="bk-h" id="hours" style="margin-top:6px">{{ __('Opening hours and rules') }}</h2>
        <form method="post" action="{{ url('/admin/booking-setup/rules') }}" class="card split bk-card">
            @csrf
            <div>
            <div class="wk" id="wk">
                <div class="wk-label">{{ __('Open days') }}</div>
                <div class="wk-days" id="wk-days">@foreach ($days as $d)<button type="button" class="wk-day" data-day="{{ $d }}" aria-pressed="false">{{ ucfirst($d) }}</button>@endforeach</div>
                <div class="wk-label">{{ __('Hours on open days') }}</div>
                <div class="wk-times">
                    <label>{{ __('Opens') }} <input type="time" id="wk-open" value="{{ $hours['mon'][0] ?? '09:00' }}"></label>
                    <span class="wk-arrow">&rarr;</span>
                    <label>{{ __('Closes') }} <input type="time" id="wk-close" value="{{ $hours['mon'][1] ?? '18:00' }}"></label>
                </div>
                <div class="wk-sum" id="wk-sum" aria-live="polite"></div>
                <details class="wk-adv" id="wk-adv">
                    <summary>{{ __('One day is different? Set each day separately') }}</summary>
                    <table class="table small-table" id="hours-table">
                        <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Opens') }}</th><th>{{ __('Closes') }}</th></tr></thead>
                        @foreach ($days as $d)
                            <tr>
                                <td>{{ strtoupper($d) }}</td>
                                <td><input type="time" name="hours[{{ $d }}][open]" value="{{ $hours[$d][0] ?? '' }}"></td>
                                <td><input type="time" name="hours[{{ $d }}][close]" value="{{ $hours[$d][1] ?? '' }}"></td>
                            </tr>
                        @endforeach
                    </table>
                </details>
            </div>
            </div>
            <div>
            <div class="grid2">
                <div class="f closed-box">
                    <span>{{ __('Days you are closed (holidays)') }}</span>
                    <input type="hidden" name="closed_dates" id="closed-dates" value="{{ old('closed_dates', $closedDates) }}">
                    <div class="closed-add"><input type="date" id="closed-pick" aria-label="{{ __('Pick a date') }}"><button type="button" class="btn small" id="closed-add">{{ __('Add') }}</button></div>
                    <div class="closed-list" id="closed-list"></div>
                </div>
                <label class="f"><span>{{ __('Offer a start time every (minutes)') }}</span><input type="number" name="slot_step_min" value="{{ $rules['slot_step_min'] }}"></label>
                <label class="f"><span>{{ __('Gap after each appointment (minutes)') }}</span><input type="number" name="buffer_min" value="{{ $rules['buffer_min'] }}"></label>
                <label class="f"><span>{{ __('Earliest booking from now (hours)') }}</span><input type="number" name="min_notice_hours" value="{{ $rules['min_notice_hours'] }}"></label>
                <label class="f"><span>{{ __('Book up to (days ahead)') }}</span><input type="number" name="max_days_ahead" value="{{ $rules['max_days_ahead'] }}"></label>
                <label class="f"><span>{{ __('Customers may cancel until (hours before)') }}</span><input type="number" name="cancel_hours" value="{{ $rules['cancel_hours'] }}"></label>
            </div>
            <button class="btn primary" type="submit">{{ __('Save hours and rules') }}</button>
            </div>
        </form>

        </section>

        <section data-tab-panel="people" hidden>
        <h2 class="bk-h" id="people">{{ __('Practitioners') }}</h2>
        <p class="mute bk-sub">{{ __('One calendar per person or room. For Google Calendar, follow the steps in the Google Calendar tab, then paste each calendar ID here and press Test.') }}</p>
        <div class="card bk-list bk-people">
            <div class="bk-cols" aria-hidden="true"><span>{{ __('Name') }}</span><span>{{ __('Google Calendar ID') }}</span><span>{{ __('Active') }}</span><span></span></div>
            @foreach ($practitioners as $p)
                <form method="post" action="{{ url('/admin/booking-setup/practitioners/'.$p->id) }}" class="bk-row">
                    @csrf
                    <label class="f"><span>{{ __('Name') }}</span><input type="text" name="name" value="{{ $p->name }}"></label>
                    <label class="f"><span>{{ __('Google Calendar ID') }}</span><input type="text" name="google_calendar_id" value="{{ $p->google_calendar_id }}" placeholder="{{ __('optional') }}"></label>
                    <label class="f check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($p->active)><span>{{ __('Active') }}</span></label>
                    <span class="bk-btns"><button class="btn" type="submit">{{ __('Save') }}</button>@if ($googleOn)<button class="btn" type="submit" formaction="{{ url('/admin/booking-setup/practitioners/'.$p->id.'/google-test') }}" title="{{ __('Check that this calendar can be read and written') }}">{{ __('Test') }}</button>@endif</span>
                </form>
            @endforeach
            <details class="bk-add">
                <summary>+ {{ __('New practitioner') }}</summary>
                <form method="post" action="{{ url('/admin/booking-setup/practitioners') }}" class="bk-row bk-new">
                    @csrf
                    <label class="f"><span>{{ __('New practitioner') }}</span><input type="text" name="name" placeholder="{{ __('Name') }}"></label>
                    <label class="f"><span>{{ __('Google Calendar ID') }}</span><input type="text" name="google_calendar_id" placeholder="{{ __('optional') }}"></label>
                    <span></span>
                    <button class="btn primary" type="submit">{{ __('Add') }}</button>
                </form>
            </details>
        </div>

        </section>

        <section data-tab-panel="services" hidden>
        <h2 class="bk-h" id="services">{{ __('Services') }}</h2>
        <div class="card bk-list bk-serv">
            <div class="bk-cols" aria-hidden="true"><span>{{ __('Name (EN)') }}</span><span>{{ __('Name (中文)') }}</span><span>{{ __('Minutes') }}</span><span>{{ __('Price') }}</span><span>{{ __('Done by') }}</span><span>{{ __('Active') }}</span><span></span></div>
            @foreach ($services as $s)
                <form method="post" action="{{ url('/admin/booking-setup/services/'.$s->id) }}" class="bk-row">
                    @csrf
                    <label class="f"><span>{{ __('Name (EN)') }}</span><input type="text" name="name_en" value="{{ $s->name['en'] ?? '' }}"></label>
                    <label class="f"><span>{{ __('Name (中文)') }}</span><input type="text" name="name_zh" value="{{ $s->name['zh'] ?? '' }}"></label>
                    <label class="f"><span>{{ __('Minutes') }}</span><input type="number" name="duration_min" value="{{ $s->duration_min }}"></label>
                    <label class="f"><span>{{ __('Price') }}</span><input type="number" step="0.01" name="price" value="{{ $s->price }}"></label>
                    <div class="f"><span>{{ __('Done by') }}</span><div class="bk-who">
                        @foreach ($practitioners as $p)
                            <label class="check"><input type="checkbox" name="practitioners[]" value="{{ $p->id }}" @checked($s->practitioners->contains('id', $p->id))> {{ $p->name }}</label>
                        @endforeach
                    </div></div>
                    <label class="f check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($s->active)><span>{{ __('Active') }}</span></label>
                    <button class="btn" type="submit">{{ __('Save') }}</button>
                </form>
            @endforeach
            <details class="bk-add">
                <summary>+ {{ __('Add service') }}</summary>
                <form method="post" action="{{ url('/admin/booking-setup/services') }}" class="bk-row bk-new">
                    @csrf
                    <label class="f"><span>{{ __('New service (EN)') }}</span><input type="text" name="name_en"></label>
                    <label class="f"><span>{{ __('Name (中文)') }}</span><input type="text" name="name_zh"></label>
                    <label class="f"><span>{{ __('Minutes') }}</span><input type="number" name="duration_min" value="60"></label>
                    <label class="f"><span>{{ __('Price') }}</span><input type="number" step="0.01" name="price"></label>
                    <div class="f"><span>{{ __('Done by') }}</span><div class="bk-who">
                        @foreach ($practitioners as $p)
                            <label class="check"><input type="checkbox" name="practitioners[]" value="{{ $p->id }}" checked> {{ $p->name }}</label>
                        @endforeach
                    </div></div>
                    <span></span>
                    <button class="btn primary" type="submit">{{ __('Add service') }}</button>
                </form>
            </details>
        </div>
        </section>

        <section data-tab-panel="google" hidden>
        <h2 class="bk-h" id="google">{{ __('Google Calendar') }} <span class="tag {{ $googleOn ? 'ok' : '' }}">{{ $googleOn ? __('Key loaded') : __('Not connected') }}</span></h2>
        <p class="mute bk-sub">{{ __('Optional and free. Bookings appear in each person\'s Google Calendar, and busy times in that calendar are blocked in the chat, so nobody is double booked.') }}</p>
        <div class="card gcal">
            @if ($googleOn)
                <div class="gcal-ok">
                    <div><b>{{ __('Share each calendar with this address') }}</b>
                        <div class="copy-row" style="margin-top:6px;max-width:560px"><input type="text" readonly id="g-email" value="{{ $googleEmail ?? __('(the key file has no email)') }}"><button type="button" class="btn small" data-copy="g-email">{{ __('Copy') }}</button></div>
                        <p class="mute small-note" style="padding:0;margin-top:6px">{{ __('In Google Calendar open the calendar, Settings and sharing, Share with specific people, paste the address, and choose "Make changes to events". Then paste the Calendar ID below and press Test.') }}</p></div>
                    @if (! $googleOwnKey && $googleViaEnv)
                        <p class="mute small-note" style="padding:0">{{ __('The key file is set in the .env file (GOOGLE_SERVICE_ACCOUNT_JSON).') }}</p>
                    @elseif ($googleOwnKey)
                        <form method="post" action="{{ url('/admin/booking-setup/google-key/remove') }}" onsubmit="return confirm({{ json_encode(__('Remove the Google key? Bookings will use the built-in calendar only.'), JSON_UNESCAPED_UNICODE) }})">@csrf<button class="btn small danger" type="submit">{{ __('Remove the key') }}</button></form>
                    @endif
                </div>
            @endif
            @if (true)
                <details class="gcal-steps" @unless ($googleOn) open @endunless>
                    <summary>{{ $googleOn ? __('Replace the key file') : __('How to connect (about 10 minutes, free)') }}</summary>
                    <ol>
                        <li>{!! __('Open :link and sign in with your Google account. Create a project (any name).', ['link' => '<a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud</a>']) !!}</li>
                        <li>{!! __('Turn on the :api: in the menu choose APIs and services, Library, search for it and press Enable.', ['api' => '<b>Google Calendar API</b>']) !!}</li>
                        <li>{{ __('Create a service account: IAM and admin, Service accounts, Create. Then open it, choose Keys, Add key, Create new key, JSON. A file downloads.') }}</li>
                        <li>{{ __('Upload that file here:') }}</li>
                    </ol>
                    <form method="post" action="{{ url('/admin/booking-setup/google-key') }}" enctype="multipart/form-data" class="gcal-upload">
                        @csrf
                        <input type="file" name="key" accept=".json,application/json" required>
                        <button class="btn primary" type="submit">{{ __('Upload the key') }}</button>
                    </form>
                    <p class="mute small-note" style="padding:0">{{ __('The key is kept on this server only, never shown again, and each business uses its own. After uploading, this page shows the address to share your calendars with.') }}</p>
                </details>
            @endif
        </div>

        </section>

@endsection
@push('scripts')
<script>
(function () {
  // Tabs: remembered for this visit, so after pressing Save you come back to the same tab.
  var tabs = document.querySelectorAll('#bk-tabs a'), panels = document.querySelectorAll('[data-tab-panel]');
  function showTab(name, remember) {
    if (!document.querySelector('[data-tab-panel="' + name + '"]')) { name = 'hours'; }
    tabs.forEach(function (a) { a.classList.toggle('on', a.dataset.tab === name); });
    panels.forEach(function (p) { p.hidden = p.dataset.tabPanel !== name; });
    if (remember) { try { sessionStorage.setItem('bk-tab', name); } catch (e) { /* storage blocked: fine */ } }
  }
  tabs.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); showTab(a.dataset.tab, true); history.replaceState(null, '', '#' + a.dataset.tab); }); });
  window.addEventListener('hashchange', function () { showTab(location.hash.replace('#', ''), true); });
  var saved = ''; try { saved = sessionStorage.getItem('bk-tab') || ''; } catch (e) { /* ignore */ }
  showTab(location.hash.replace('#', '') || @json($errors->has('google') ? 'google' : '') || saved || 'hours', false);
})();
</script>
<script>
(function () {
  var wk = document.getElementById('wk'); if (!wk) { return; }
  var days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], names = { mon: @json(__('Mon')), tue: @json(__('Tue')), wed: @json(__('Wed')), thu: @json(__('Thu')), fri: @json(__('Fri')), sat: @json(__('Sat')), sun: @json(__('Sun')) }, zhMode = window.LOCALE === 'zh';
  var open = document.getElementById('wk-open'), close = document.getElementById('wk-close'), sum = document.getElementById('wk-sum');
  function inp(d, k) { return document.querySelector('input[name="hours[' + d + '][' + k + ']"]'); }
  function hrs(d) { var o = inp(d, 'open').value, c = inp(d, 'close').value; return o && c ? o + ' to ' + c : ''; }
  function isOpen(d) { return hrs(d) !== ''; }
  function setDay(d, o, c) { inp(d, 'open').value = o; inp(d, 'close').value = c; }
  function t12(t) { if (zhMode) { return t; } var p = t.split(':'), h = parseInt(p[0], 10); return ((h + 11) % 12 + 1) + (p[1] === '00' ? '' : ':' + p[1]) + (h < 12 ? 'am' : 'pm'); }
  function span(from, to) { return from === to ? names[days[from]] : @json(__(':a to :b')).replace(':a', names[days[from]]).replace(':b', names[days[to]]); }

  // Plain-language summary: groups neighbouring days that share the same hours, so an odd day stands out.
  function render() {
    days.forEach(function (d) { var b = wk.querySelector('.wk-day[data-day="' + d + '"]'); b.classList.toggle('on', isOpen(d)); b.setAttribute('aria-pressed', isOpen(d)); });
    var parts = [], i = 0;
    while (i < 7) {
      var key = isOpen(days[i]) ? hrs(days[i]) : 'closed', j = i;
      while (j + 1 < 7 && (isOpen(days[j + 1]) ? hrs(days[j + 1]) : 'closed') === key) { j++; }
      parts.push(key === 'closed' ? span(i, j) + ' ' + @json(__('closed')) : span(i, j) + ' ' + @json(__(':a to :b')).replace(':a', t12(key.split(' to ')[0])).replace(':b', t12(key.split(' to ')[1])));
      i = j + 1;
    }
    sum.textContent = parts.join('  ·  ');
    var openDays = days.filter(isOpen), same = openDays.every(function (d) { return hrs(d) === hrs(openDays[0]); });
    if (openDays.length && same) { open.value = inp(openDays[0], 'open').value; close.value = inp(openDays[0], 'close').value; }
    if (!same) { document.getElementById('wk-adv').open = true; }
  }

  wk.querySelectorAll('.wk-day').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = b.dataset.day;
      if (isOpen(d)) { setDay(d, '', ''); } else { setDay(d, open.value || '09:00', close.value || '18:00'); }
      render();
    });
  });
  function applyCommon() {
    if (!open.value || !close.value || open.value >= close.value) { sum.textContent = @json(__('Opening time must be before closing time.')); return; }
    days.filter(isOpen).forEach(function (d) { setDay(d, open.value, close.value); });
    render();
  }
  open.addEventListener('change', applyCommon); close.addEventListener('change', applyCommon);
  wk.querySelectorAll('#hours-table input').forEach(function (i) { i.addEventListener('change', render); });
  render();
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
            if (!dates.length) { list.innerHTML = ''; var none = document.createElement('span'); none.className = 'mute'; none.textContent = @json(__('None. Add a day above.')); list.appendChild(none); }
            dates.forEach(function (d) {
                var c = document.createElement('span'); c.className = 'fchip';
                c.textContent = new Date(d + 'T00:00:00').toLocaleDateString(window.LOCALE === 'zh' ? 'zh-CN' : 'en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + ' ';
                var x = document.createElement('a'); x.href = '#'; x.innerHTML = '&times;'; x.setAttribute('aria-label', @json(__('Remove')));
                x.addEventListener('click', function (e) { e.preventDefault(); dates.splice(dates.indexOf(d), 1); show(); });
                c.appendChild(x); list.appendChild(c);
            });
        }
        document.getElementById('closed-add').addEventListener('click', function () { if (pick.value) { dates.push(pick.value); pick.value = ''; show(); } });
        show();
    })();
</script>
@endpush
