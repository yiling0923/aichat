@extends('admin.layout')
@section('title', 'AI assistant')
@section('content')
    @php
        $effective = $planLimit === null ? $cap : min($cap, $planLimit);
        $pct = $effective > 0 ? min(100, round($calls / $effective * 100)) : 100;
        $purpose = ['reply' => 'Answer to a customer', 'suggest' => 'Suggestion for staff', 'summary' => 'Chat summary', 'preview' => 'Test box', 'setup' => 'Setup draft'];
        $modes = [
            'off' => ['Off', 'Only the menu bot answers. No AI at all.'],
            'suggest' => ['Suggest', 'The AI writes ideas for your team. Customers never see AI text.'],
            'approve' => ['Approve', 'The AI drafts an answer and your team checks it before it is sent.'],
            'auto' => ['Auto', 'The AI answers typed questions by itself, straight away.'],
        ];
        $styles = ['Friendly and short', 'Polite and formal', 'Never use emojis', 'Use first names', 'Answer in the customer\'s language'];
    @endphp
    <div class="page-head">
        <div><h1>AI assistant</h1>
            <p class="mute">Answers the questions your menu cannot, drafts replies for your team and summarises chats. It only uses information you gave it, and every answer is checked first.</p></div>
        <span class="tag {{ $provider === 'claude' && $keySet ? 'ok' : 'warn' }}">{{ $provider === 'claude' ? ($keySet ? 'Real AI' : 'AI key missing') : ($provider === 'fake' ? 'Practice AI' : 'AI off') }}</span>
    </div>

    @if ($provider === 'off')
        <div class="note warn">The AI is switched off for the whole system, so nothing below will answer yet. <details class="inline"><summary>How to turn it on</summary>Set <code>AI_PROVIDER=fake</code> in <code>.env</code> (free practice AI) or <code>AI_PROVIDER=claude</code> with your <code>ANTHROPIC_API_KEY</code>, then run <code>php artisan config:clear</code>.</details></div>
    @elseif ($provider === 'fake')
        <div class="note warn">This is a free <b>practice AI</b> that follows simple rules, so you can try every screen. It is not intelligent. <details class="inline"><summary>How to use the real one</summary>Set <code>AI_PROVIDER=claude</code> and <code>ANTHROPIC_API_KEY</code> in <code>.env</code>.</details></div>
    @elseif (! $keySet)
        <div class="note err">The real AI is selected but <code>ANTHROPIC_API_KEY</code> is not set in <code>.env</code>.</div>
    @endif

    <form method="post" action="{{ url('/admin/ai') }}">
        @csrf
        <h2 style="margin-top:6px">How should the AI help?</h2>
        <div class="mode-cards" role="radiogroup">
            @foreach ($modes as $key => [$name, $text])
                <label class="mode {{ $mode === $key ? 'on' : '' }}">
                    <input type="radio" name="mode" value="{{ $key }}" @checked($mode === $key)>
                    <b>{{ $name }}@if ($key === 'approve') <span class="tag ok">Good start</span>@endif</b>
                    <span>{{ $text }}</span>
                </label>
            @endforeach
        </div>

        <div class="card" style="margin-top:14px">
            <label class="f"><span>Tone and style (optional)</span>
                <textarea name="instructions" id="ai-style" rows="2" placeholder="For example: friendly and short, never use emojis.">{{ old('instructions', $instructions) }}</textarea></label>
            <div class="var-row">@foreach ($styles as $st)<button type="button" class="btn small" data-style="{{ $st }}">+ {{ $st }}</button>@endforeach</div>
            <details class="acct-more" style="margin-top:12px"><summary>Spending limit</summary>
                <label class="f" style="margin-top:10px;max-width:320px"><span>Most AI calls per month</span><input type="number" name="monthly_cap" value="{{ $cap }}" min="0" max="100000"></label>
                <p class="mute small-note" style="padding:0">Protects your bill. @if ($planLimit !== null) Your plan also allows {{ number_format($planLimit) }} a month. @endif</p></details>
            <div style="margin-top:14px"><button class="btn primary" type="submit">Save</button></div>
        </div>
    </form>

    <div class="card ai-usage">
        <div class="bar spread"><b>This month</b><span class="mute">Resets on {{ $resets }}</span></div>
        <div class="usage-line"><span><b>{{ number_format($calls) }}</b> of {{ number_format($effective) }} AI replies used</span>
            @if ($cost > 0)<span class="mute">about US${{ number_format($cost, 2) }} so far</span>@endif</div>
        <div class="progress {{ $pct >= 90 ? 'hot' : '' }}"><span style="width: {{ $pct }}%"></span></div>
        @if ($pct >= 90)<p class="mute" style="margin-top:6px">{{ $calls >= $effective ? 'The limit is reached: the AI is paused and chats still reach your inbox.' : 'Almost at the limit.' }} <a href="{{ route('billing') }}">Plan and billing</a></p>@endif
        <div class="usage-sub">
            <span><b>{{ $blocked }}</b> stopped by the safety check</span>
            <span><b>{{ $failed }}</b> failed</span>
        </div>
    </div>

    <h2>Try it</h2>
    <div class="card" id="try">
        <p class="mute small-note" style="padding:0">Ask a question as a customer would. Nothing is sent to anyone.</p>
        <div class="try-chips">
            @foreach (array_filter([$firstService ? 'How much is '.$firstService.'?' : null, 'What time do you open?', 'Can I book tomorrow?', 'I want a refund', 'Can I talk to a person?']) as $q)<button type="button" class="btn small" data-q="{{ $q }}">{{ $q }}</button>@endforeach
        </div>
        <div class="copy-row"><input type="text" id="try-text" placeholder="Type a question" maxlength="500"><button class="btn primary" id="try-btn" type="button">Ask</button></div>
        <div id="try-out"></div>
    </div>

    <h2>Extra answers you teach the AI</h2>
    <p class="mute" style="margin-top:-4px">Opening hours, services with prices and the information screens of your menu are added automatically. Add anything else customers ask.</p>
    <div class="bar" style="margin-bottom:10px"><button type="button" class="btn primary" id="new-answer">Add an answer</button></div>
    <div class="masonry">
        @foreach ($entries as $e)
            <div class="card reply {{ $e->active ? '' : 'off' }}">
                <div class="reply-top"><b>{{ $e->title }}</b>@unless ($e->active)<span class="tag">Not used</span>@endunless</div>
                <p class="reply-text"><span class="lang">EN</span>{{ $e->answer['en'] ?? '' }}</p>
                @if (! empty($e->answer['zh']))<p class="reply-text"><span class="lang">中文</span>{{ $e->answer['zh'] }}</p>@endif
                <div class="reply-actions"><button type="button" class="btn small" data-edit
                    data-action="{{ url('/admin/ai/knowledge/'.$e->id) }}" data-delete="{{ url('/admin/ai/knowledge/'.$e->id.'/delete') }}"
                    data-title="{{ $e->title }}" data-en="{{ $e->answer['en'] ?? '' }}" data-zh="{{ $e->answer['zh'] ?? '' }}" data-active="{{ $e->active ? 1 : 0 }}">Edit</button></div>
            </div>
        @endforeach
    </div>
    @php $have = $entries->pluck('title')->map(fn ($t) => mb_strtolower($t)); $ideas = collect($topicIdeas)->reject(fn ($t) => $have->contains(mb_strtolower($t))); @endphp
    @if ($ideas->isNotEmpty())
        <div class="card ideas"><b>{{ $entries->isEmpty() ? 'Start with a few common topics' : 'Ideas' }}</b>
            <div class="idea-row">@foreach ($ideas as $t)<button type="button" class="btn small" data-idea="{{ $t }}">+ {{ $t }}</button>@endforeach</div></div>
    @endif

    <div class="card safe">
        <b>What the AI will never do</b>
        <ul>
            <li>Invent prices, offers or opening times. It quotes only what is written for your business.</li>
            <li>Offer appointment times. Booking always goes through the Book button.</li>
            <li>Keep talking to an upset customer. It passes them to your team.</li>
            <li>Pretend to be a person. It says it is a virtual assistant.</li>
        </ul>
    </div>

    <details class="card fold"><summary><b>Everything the AI knows right now</b><small class="mute fold-help">Built from your services, hours, menu texts and the answers above.</small></summary>
        <pre class="know">{{ $knowledgeText }}</pre></details>

    <details class="card fold"><summary><b>Recent AI calls</b><small class="mute fold-help">The last {{ $recent->count() }}. Useful to see what it did and whether anything was stopped.</small></summary>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>When</th><th>For</th><th>Result</th><th>Note</th></tr></thead>
            <tbody>
            @forelse ($recent as $u)
                <tr><td>{{ $u->created_at?->copy()->setTimezone($business->timezone)->format('d M H:i') }}</td><td>{{ $purpose[$u->purpose] ?? $u->purpose }}</td>
                    <td><span class="tag {{ $u->status === 'ok' ? 'ok' : ($u->status === 'blocked' ? 'warn' : 'hot') }}">{{ $u->status === 'ok' ? 'Answered' : ($u->status === 'blocked' ? 'Stopped' : 'Failed') }}</span></td>
                    <td class="mute">{{ $u->note }}</td></tr>
            @empty
                <tr><td colspan="4" class="empty">No AI calls yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </details>

    <dialog id="ans-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3 id="ad-title">Add an answer</h3>
        <form method="post" id="ad-form" action="{{ url('/admin/ai/knowledge') }}">
            @csrf
            <label class="f" style="margin-top:12px"><span>Question or topic</span><input type="text" name="title" id="ad-name" required maxlength="150" placeholder="Parking"></label>
            <label class="f"><span>Answer (English)</span><textarea name="answer_en" id="ad-en" rows="4" required maxlength="1500"></textarea></label>
            <label class="f"><span>答案 (中文, optional)</span><textarea name="answer_zh" id="ad-zh" rows="4" maxlength="1500"></textarea></label>
            <label class="chk"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" id="ad-active" checked> The AI may use this answer</label>
            <div class="dlg-foot"><button class="btn primary" type="submit">Save</button><button class="btn danger push" type="submit" id="ad-delete" hidden onclick="return confirm('Delete this answer?')">Delete</button></div>
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
        if (res[0] !== 200) { bot.style.color = 'var(--red)'; bot.textContent = j.error || j.message || 'Failed.'; return; }
        bot.textContent = j.reply;
        var meta = document.createElement('div'); meta.className = 'try-meta';
        meta.innerHTML = '<span class="tag">' + j.intent + '</span><span class="tag">' + j.mood + '</span>' + (j.wants_human ? '<span class="tag warn">passes to a person</span>' : '');
        out.appendChild(meta);
      }).catch(function () { out.querySelector('.bot').textContent = 'Could not reach the server.'; });
  }
  document.getElementById('try-btn').addEventListener('click', ask);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ask(); } });
  document.querySelectorAll('[data-q]').forEach(function (b) { b.addEventListener('click', function () { input.value = b.dataset.q; ask(); }); });

  // Answers pop-up.
  var dlg = document.getElementById('ans-dialog'), form = document.getElementById('ad-form'), del = document.getElementById('ad-delete');
  var f = { name: document.getElementById('ad-name'), en: document.getElementById('ad-en'), zh: document.getElementById('ad-zh'), on: document.getElementById('ad-active') };
  function open(d) {
    document.getElementById('ad-title').textContent = d && d.action ? 'Edit answer' : 'Add an answer';
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
