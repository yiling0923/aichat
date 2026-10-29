@extends('admin.layout')
@section('title', __('AI assistant'))
@section('content')
    @php
        $effective = $planLimit === null ? $cap : min($cap, $planLimit);
        $pct = $effective > 0 ? min(100, round($calls / $effective * 100)) : 100;
        $purpose = ['reply' => __('Answer to a customer'), 'suggest' => __('Suggestion for staff'), 'summary' => __('Chat summary'), 'preview' => __('Test box'), 'setup' => __('Setup draft')];
        $modes = [
            'off' => [__('Off'), __('Only the menu bot answers. No AI at all.')],
            'suggest' => [__('Suggest'), __('The AI writes ideas for your team. Customers never see AI text.')],
            'approve' => [__('Approve'), __('The AI drafts an answer and your team checks it before it is sent.')],
            'auto' => [__('Auto'), __('The AI answers typed questions by itself, straight away.')],
        ];
        $styles = [__('Friendly and short'), __('Polite and formal'), __('Never use emojis'), __('Use first names'), __('Answer in the customer\'s language')];
    @endphp
    <div class="page-head">
        <div><h1>{{ __('AI assistant') }}</h1>
            <p class="mute">{{ __('Answers the questions your menu cannot, drafts replies for your team and summarises chats. It only uses information you gave it, and every answer is checked first.') }}</p></div>
        <span class="tag {{ $provider === 'claude' && $keySet ? 'ok' : 'warn' }}">{{ $provider === 'claude' ? ($keySet ? 'Real AI' : 'AI key missing') : ($provider === 'fake' ? 'Practice AI' : 'AI off') }}</span>
    </div>

    @if ($provider === 'off')
        <div class="note warn">{{ __('The AI is switched off for the whole system, so nothing below will answer yet.') }} <details class="inline"><summary>{{ __('How to turn it on') }}</summary>{!! __('Set :fake in :env (free practice AI) or :claude with your :key, then run :clear.', ['fake' => '<code>AI_PROVIDER=fake</code>', 'env' => '<code>.env</code>', 'claude' => '<code>AI_PROVIDER=claude</code>', 'key' => '<code>ANTHROPIC_API_KEY</code>', 'clear' => '<code>php artisan config:clear</code>']) !!}</details></div>
    @elseif ($provider === 'fake')
        <div class="note warn">{!! __('This is a free :practice that follows simple rules, so you can try every screen. It is not intelligent.', ['practice' => '<b>'.e(__('practice AI')).'</b>']) !!} <details class="inline"><summary>{{ __('How to use the real one') }}</summary>{!! __('Set :claude and :key in :env.', ['claude' => '<code>AI_PROVIDER=claude</code>', 'key' => '<code>ANTHROPIC_API_KEY</code>', 'env' => '<code>.env</code>']) !!}</details></div>
    @elseif (! $keySet)
        <div class="note err">{!! __('The real AI is selected but :key is not set in :env.', ['key' => '<code>ANTHROPIC_API_KEY</code>', 'env' => '<code>.env</code>']) !!}</div>
    @endif

    <form method="post" action="{{ url('/admin/ai') }}">
        @csrf
        <h2 style="margin-top:6px">{{ __('How should the AI help?') }}</h2>
        <div class="mode-cards" role="radiogroup">
            @foreach ($modes as $key => [$name, $text])
                <label class="mode {{ $mode === $key ? 'on' : '' }}">
                    <input type="radio" name="mode" value="{{ $key }}" @checked($mode === $key)>
                    <b>{{ $name }}@if ($key === 'approve') <span class="tag ok">{{ __('Good start') }}</span>@endif</b>
                    <span>{{ $text }}</span>
                </label>
            @endforeach
        </div>

        <div class="card" style="margin-top:14px">
            <label class="f"><span>{{ __('Tone and style (optional)') }}</span>
                <textarea name="instructions" id="ai-style" rows="2" placeholder="{{ __('For example: friendly and short, never use emojis.') }}">{{ old('instructions', $instructions) }}</textarea></label>
            <div class="var-row">@foreach ($styles as $st)<button type="button" class="btn small" data-style="{{ $st }}">+ {{ $st }}</button>@endforeach</div>
            <details class="acct-more" style="margin-top:12px"><summary>{{ __('Spending limit') }}</summary>
                <label class="f" style="margin-top:10px;max-width:320px"><span>{{ __('Most AI calls per month') }}</span><input type="number" name="monthly_cap" value="{{ $cap }}" min="0" max="100000"></label>
                <p class="mute small-note" style="padding:0">{{ __('Protects your bill.') }} @if ($planLimit !== null) {{ __('Your plan also allows :n a month.', ['n' => number_format($planLimit)]) }} @endif</p></details>
            <div style="margin-top:14px"><button class="btn primary" type="submit">{{ __('Save') }}</button></div>
        </div>
    </form>

    <div class="card ai-usage">
        <div class="bar spread"><b>{{ __('This month') }}</b><span class="mute">{{ __('Resets on :date', ['date' => $resets]) }}</span></div>
        <div class="usage-line"><span>{!! __(':a of :b AI replies used', ['a' => '<b>'.number_format($calls).'</b>', 'b' => number_format($effective)]) !!}</span>
            @if ($cost > 0)<span class="mute">{{ __('about US$:n so far', ['n' => number_format($cost, 2)]) }}</span>@endif</div>
        <div class="progress {{ $pct >= 90 ? 'hot' : '' }}"><span style="width: {{ $pct }}%"></span></div>
        @if ($pct >= 90)<p class="mute" style="margin-top:6px">{{ $calls >= $effective ? 'The limit is reached: the AI is paused and chats still reach your inbox.' : 'Almost at the limit.' }} <a href="{{ route('billing') }}">{{ __('Plan and billing') }}</a></p>@endif
        <div class="usage-sub">
            <span><b>{{ $blocked }}</b> {{ __('stopped by the safety check') }}</span>
            <span><b>{{ $failed }}</b> {{ __('failed') }}</span>
        </div>
    </div>

    <h2>{{ __('Try it') }}</h2>
    <div class="card" id="try">
        <p class="mute small-note" style="padding:0">{{ __('Ask a question as a customer would. Nothing is sent to anyone.') }}</p>
        <div class="try-chips">
            @foreach (array_filter([$firstService ? __('How much is :x?', ['x' => $firstService]) : null, __('What time do you open?'), __('Can I book tomorrow?'), __('I want a refund'), __('Can I talk to a person?')]) as $q)<button type="button" class="btn small" data-q="{{ $q }}">{{ $q }}</button>@endforeach
        </div>
        <div class="copy-row"><input type="text" id="try-text" placeholder="{{ __('Type a question') }}" maxlength="500"><button class="btn primary" id="try-btn" type="button">{{ __('Ask') }}</button></div>
        <div id="try-out"></div>
    </div>

    <h2>{{ __('Extra answers you teach the AI') }}</h2>
    <p class="mute" style="margin-top:-4px">{{ __('Opening hours, services with prices and the information screens of your menu are added automatically. Add anything else customers ask.') }}</p>
    <div class="bar" style="margin-bottom:10px"><button type="button" class="btn primary" id="new-answer">{{ __('Add an answer') }}</button></div>
    <div class="masonry">
        @foreach ($entries as $e)
            <div class="card reply {{ $e->active ? '' : 'off' }}">
                <div class="reply-top"><b>{{ $e->title }}</b>@unless ($e->active)<span class="tag">{{ __('Not used') }}</span>@endunless</div>
                <p class="reply-text"><span class="lang">{{ __('EN') }}</span>{{ $e->answer['en'] ?? '' }}</p>
                @if (! empty($e->answer['zh']))<p class="reply-text"><span class="lang">中文</span>{{ $e->answer['zh'] }}</p>@endif
                <div class="reply-actions"><button type="button" class="btn small" data-edit
                    data-action="{{ url('/admin/ai/knowledge/'.$e->id) }}" data-delete="{{ url('/admin/ai/knowledge/'.$e->id.'/delete') }}"
                    data-title="{{ $e->title }}" data-en="{{ $e->answer['en'] ?? '' }}" data-zh="{{ $e->answer['zh'] ?? '' }}" data-active="{{ $e->active ? 1 : 0 }}">{{ __('Edit') }}</button></div>
            </div>
        @endforeach
    </div>
    @php $have = $entries->pluck('title')->map(fn ($t) => mb_strtolower($t)); $ideas = collect($topicIdeas)->reject(fn ($t) => $have->contains(mb_strtolower($t))); @endphp
    @if ($ideas->isNotEmpty())
        <div class="card ideas"><b>{{ $entries->isEmpty() ? __('Start with a few common topics') : __('Ideas') }}</b>
            <div class="idea-row">@foreach ($ideas as $t)<button type="button" class="btn small" data-idea="{{ $t }}">+ {{ $t }}</button>@endforeach</div></div>
    @endif

    <div class="card safe">
        <b>{{ __('What the AI will never do') }}</b>
        <ul>
            <li>{{ __('Invent prices, offers or opening times. It quotes only what is written for your business.') }}</li>
            <li>{{ __('Offer appointment times. Booking always goes through the Book button.') }}</li>
            <li>{{ __('Keep talking to an upset customer. It passes them to your team.') }}</li>
            <li>{{ __('Pretend to be a person. It says it is a virtual assistant.') }}</li>
        </ul>
    </div>

    <details class="card fold"><summary><b>{{ __('Everything the AI knows right now') }}</b><small class="mute fold-help">{{ __('Built from your services, hours, menu texts and the answers above.') }}</small></summary>
        <pre class="know">{{ $knowledgeText }}</pre></details>

    <details class="card fold"><summary><b>{{ __('Recent AI calls') }}</b><small class="mute fold-help">{{ __('The last :n. Useful to see what it did and whether anything was stopped.', ['n' => $recent->count()]) }}</small></summary>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('For') }}</th><th>{{ __('Result') }}</th><th>{{ __('Note') }}</th></tr></thead>
            <tbody>
            @forelse ($recent as $u)
                <tr><td>{{ $u->created_at?->copy()->setTimezone($business->timezone)->format('d M H:i') }}</td><td>{{ $purpose[$u->purpose] ?? $u->purpose }}</td>
                    <td><span class="tag {{ $u->status === 'ok' ? 'ok' : ($u->status === 'blocked' ? 'warn' : 'hot') }}">{{ $u->status === 'ok' ? __('Answered') : ($u->status === 'blocked' ? __('Stopped') : __('Failed')) }}</span></td>
                    <td class="mute">{{ $u->note }}</td></tr>
            @empty
                <tr><td colspan="4" class="empty">{{ __('No AI calls yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </details>

    <dialog id="ans-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3 id="ad-title">{{ __('Add an answer') }}</h3>
        <form method="post" id="ad-form" action="{{ url('/admin/ai/knowledge') }}">
            @csrf
            <label class="f" style="margin-top:12px"><span>{{ __('Question or topic') }}</span><input type="text" name="title" id="ad-name" required maxlength="150" placeholder="{{ __('Parking') }}"></label>
            <label class="f"><span>{{ __('Answer (English)') }}</span><textarea name="answer_en" id="ad-en" rows="4" required maxlength="1500"></textarea></label>
            <label class="f"><span>{{ __('答案 (中文, optional)') }}</span><textarea name="answer_zh" id="ad-zh" rows="4" maxlength="1500"></textarea></label>
            <label class="chk"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" id="ad-active" checked> {{ __('The AI may use this answer') }}</label>
            <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save') }}</button><button class="btn danger push" type="submit" id="ad-delete" hidden onclick="return confirm('Delete this answer?')">{{ __('Delete') }}</button></div>
        </form>
    </dialog>
@endsection
@push('scripts')
<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;

  // Mode cards.
  document.querySelectorAll('.mode input').forEach(function (r) {
    r.addEventListener('change', function () { document.querySelectorAll('.mode').forEach(function (m) { m.classList.toggle('on', m.querySelector('input').checked); }); });
  });
  // Style chips.
  var style = document.getElementById('ai-style');
  document.querySelectorAll('[data-style]').forEach(function (b) {
    b.addEventListener('click', function () { var t = style.value.trim(); style.value = (t ? t.replace(/[.\s]+$/, '') + '. ' : '') + b.dataset.style + '.'; style.focus(); });
  });

  // Test box.
  var out = document.getElementById('try-out'), input = document.getElementById('try-text');
  function ask() {
    var text = input.value.trim();
    if (!text) { return; }
    out.innerHTML = '<div class="bubble customer">' + text.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</div><div class="bubble bot mute">Thinking...</div>';
    fetch('{{ url('/admin/ai/preview') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ text: text }) })
      .then(function (r) { return r.json().then(function (j) { return [r.status, j]; }); })
      .then(function (res) {
        var j = res[1], bot = out.querySelector('.bot');
        bot.classList.remove('mute');
        if (res[0] !== 200) { bot.style.color = 'var(--red)'; bot.textContent = j.error || j.message || @json(__('Failed.')); return; }
        bot.textContent = j.reply;
        var meta = document.createElement('div'); meta.className = 'try-meta';
        meta.innerHTML = '<span class="tag">' + j.intent + '</span><span class="tag">' + j.mood + '</span>' + (j.wants_human ? '<span class="tag warn">passes to a person</span>' : '');
        out.appendChild(meta);
      }).catch(function () { out.querySelector('.bot').textContent = @json(__('Could not reach the server.')); });
  }
  document.getElementById('try-btn').addEventListener('click', ask);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ask(); } });
  document.querySelectorAll('[data-q]').forEach(function (b) { b.addEventListener('click', function () { input.value = b.dataset.q; ask(); }); });

  // Answers pop-up.
  var dlg = document.getElementById('ans-dialog'), form = document.getElementById('ad-form'), del = document.getElementById('ad-delete');
  var f = { name: document.getElementById('ad-name'), en: document.getElementById('ad-en'), zh: document.getElementById('ad-zh'), on: document.getElementById('ad-active') };
  function open(d) {
    document.getElementById('ad-title').textContent = d && d.action ? @json(__('Edit answer')) : @json(__('Add an answer'));
    form.action = d && d.action ? d.action : '{{ url('/admin/ai/knowledge') }}';
    f.name.value = d ? (d.title || '') : ''; f.en.value = d ? (d.en || '') : ''; f.zh.value = d ? (d.zh || '') : ''; f.on.checked = d && d.active !== undefined ? d.active === '1' : true;
    del.hidden = !(d && d.action); if (d && d.delete) { del.setAttribute('formaction', d.delete); }
    dlg.showModal(); (d && d.action ? f.en : (d && d.title ? f.en : f.name)).focus();
  }
  document.getElementById('new-answer').addEventListener('click', function () { open(null); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset); }); });
  document.querySelectorAll('[data-idea]').forEach(function (b) { b.addEventListener('click', function () { open({ title: b.dataset.idea }); }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
})();
</script>
@endpush
