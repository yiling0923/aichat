@extends('admin.layout')
@section('title', 'Schedule')
@section('content')
    @php
        $link = fn (array $over = []) => route('schedule', array_filter(['view' => $view, 'practitioner' => $practitionerId] + $over, fn ($v) => $v !== null && $v !== ''));
    @endphp
    <div class="page-head">
        <div><h1>Schedule</h1><p class="mute">Bookings made in the chat. Times are in {{ $business->timezone }}.</p></div>
        <div class="seg">
            <a @class(['on' => $view === 'month']) href="{{ $link(['view' => 'month', 'date' => isset($anchor) ? $anchor->format('Y-m-d') : null]) }}">Month</a>
            <a @class(['on' => $view === 'week']) href="{{ $link(['view' => 'week', 'date' => isset($anchor) ? $anchor->format('Y-m-d') : null]) }}">Week</a>
            <a @class(['on' => $view === 'list']) href="{{ $link(['view' => 'list']) }}">List</a>
        </div>
    </div>

    <form method="get" action="{{ route('schedule') }}" class="cal-bar">
        <input type="hidden" name="view" value="{{ $view }}">
        @if ($view !== 'list')
            <input type="hidden" name="date" value="{{ $anchor->format('Y-m-d') }}">
            <a class="btn small" href="{{ $link(['date' => $prev->format('Y-m-d')]) }}" aria-label="Previous">&larr;</a>
            <a class="btn small" href="{{ $link(['date' => $today->format('Y-m-d')]) }}">Today</a>
            <a class="btn small" href="{{ $link(['date' => $next->format('Y-m-d')]) }}" aria-label="Next">&rarr;</a>
            <b class="cal-title">{{ $title }}</b>
        @else
            <a class="btn small {{ $show === 'upcoming' ? 'primary' : '' }}" href="{{ $link(['show' => 'upcoming']) }}">Upcoming</a>
            <a class="btn small {{ $show === 'past' ? 'primary' : '' }}" href="{{ $link(['show' => 'past']) }}">Past and cancelled</a>
        @endif
        <span class="grow"></span>
        <select name="practitioner" onchange="this.form.submit()" aria-label="Practitioner">
            <option value="">Everyone</option>
            @foreach ($practitioners as $p)<option value="{{ $p->id }}" @selected($practitionerId === $p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </form>

    @if ($view === 'month')
        @php $days = []; for ($d = $from->copy(); $d->lte($to); $d->addDay()) { $days[] = $d->copy(); } @endphp
        <div class="month">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $n)<div class="mh">{{ $n }}</div>@endforeach
            @foreach ($days as $d)
                @php $key = $d->format('Y-m-d'); $list = $byDay[$key] ?? []; @endphp
                <div @class(['mc', 'other' => $d->month !== $anchor->month, 'today' => $d->isSameDay($today)])>
                    <a class="dn" href="{{ $link(['view' => 'week', 'date' => $key]) }}">{{ $d->day }}</a>
                    @foreach (array_slice($list, 0, 3) as $b)
                        <button type="button" class="chip st-{{ $b->status }}" data-open="b-{{ $b->id }}" title="{{ $b->service->label('en') }} with {{ $b->practitioner->name }}">
                            <b>{{ $b->starts_at->copy()->setTimezone($tz)->format('H:i') }}</b> {{ $b->customer?->name ?: $b->service->label('en') }}
                        </button>
                    @endforeach
                    @if (count($list) > 3)<a class="more" href="{{ $link(['view' => 'week', 'date' => $key]) }}">+{{ count($list) - 3 }} more</a>@endif
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
                    <div @class(['wh', 'today' => $d->isSameDay($today)])><span>{{ $d->format('D') }}</span> <b>{{ $d->day }}</b></div>
                @endfor
                <div class="gutter hours">@for ($h = $startHour; $h < $endHour; $h++)<div>{{ sprintf('%02d:00', $h) }}</div>@endfor</div>
                @for ($i = 0; $i < 7; $i++)
                    @php $d = $from->copy()->addDays($i); $key = $d->format('Y-m-d'); @endphp
                    <div @class(['wd', 'today' => $d->isSameDay($today)])>
                        @foreach ($byDay[$key] ?? [] as $b)
                            @php
                                $s = $b->starts_at->copy()->setTimezone($tz); $e = $b->ends_at->copy()->setTimezone($tz);
                                $top = (($s->hour - $startHour) * 60 + $s->minute) / 60 * $hourH;
                                $height = max(24, $s->diffInMinutes($e) / 60 * $hourH - 2);
                                $l = $lanes[$b->id] ?? ['lane' => 0, 'of' => 1];
                            @endphp
                            <button type="button" class="ev st-{{ $b->status }}" data-open="b-{{ $b->id }}"
                                    style="top: {{ round($top, 1) }}px; height: {{ round($height, 1) }}px; left: calc({{ $l['lane'] }} * 100% / {{ $l['of'] }} + 2px); width: calc(100% / {{ $l['of'] }} - 4px)">
                                <b>{{ $s->format('H:i') }}</b> {{ $b->customer?->name ?: '-' }}<br><small>{{ $b->service->label('en') }} · {{ $b->practitioner->name }}</small>
                            </button>
                        @endforeach
                    </div>
                @endfor
            </div>
        </div>
    @else
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
    @endif

    @if ($view !== 'list')
        <p class="legend"><span class="chip st-confirmed">Confirmed</span> <span class="chip st-completed">Done</span> <span class="chip st-no_show">No-show</span> <small class="mute">Click a booking to see details, mark it done or cancel it. Cancelled bookings are hidden here (see List).</small></p>
        @if ($bookings->isEmpty())<p class="mute" style="margin-top:12px">No bookings in this {{ $view }}.</p>@endif
        @foreach ($bookings as $b)
            <template id="b-{{ $b->id }}">@include('admin._booking_detail', ['b' => $b, 'tz' => $tz])</template>
        @endforeach
        <dialog id="bk-dialog" class="dlg"><div class="dlg-body"></div><form method="dialog"><button class="btn small">Close</button></form></dialog>
    @endif
@endsection
@push('scripts')
<script>
    (function () {
        var dlg = document.getElementById('bk-dialog');
        if (!dlg) { return; }
        document.addEventListener('click', function (e) {
            var t = e.target.closest('[data-open]');
            if (t) {
                dlg.querySelector('.dlg-body').innerHTML = document.getElementById(t.dataset.open).innerHTML;
                dlg.showModal();
            } else if (e.target === dlg) {
                dlg.close();
            }
        });
    })();
</script>
@endpush
