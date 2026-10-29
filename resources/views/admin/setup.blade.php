@extends('admin.layout')
@section('title', __('Getting started'))
@section('content')
    @php
        $cur = $business->settings['currency'] ?? 'RM';
        $live = $business->setup_done_at !== null;
        $paid = in_array($planState, ['active', 'internal'], true);
        $steps = [
            1 => [__('Describe your business'), $services > 0],
            2 => [__('Connect (optional)'), $channels > 0],
            3 => [__('Try your chat'), $live],
            4 => [__('Choose a plan'), $paid],
            5 => [__('Go live'), $live],
        ];
        $doneCount = collect($steps)->filter(fn ($s) => $s[1])->count();
        $aiOff = config('aichat.ai.provider') === 'off';
    @endphp
    <div class="page-head">
        <div><h1>{{ __('Getting started') }}</h1><p class="mute">{{ __('Five short steps to a working assistant. Nothing is saved to your business until you approve it.') }}</p></div>
        @if ($live)<a class="btn" href="{{ $chatUrl }}" target="_blank" rel="noopener">{{ __('Open my chat') }}</a>@endif
    </div>

    <div class="card wiz-progress">
        <div class="bar spread"><b>{{ $live ? __('You are live') : __(':n of 5 done', ['n' => $doneCount]) }}</b><span class="mute">{{ $live ? __('Share your chat link with customers.') : __('Take your time. You can come back any time.') }}</span></div>
        <div class="progress"><span style="width: {{ round($doneCount / 5 * 100) }}%"></span></div>
        <ol class="wiz-steps">
            @foreach ($steps as $n => [$label, $done])
                <li><a href="#step-{{ $n }}" @class(['done' => $done])><span class="wn">{{ $done ? '✓' : $n }}</span>{{ $label }}</a></li>
            @endforeach
        </ol>
    </div>

    <section class="card wiz" id="step-1">
        <header><span class="wn {{ $services > 0 ? 'ok' : '' }}">{{ $services > 0 ? '✓' : 1 }}</span>
            <div><h3>{{ __('Tell us about your business') }}</h3><p class="mute">{{ $services > 0 ? $services.' '.($services === 1 ? 'service' : 'services').' saved. You can draft more any time.' : 'We turn your price list into services and answers. You check them first.' }}</p></div></header>
        @if (! $draft)
            <form method="post" action="{{ url('/admin/setup/draft') }}">
                @csrf
                <label class="f"><span>{{ __('Paste your website text or price list') }}</span>
                    <textarea name="about" rows="7" required minlength="10" maxlength="6000" placeholder="Facial - {{ $cur }} 188 (60 min)&#10;Hair colour - {{ $cur }} 260 (120 min)&#10;Open Mon to Sat, 9am to 6pm">{{ old('about') }}</textarea>
                    @error('about')<small class="field-err">{{ $message }}</small>@enderror
                    <small class="hint">{{ $aiOff ? 'The AI is off here, so we read simple price lines: one service per line, name, price, minutes.' : 'The AI drafts services and answers from this. You can paste a whole web page.' }}</small></label>
                <label class="f"><span>{{ __('Address (optional, used when customers ask where you are)') }}</span><input type="text" name="address" value="{{ old('address', $address) }}" maxlength="200"></label>
                <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Draft my setup') }}</button></div>
            </form>
        @else
            <form method="post" action="{{ url('/admin/setup/approve') }}" id="approve-form">
                @csrf
                <div class="note ok">{{ $draft['source'] === 'ai' ? __('Draft made by the AI. Untick anything that is wrong, and fix the rest.') : __('Draft made from your price lines. Untick anything that is wrong, and fix the rest.') }}</div>
                @if (count($draft['services']))<h4 class="wiz-h">{{ __('Services') }} <small class="mute">{{ __('name, minutes, price (:cur)', ['cur' => $cur]) }}</small></h4>@endif
                @forelse ($draft['services'] as $i => $s)
                    <div class="draft-row" data-row>
                        <label class="tick"><input type="checkbox" name="services[{{ $i }}][include]" value="1" checked aria-label="{{ __('Add this service') }}"></label>
                        <input type="text" name="services[{{ $i }}][name_en]" value="{{ $s['name_en'] }}" placeholder="{{ __('Service name') }}" aria-label="{{ __('Service name') }}" class="c-name">
                        <input type="text" name="services[{{ $i }}][name_zh]" value="{{ $s['name_zh'] }}" placeholder="{{ __('中文名 (optional)') }}" aria-label="{{ __('Chinese name') }}" class="c-zh">
                        <input type="number" name="services[{{ $i }}][duration_min]" value="{{ $s['duration_min'] }}" min="5" max="480" aria-label="{{ __('Minutes') }}" class="num c-min">
                        <input type="number" step="0.01" name="services[{{ $i }}][price]" value="{{ $s['price'] }}" min="0" aria-label="{{ __('Price') }}" class="num c-price">
                    </div>
                @empty
                    <p class="mute empty">{!! __('No services found in that text. Discard and try again, or add them in :link.', ['link' => '<a href="'.route('booking.setup').'">'.e(__('Booking setup')).'</a>']) !!}</p>
                @endforelse
                @if (count($draft['faqs']))<h4 class="wiz-h">{{ __('Answers the assistant can use') }}</h4>@endif
                @foreach ($draft['faqs'] as $i => $f)
                    <div class="draft-faq" data-row>
                        <label class="tick"><input type="checkbox" name="faqs[{{ $i }}][include]" value="1" checked aria-label="{{ __('Use this answer') }}"></label>
                        <div><input type="text" name="faqs[{{ $i }}][title]" value="{{ $f['title'] }}" aria-label="{{ __('Question or topic') }}"><textarea name="faqs[{{ $i }}][answer]" rows="2" aria-label="{{ __('Answer') }}">{{ $f['answer'] }}</textarea></div>
                    </div>
                @endforeach
                <label class="f" style="margin-top:12px"><span>{{ __('Address') }}</span><input type="text" name="address" value="{{ $address }}" maxlength="200"></label>
                <div class="dlg-foot">
                    <button class="btn primary" type="submit" id="approve-btn">{{ __('Approve and save') }}</button>
                    <button class="btn push" type="submit" form="discard-form" onclick="return confirm('Throw this draft away?')">{{ __('Discard draft') }}</button>
                </div>
            </form>
            <form method="post" action="{{ url('/admin/setup/discard') }}" id="discard-form">@csrf</form>
        @endif
        <p class="mute small-note" style="padding:0;margin-top:8px">{!! __('Opening hours, closed days and people are in :link.', ['link' => '<a href="'.route('booking.setup').'">'.e(__('Booking setup')).'</a>']) !!}</p>
    </section>

    <section class="card wiz" id="step-2">
        <header><span class="wn {{ $channels > 0 ? 'ok' : '' }}">{{ $channels > 0 ? '✓' : 2 }}</span>
            <div><h3>{{ __('Connect your calendar and WhatsApp') }} <span class="tag">{{ __('optional') }}</span></h3><p class="mute">{{ __('The website chat works straight away. Connect these when you are ready.') }}</p></div></header>
        <div class="wiz-links">
            <div><b>{{ __('Google Calendar') }}</b><span class="mute">{{ __('So booked times are never double-booked.') }}</span><a class="btn small" href="{{ route('booking.setup') }}">{{ __('Open Booking setup') }}</a></div>
            <div><b>{{ __('WhatsApp, Instagram, Messenger') }}</b>@if ($channels)<span class="tag ok">{{ __(':n connected', ['n' => $channels]) }}</span>@else<span class="tag warn">{{ __('not connected') }}</span>@endif<a class="btn small" href="{{ route('channels') }}">{{ __('Open Channels') }}</a></div>
        </div>
    </section>

    <section class="card wiz" id="step-3">
        <header><span class="wn">3</span>
            <div><h3>{{ __('Try your chat') }}</h3><p class="mute">{{ __('Ask for prices, book a time, ask for a person. This is what customers will see.') }}</p></div></header>
        <div class="copy-row"><input type="text" readonly value="{{ $chatUrl }}" id="chat-link"><button type="button" class="btn small" data-copy="chat-link">{{ __('Copy') }}</button><a class="btn small primary" href="{{ $chatUrl }}" target="_blank" rel="noopener">{{ __('Open test chat') }}</a></div>
    </section>

    <section class="card wiz" id="step-4">
        <header><span class="wn {{ $paid ? 'ok' : '' }}">{{ $paid ? '✓' : 4 }}</span>
            <div><h3>{{ __('Choose a plan') }}</h3><p class="mute">{!! __('Current: :state. You can keep trying for free until the trial ends.', ['state' => '<b>'.e(__(ucfirst(str_replace('_', ' ', $planState)))).'</b>']) !!}</p></div>
            <a class="btn small" href="{{ route('billing') }}">{{ __('Plan & billing') }}</a></header>
    </section>

    <section class="card wiz" id="step-5">
        <header><span class="wn {{ $live ? 'ok' : '' }}">{{ $live ? '✓' : 5 }}</span>
            <div><h3>{{ __('Go live') }}</h3><p class="mute">{{ $live ? __('You are live. Share this link with customers, or add it to your website from Channels.') : __('When you are happy with the test chat, press the button. Customers can already use the link, this just marks setup as finished.') }}</p></div>
            @unless ($live)<form method="post" action="{{ url('/admin/setup/go-live') }}">@csrf<button class="btn primary" type="submit">{{ __('I am ready, go live') }}</button></form>@endunless</header>
    </section>
@endsection
@push('scripts')
<script>
(function () {
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.dataset.copy), done = function () { var t = b.textContent; b.textContent = @json(__('Copied')); setTimeout(function () { b.textContent = t; }, 1300); };
      if (navigator.clipboard) { navigator.clipboard.writeText(el.value).then(done); } else { el.select(); document.execCommand('copy'); done(); }
    });
  });
  var rows = document.querySelectorAll('[data-row]'), btn = document.getElementById('approve-btn');
  function sync() {
    var n = 0;
    rows.forEach(function (r) { var on = r.querySelector('input[type=checkbox]').checked; r.classList.toggle('off', !on); if (on) { n++; } });
    if (btn) { btn.textContent = n ? @json(__('Approve and save (:n)')).replace(':n', n) : @json(__('Approve and save')); }
  }
  rows.forEach(function (r) { r.querySelector('input[type=checkbox]').addEventListener('change', sync); });
  sync();
})();
</script>
@endpush
