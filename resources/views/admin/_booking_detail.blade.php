{{-- Details and actions for one booking (used in the calendar pop-up). --}}
@php $local = $b->starts_at->copy()->setTimezone($tz); $lbl = app()->getLocale() === 'zh' ? 'zh' : 'en'; $st = ['confirmed' => __('Confirmed'), 'completed' => __('Done'), 'no_show' => __('No-show'), 'cancelled' => __('Cancelled')]; @endphp
<h3>{{ $b->service->label($lbl) }}</h3>
<p class="mute">{{ __(':start to :end (:n min)', ['start' => \App\Support\Lang::fmt($local, 'D j M Y, H:i', 'Y年n月j日 D H:i'), 'end' => $b->ends_at->copy()->setTimezone($tz)->format('H:i'), 'n' => $b->service->duration_min]) }}</p>
<ul class="detail">
    <li><span>{{ __('With') }}</span><b>{{ $b->practitioner->name }}</b></li>
    <li><span>{{ __('Customer') }}</span><b>@if ($b->customer)<a href="{{ route('customers.show', $b->customer->id) }}">{{ $b->customer->name ?: '-' }}</a>@else - @endif</b></li>
    @if ($b->customer?->phone)<li><span>{{ __('Phone') }}</span><b>{{ $b->customer->phone }}</b></li>@endif
    <li><span>{{ __('Status') }}</span><span class="tag {{ $b->status === 'confirmed' ? 'ok' : 'warn' }}">{{ $st[$b->status] ?? $b->status }}</span></li>
</ul>
@if ($b->status === 'confirmed')
    <div class="move-box">
        <div class="mb-title">{{ __('Change time') }}</div>
        <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/move') }}" class="move-form">
            @csrf
            <input type="hidden" name="starts_at" value="">
            <div class="mf-row">
                <input type="date" name="date" value="{{ $local->format('Y-m-d') }}" required aria-label="{{ __('Date') }}">
                <input type="time" name="time" value="{{ $local->format('H:i') }}" step="900" required aria-label="{{ __('Time') }}">
                <button class="btn small primary" type="submit">{{ __('Move') }}</button>
            </div>
            @if ($b->conversation_id)<label class="chk"><input type="checkbox" name="notify" value="1" checked> {{ __('Tell the customer in the chat') }}</label>@endif
        </form>
    </div>
    <div class="dlg-foot">
        @if ($b->starts_at->isPast())
            <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="completed"><button class="btn small">{{ __('Mark done') }}</button></form>
            <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/mark') }}">@csrf<input type="hidden" name="status" value="no_show"><button class="btn small">{{ __('No-show') }}</button></form>
        @endif
        <form method="post" action="{{ url('/admin/schedule/'.$b->id.'/cancel') }}" onsubmit="return confirm({{ json_encode(__('Cancel this booking?'), JSON_UNESCAPED_UNICODE) }})" class="push">@csrf<button class="btn small danger">{{ __('Cancel booking') }}</button></form>
    </div>
@endif
