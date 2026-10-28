{{-- Details and actions for one booking (used in the calendar pop-up). --}}
@php $local = $b->starts_at->copy()->setTimezone($tz); @endphp
<h3>{{ $b->service->label('en') }}</h3>
<p class="mute">{{ $local->format('D j M Y, H:i') }} to {{ $b->ends_at->copy()->setTimezone($tz)->format('H:i') }} ({{ $b->service->duration_min }} min)</p>
<ul class="detail">
    <li><span>With</span><b>{{ $b->practitioner->name }}</b></li>
    <li><span>Customer</span><b>@if ($b->customer)<a href="{{ route('customers.show', $b->customer->id) }}">{{ $b->customer->name ?: '-' }}</a>@else - @endif</b></li>
    @if ($b->customer?->phone)<li><span>Phone</span><b>{{ $b->customer->phone }}</b></li>@endif
    <li><span>Status</span><span class="tag {{ $b->status === 'confirmed' ? 'ok' : 'warn' }}">{{ str_replace('_', ' ', $b->status) }}</span></li>
</ul>
@if ($b->status === 'confirmed')
    <div class="move-box">
        <div class="mb-title">Change time</div>
        <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/move') }}" class="move-form">
            @csrf
            <input type="hidden" name="starts_at" value="">
            <div class="mf-row">
                <input type="date" name="date" value="{{ $local->format('Y-m-d') }}" required aria-label="Date">
                <input type="time" name="time" value="{{ $local->format('H:i') }}" step="900" required aria-label="Time">
                <button class="btn small primary" type="submit">Move</button>
            </div>
            @if ($b->conversation_id)<label class="chk"><input type="checkbox" name="notify" value="1" checked> Tell the customer in the chat</label>@endif
        </form>
    </div>
    <div class="dlg-foot">
        @if ($b->starts_at->isPast())
            <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="completed"><button class="btn small">Mark done</button></form>
            <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="no_show"><button class="btn small">No-show</button></form>
        @endif
        <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/cancel') }}" onsubmit="return confirm('Cancel this booking?')" class="push">@csrf<button class="btn small danger">Cancel booking</button></form>
    </div>
@endif
