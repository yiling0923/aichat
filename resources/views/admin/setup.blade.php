@extends('admin.layout')
@section('title', 'Getting started')
@section('content')
    @php
        $cur = $business->settings['currency'] ?? 'RM';
        $live = $business->setup_done_at !== null;
        $paid = in_array($planState, ['active', 'internal'], true);
        $steps = [
            1 => ['Describe your business', $services > 0],
            2 => ['Connect (optional)', $channels > 0],
            3 => ['Try your chat', $live],
            4 => ['Choose a plan', $paid],
            5 => ['Go live', $live],
        ];
        $doneCount = collect($steps)->filter(fn ($s) => $s[1])->count();
        $aiOff = config('aichat.ai.provider') === 'off';
    @endphp
    <div class="page-head">
        <div><h1>Getting started</h1><p class="mute">Five short steps to a working assistant. Nothing is saved to your business until you approve it.</p></div>
        @if ($live)<a class="btn" href="{{ $chatUrl }}" target="_blank" rel="noopener">Open my chat</a>@endif
    </div>

    <div class="card wiz-progress">
        <div class="bar spread"><b>{{ $live ? 'You are live' : $doneCount.' of 5 done' }}</b><span class="mute">{{ $live ? 'Share your chat link with customers.' : 'Take your time. You can come back any time.' }}</span></div>
        <div class="progress"><span style="width: {{ round($doneCount / 5 * 100) }}%"></span></div>
        <ol class="wiz-steps">
            @foreach ($steps as $n => [$label, $done])
                <li><a href="#step-{{ $n }}" @class(['done' => $done])><span class="wn">{{ $done ? '✓' : $n }}</span>{{ $label }}</a></li>
            @endforeach
        </ol>
    </div>

    <section class="card wiz" id="step-1">
        <header><span class="wn {{ $services > 0 ? 'ok' : '' }}">{{ $services > 0 ? '✓' : 1 }}</span>
            <div><h3>Tell us about your business</h3><p class="mute">{{ $services > 0 ? $services.' '.($services === 1 ? 'service' : 'services').' saved. You can draft more any time.' : 'We turn your price list into services and answers. You check them first.' }}</p></div></header>
        @if (! $draft)
            <form method="post" action="{{ url('/admin/setup/draft') }}">
                @csrf
                <label class="f"><span>Paste your website text or price list</span>
                    <textarea name="about" rows="7" required minlength="10" maxlength="6000" placeholder="Facial - {{ $cur }} 188 (60 min)&#10;Hair colour - {{ $cur }} 260 (120 min)&#10;Open Mon to Sat, 9am to 6pm">{{ old('about') }}</textarea>
                    @error('about')<small class="field-err">{{ $message }}</small>@enderror
                    <small class="hint">{{ $aiOff ? 'The AI is off here, so we read simple price lines: one service per line, name, price, minutes.' : 'The AI drafts services and answers from this. You can paste a whole web page.' }}</small></label>
                <label class="f"><span>Address (optional, used when customers ask where you are)</span><input type="text" name="address" value="{{ old('address', $address) }}" maxlength="200"></label>
                <div class="dlg-foot"><button class="btn primary" type="submit">Draft my setup</button></div>
            </form>
        @else
            <form method="post" action="{{ url('/admin/setup/approve') }}" id="approve-form">
                @csrf
                <div class="note ok">Draft made {{ $draft['source'] === 'ai' ? 'by the AI' : 'from your price lines' }}. Untick anything that is wrong, and fix the rest.</div>
                @if (count($draft['services']))<h4 class="wiz-h">Services <small class="mute">name, minutes, price ({{ $cur }})</small></h4>@endif
                @forelse ($draft['services'] as $i => $s)
                    <div class="draft-row" data-row>
                        <label class="tick"><input type="checkbox" name="services[{{ $i }}][include]" value="1" checked aria-label="Add this service"></label>
                        <input type="text" name="services[{{ $i }}][name_en]" value="{{ $s['name_en'] }}" placeholder="Service name" aria-label="Service name" class="c-name">
                        <input type="text" name="services[{{ $i }}][name_zh]" value="{{ $s['name_zh'] }}" placeholder="中文名 (optional)" aria-label="Chinese name" class="c-zh">
                        <input type="number" name="services[{{ $i }}][duration_min]" value="{{ $s['duration_min'] }}" min="5" max="480" aria-label="Minutes" class="num c-min">
                        <input type="number" step="0.01" name="services[{{ $i }}][price]" value="{{ $s['price'] }}" min="0" aria-label="Price" class="num c-price">
                    </div>
                @empty
                    <p class="mute empty">No services found in that text. Discard and try again, or add them in <a href="{{ route('booking.setup') }}">Booking setup</a>.</p>
                @endforelse
                @if (count($draft['faqs']))<h4 class="wiz-h">Answers the assistant can use</h4>@endif
                @foreach ($draft['faqs'] as $i => $f)
                    <div class="draft-faq" data-row>
                        <label class="tick"><input type="checkbox" name="faqs[{{ $i }}][include]" value="1" checked aria-label="Use this answer"></label>
                        <div><input type="text" name="faqs[{{ $i }}][title]" value="{{ $f['title'] }}" aria-label="Question or topic"><textarea name="faqs[{{ $i }}][answer]" rows="2" aria-label="Answer">{{ $f['answer'] }}</textarea></div>
                    </div>
                @endforeach
                <label class="f" style="margin-top:12px"><span>Address</span><input type="text" name="address" value="{{ $address }}" maxlength="200"></label>
                <div class="dlg-foot">
                    <button class="btn primary" type="submit" id="approve-btn">Approve and save</button>
                    <button class="btn push" type="submit" form="discard-form" onclick="return confirm('Throw this draft away?')">Discard draft</button>
                </div>
            </form>
            <form method="post" action="{{ url('/admin/setup/discard') }}" id="discard-form">@csrf</form>
        @endif
        <p class="mute small-note" style="padding:0;margin-top:8px">Opening hours, closed days and people are in <a href="{{ route('booking.setup') }}">Booking setup</a>.</p>
    </section>

    <section class="card wiz" id="step-2">
        <header><span class="wn {{ $channels > 0 ? 'ok' : '' }}">{{ $channels > 0 ? '✓' : 2 }}</span>
            <div><h3>Connect your calendar and WhatsApp <span class="tag">optional</span></h3><p class="mute">The website chat works straight away. Connect these when you are ready.</p></div></header>
        <div class="wiz-links">
            <div><b>Google Calendar</b><span class="mute">So booked times are never double-booked.</span><a class="btn small" href="{{ route('booking.setup') }}">Open Booking setup</a></div>
            <div><b>WhatsApp, Instagram, Messenger</b>@if ($channels)<span class="tag ok">{{ $channels }} connected</span>@else<span class="tag warn">not connected</span>@endif<a class="btn small" href="{{ route('channels') }}">Open Channels</a></div>
        </div>
    </section>

    <section class="card wiz" id="step-3">
        <header><span class="wn">3</span>
            <div><h3>Try your chat</h3><p class="mute">Ask for prices, book a time, ask for a person. This is what customers will see.</p></div></header>
        <div class="copy-row"><input type="text" readonly value="{{ $chatUrl }}" id="chat-link"><button type="button" class="btn small" data-copy="chat-link">Copy</button><a class="btn small primary" href="{{ $chatUrl }}" target="_blank" rel="noopener">Open test chat</a></div>
    </section>

    <section class="card wiz" id="step-4">
        <header><span class="wn {{ $paid ? 'ok' : '' }}">{{ $paid ? '✓' : 4 }}</span>
            <div><h3>Choose a plan</h3><p class="mute">Current: <b>{{ ucfirst(str_replace('_', ' ', $planState)) }}</b>. You can keep trying for free until the trial ends.</p></div>
            <a class="btn small" href="{{ route('billing') }}">Plan &amp; billing</a></header>
    </section>

    <section class="card wiz" id="step-5">
        <header><span class="wn {{ $live ? 'ok' : '' }}">{{ $live ? '✓' : 5 }}</span>
            <div><h3>Go live</h3><p class="mute">{{ $live ? 'You are live. Share this link with customers, or add it to your website from Channels.' : 'When you are happy with the test chat, press the button. Customers can already use the link, this just marks setup as finished.' }}</p></div>
            @unless ($live)<form method="post" action="{{ url('/admin/setup/go-live') }}">@csrf<button class="btn primary" type="submit">I am ready, go live</button></form>@endunless</header>
    </section>
@endsection
@push('scripts')
<script>
(function () {
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.dataset.copy), done = function () { var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1300); };
      if (navigator.clipboard) { navigator.clipboard.writeText(el.value).then(done); } else { el.select(); document.execCommand('copy'); done(); }
    });
  });
  var rows = document.querySelectorAll('[data-row]'), btn = document.getElementById('approve-btn');
  function sync() {
    var n = 0;
    rows.forEach(function (r) { var on = r.querySelector('input[type=checkbox]').checked; r.classList.toggle('off', !on); if (on) { n++; } });
    if (btn) { btn.textContent = n ? 'Approve and save (' + n + ')' : 'Approve and save'; }
  }
  rows.forEach(function (r) { r.querySelector('input[type=checkbox]').addEventListener('change', sync); });
  sync();
})();
</script>
@endpush
