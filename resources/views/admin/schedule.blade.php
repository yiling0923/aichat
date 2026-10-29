@extends('admin.layout')
@section('title', __('Schedule'))
@section('content')
    @php
        $lbl = app()->getLocale() === 'zh' ? 'zh' : 'en';
        $st = ['confirmed' => __('Confirmed'), 'completed' => __('Done'), 'no_show' => __('No-show'), 'cancelled' => __('Cancelled')];
        $thisBooking = __('This booking');
        $link = fn (array $over = []) => route('schedule', array_filter($over + ['view' => $view, 'practitioner' => $practitionerId], fn ($v) => $v !== null && $v !== ''));
    @endphp
    <div class="page-head">
        <div><h1>{{ __('Schedule') }}</h1><p class="mute">{{ __('Bookings made in the chat. Times are in :tz.', ['tz' => $business->timezone]) }}</p></div>
        <div class="seg">
            <a @class(['on' => $view === 'month']) href="{{ $link(['view' => 'month', 'date' => isset($anchor) ? $anchor->format('Y-m-d') : null]) }}">{{ __('Month') }}</a>
            <a @class(['on' => $view === 'week']) href="{{ $link(['view' => 'week', 'date' => isset($anchor) ? $anchor->format('Y-m-d') : null]) }}">{{ __('Week') }}</a>
            <a @class(['on' => $view === 'list']) href="{{ $link(['view' => 'list']) }}">{{ __('List') }}</a>
        </div>
    </div>

    <form method="get" action="{{ route('schedule') }}" class="cal-bar">
        <input type="hidden" name="view" value="{{ $view }}">
        @if ($view !== 'list')
            <input type="hidden" name="date" value="{{ $anchor->format('Y-m-d') }}">
            <a class="btn small" href="{{ $link(['date' => $prev->format('Y-m-d')]) }}" aria-label="{{ __('Previous') }}">&larr;</a>
            <a class="btn small" href="{{ $link(['date' => $today->format('Y-m-d')]) }}">{{ __('Today') }}</a>
            <a class="btn small" href="{{ $link(['date' => $next->format('Y-m-d')]) }}" aria-label="{{ __('Next') }}">&rarr;</a>
            <b class="cal-title">{{ $title }}</b>
        @else
            <a class="btn small {{ $show === 'upcoming' ? 'primary' : '' }}" href="{{ $link(['show' => 'upcoming']) }}">{{ __('Upcoming') }}</a>
            <a class="btn small {{ $show === 'past' ? 'primary' : '' }}" href="{{ $link(['show' => 'past']) }}">{{ __('Past and cancelled') }}</a>
        @endif
        <span class="grow"></span>
        <select name="practitioner" onchange="this.form.submit()" aria-label="{{ __('Practitioner') }}">
            <option value="">{{ __('Everyone') }}</option>
            @foreach ($practitioners as $p)<option value="{{ $p->id }}" @selected($practitionerId === $p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </form>

    @if ($view === 'month')
        @php $days = []; for ($d = $from->copy(); $d->lte($to); $d->addDay()) { $days[] = $d->copy(); } @endphp
        <div class="month">
            @foreach (range(0, 6) as $n)<div class="mh">{{ \Carbon\Carbon::create(2024, 1, 1 + $n)->translatedFormat('D') }}</div>@endforeach
            @foreach ($days as $d)
                @php $key = $d->format('Y-m-d'); $list = $byDay[$key] ?? []; @endphp
                <div @class(['mc', 'other' => $d->month !== $anchor->month, 'today' => $d->isSameDay($today)]) data-date="{{ $key }}">
                    <a class="dn" href="{{ $link(['view' => 'week', 'date' => $key]) }}">{{ $d->day }}</a>
                    @foreach (array_slice($list, 0, 3) as $b)
                        <button type="button" class="chip st-{{ $b->status }}" data-open="b-{{ $b->id }}" @if ($b->status === 'confirmed') draggable="true" data-id="{{ $b->id }}" data-time="{{ $b->starts_at->copy()->setTimezone($tz)->format('H:i') }}" data-convo="{{ $b->conversation_id ? 1 : 0 }}" data-label="{{ ($b->customer?->name ?: $thisBooking).' ('.$b->service->label($lbl).')' }}" @endif title="{{ $b->service->label('en') }} with {{ $b->practitioner->name }}">
                            <b>{{ $b->starts_at->copy()->setTimezone($tz)->format('H:i') }}</b> {{ $b->customer?->name ?: $b->service->label($lbl) }}
                        </button>
                    @endforeach
                    @if (count($list) > 3)<a class="more" href="{{ $link(['view' => 'week', 'date' => $key]) }}">{{ __('+:n more', ['n' => count($list) - 3]) }}</a>@endif
                </div>
            @endforeach
        </div>
    @elseif ($view === 'week')
        @php $hourH = 48; $hours = $endHour - $startHour; @endphp
        <div class="week-scroll">
            <div class="week" style="--hours: {{ $hours }}; --hh: {{ $hourH }}px">
                <div class="wh gutter"></div>
                @for ($i = 0; $i < 7; $i++)
                    @php $d = $from->copy()->addDays($i); @endphp
                    <div @class(['wh', 'today' => $d->isSameDay($today)])><span>{{ $d->translatedFormat('D') }}</span> <b>{{ $d->day }}</b></div>
                @endfor
                <div class="gutter hours">@for ($h = $startHour; $h < $endHour; $h++)<div>{{ sprintf('%02d:00', $h) }}</div>@endfor</div>
                @for ($i = 0; $i < 7; $i++)
                    @php $d = $from->copy()->addDays($i); $key = $d->format('Y-m-d'); @endphp
                    <div @class(['wd', 'today' => $d->isSameDay($today)]) data-date="{{ $key }}" data-start="{{ $startHour }}">
                        @foreach ($byDay[$key] ?? [] as $b)
                            @php
                                $s = $b->starts_at->copy()->setTimezone($tz); $e = $b->ends_at->copy()->setTimezone($tz);
                                $top = (($s->hour - $startHour) * 60 + $s->minute) / 60 * $hourH;
                                $height = max(24, $s->diffInMinutes($e) / 60 * $hourH - 2);
                                $l = $lanes[$b->id] ?? ['lane' => 0, 'of' => 1];
                            @endphp
                            <button type="button" class="ev st-{{ $b->status }}" data-open="b-{{ $b->id }}" @if ($b->status === 'confirmed') draggable="true" data-id="{{ $b->id }}" data-convo="{{ $b->conversation_id ? 1 : 0 }}" data-label="{{ ($b->customer?->name ?: $thisBooking).' ('.$b->service->label($lbl).')' }}" @endif
                                    style="top: {{ round($top, 1) }}px; height: {{ round($height, 1) }}px; left: calc({{ $l['lane'] }} * 100% / {{ $l['of'] }} + 2px); width: calc(100% / {{ $l['of'] }} - 4px)">
                                <b>{{ $s->format('H:i') }}</b> {{ $b->customer?->name ?: '-' }}<br><small>{{ $b->service->label($lbl) }} · {{ $b->practitioner->name }}</small>
                            </button>
                        @endforeach
                    </div>
                @endfor
            </div>
        </div>
    @else
        <table class="table">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Service') }}</th><th>{{ __('With') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($bookings as $b)
                <tr>
                    <td>{{ \App\Support\Lang::fmt($b->starts_at->copy()->setTimezone($business->timezone), 'D d M Y, H:i', 'Y年n月j日 D H:i') }}</td>
                    <td>{{ $b->service->label($lbl) }}<br><small class="mute">{{ __(':n min', ['n' => $b->service->duration_min]) }}</small></td>
                    <td>{{ $b->practitioner->name }}</td>
                    <td>{{ $b->customer?->name ?: '-' }}<br><small class="mute">{{ $b->customer?->phone }}</small></td>
                    <td><span class="tag {{ $b->status === 'confirmed' ? ($b->ends_at->isPast() ? 'warn' : 'ok') : 'warn' }}">{{ $b->status === 'confirmed' && $b->ends_at->isPast() ? __('Finished? Mark it') : ($st[$b->status] ?? $b->status) }}</span>
                        @if ($b->status === 'confirmed' && ! $b->google_event_id && $b->practitioner->google_calendar_id)
                            <br><small class="mute">{{ __('not in Google Calendar') }}</small>
                        @endif
                    </td>
                    <td class="actions">
                        @if ($b->status === 'confirmed')
                            @if ($b->starts_at->isPast())
                                <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="completed"><button class="btn small">{{ __('Done') }}</button></form>
                                <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="no_show"><button class="btn small">{{ __('No-show') }}</button></form>
                            @endif
                            <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/cancel') }}" onsubmit="return confirm({{ json_encode(__('Cancel this booking?'), JSON_UNESCAPED_UNICODE) }})">@csrf<button class="btn small danger">{{ __('Cancel') }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">{{ __('No bookings here yet. Book one in the test chat.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    @endif

    @if ($view !== 'list')
        <p class="legend"><span class="chip st-confirmed">{{ __('Confirmed') }}</span> <span class="chip st-completed">{{ __('Done') }}</span> <span class="chip st-no_show">{{ __('No-show') }}</span> <small class="mute hint-line">{{ __('Click a booking for details.') }} {{ $view === 'month' ? __('Drag it to another day to move it.') : __('Drag it to another time or day to move it.') }} {{ __('Cancelled ones are in List.') }}</small></p>
        @if ($bookings->isEmpty())<p class="mute" style="margin-top:12px">{{ $view === 'month' ? __('No bookings in this month.') : __('No bookings in this week.') }}</p>@endif
        @foreach ($bookings as $b)
            <template id="b-{{ $b->id }}">@include('admin._booking_detail', ['b' => $b, 'tz' => $tz])</template>
        @endforeach
        <dialog id="bk-dialog" class="dlg"><button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button><div class="dlg-body"></div></dialog>
        <dialog id="mv-dialog" class="dlg">
            <h3>{{ __('Move this booking?') }}</h3>
            <p id="mv-text" style="margin:8px 0"></p>
            <label class="chk" id="mv-notify-wrap"><input type="checkbox" id="mv-notify" checked> {{ __('Tell the customer in the chat') }}</label>
            <div class="note err" id="mv-error" hidden style="margin-top:10px"></div>
            <div class="actions" style="margin-top:12px"><button class="btn small primary" id="mv-ok" type="button">{{ __('Move it') }}</button><button class="btn small" id="mv-cancel" type="button">{{ __('Keep the old time') }}</button></div>
        </dialog>
    @endif
@endsection
@push('scripts')
<script>
    (function () {
        var dlg = document.getElementById('bk-dialog');
        if (!dlg) { return; }
        // Drag a booking to another day (month view) or another time (week view).
        var mv = document.getElementById('mv-dialog'), drag = null, grab = 0, pending = null;
        var token = document.querySelector('meta[name=csrf-token]').content;

        function pad(n) { return (n < 10 ? '0' : '') + n; }
        function ask(info, date, time) {
            pending = { id: info.id, starts_at: date + ' ' + time };
            var when = new Date(date + 'T' + time + ':00').toLocaleString(window.LOCALE === 'zh' ? 'zh-CN' : 'en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
            document.getElementById('mv-text').textContent = __t('Move :what to :when?', { what: info.label, when: when });
            document.getElementById('mv-notify-wrap').hidden = info.convo !== '1';
            document.getElementById('mv-error').hidden = true;
            document.getElementById('mv-ok').disabled = false;
            mv.showModal();
        }

        document.addEventListener('dragstart', function (e) {
            var t = e.target.closest ? e.target.closest('[draggable=true][data-id]') : null;
            if (!t) { return; }
            drag = t.dataset;
            grab = e.clientY - t.getBoundingClientRect().top;
            e.dataTransfer.setData('text/plain', t.dataset.id);
            e.dataTransfer.effectAllowed = 'move';
            t.classList.add('dragging');
        });
        document.addEventListener('dragend', function (e) {
            drag = null;
            document.querySelectorAll('.dragging, .drop-target').forEach(function (n) { n.classList.remove('dragging', 'drop-target'); });
        });
        document.addEventListener('dragover', function (e) {
            var c = drag && e.target.closest ? e.target.closest('.wd, .mc') : null;
            if (!c) { return; }
            e.preventDefault();
            document.querySelectorAll('.drop-target').forEach(function (n) { if (n !== c) { n.classList.remove('drop-target'); } });
            c.classList.add('drop-target');
        });
        document.addEventListener('drop', function (e) {
            var c = drag && e.target.closest ? e.target.closest('.wd, .mc') : null;
            if (!c) { return; }
            e.preventDefault();
            var info = drag, date = c.dataset.date, time = info.time;
            if (c.classList.contains('wd')) {
                var hh = parseFloat(getComputedStyle(c.parentNode).getPropertyValue('--hh')) || 48;
                var y = Math.max(0, e.clientY - c.getBoundingClientRect().top - grab);
                var mins = Math.round(y / hh * 60 / 15) * 15 + parseInt(c.dataset.start, 10) * 60;
                mins = Math.min(mins, 23 * 60 + 45);
                time = pad(Math.floor(mins / 60)) + ':' + pad(mins % 60);
            }
            document.querySelectorAll('.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });
            ask(info, date, time);
        });

        document.getElementById('mv-cancel').addEventListener('click', function () { mv.close(); });
        document.getElementById('mv-ok').addEventListener('click', function () {
            var btn = this, err = document.getElementById('mv-error');
            btn.disabled = true;
            fetch('{{ url('/admin/schedule') }}/' + pending.id + '/move', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ starts_at: pending.starts_at, notify: document.getElementById('mv-notify').checked && !document.getElementById('mv-notify-wrap').hidden })
            }).then(function (r) { return r.json().then(function (j) { return [r.ok, j]; }); }).then(function (res) {
                if (res[0]) { location.reload(); return; }
                err.textContent = (res[1] && (res[1].message || (res[1].errors && Object.values(res[1].errors)[0][0]))) || __t('Could not move it.');
                err.hidden = false; btn.disabled = false;
            }).catch(function () { err.textContent = __t('Could not reach the server. Try again.'); err.hidden = false; btn.disabled = false; });
        });

        // The "Change time" form in the pop-up (works on phones too).
        document.addEventListener('submit', function (e) {
            var f = e.target;
            if (f.classList && f.classList.contains('move-form')) {
                f.elements.starts_at.value = f.elements.date.value + ' ' + f.elements.time.value;
            }
        });

        document.addEventListener('click', function (e) {
            var t = e.target.closest('[data-open]');
            if (t) {
                dlg.querySelector('.dlg-body').innerHTML = document.getElementById(t.dataset.open).innerHTML;
                dlg.showModal();
            } else if (e.target === dlg || e.target.closest('.dlg-x')) {
                dlg.close();
            }
        });
    })();
</script>
@endpush
