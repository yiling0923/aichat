@extends('admin.layout')
@section('title', 'AI assistant')
@section('content')
    <h1>AI assistant</h1>
    <p class="mute">The AI answers questions the menu cannot, drafts replies for your team, and summarises chats. It only uses what is written in your own business information, and its answers are checked before anyone sees them.</p>

    @if ($provider === 'off')
        <div class="note warn">The AI is switched off for the whole system. To try it, set <code>AI_PROVIDER=fake</code> in <code>.env</code> (a free pretend AI) or <code>AI_PROVIDER=claude</code> with your <code>ANTHROPIC_API_KEY</code>, then run <code>php artisan config:clear</code>.</div>
    @elseif ($provider === 'fake')
        <div class="note warn">System AI: <b>fake</b>. A free pretend AI that follows simple rules, so you can try everything. It is not intelligent. Use <code>AI_PROVIDER=claude</code> for the real one.</div>
    @elseif (! $keySet)
        <div class="note err">System AI: <b>claude</b>, but <code>ANTHROPIC_API_KEY</code> is not set in <code>.env</code>.</div>
    @else
        <div class="note ok">System AI: <b>Claude</b> ({{ $model }}). A real AI that costs a little per message.</div>
    @endif

    <form method="post" action="{{ url('/admin/ai') }}" class="card">
        @csrf
        <h3>How should the AI help?</h3>
        <div class="grid2">
            <label class="f"><span>Mode</span>
                <select name="mode">
                    <option value="off" @selected($mode === 'off')>Off: only the menu bot</option>
                    <option value="suggest" @selected($mode === 'suggest')>Suggest: ideas for your team, customers never see AI text</option>
                    <option value="approve" @selected($mode === 'approve')>Approve: AI drafts, your team checks and sends</option>
                    <option value="auto" @selected($mode === 'auto')>Auto: AI answers typed questions by itself</option>
                </select></label>
            <label class="f"><span>Most AI calls per month (protects your bill)</span><input type="number" name="monthly_cap" value="{{ $cap }}"></label>
        </div>
        <label class="f"><span>Tone and style (optional). For example: friendly and short, call customers "you", never use emojis.</span>
            <textarea name="instructions" rows="2">{{ old('instructions', $instructions) }}</textarea></label>
        <p class="mute small-note" style="margin-top:8px">Buttons and booking always use the menu. The AI only steps in when someone types something the menu does not understand. It never offers appointment times and never invents prices. An upset customer is passed to your team.</p>
        <button class="btn primary" type="submit">Save</button>
    </form>

    <div class="grid3">
        <div class="stat"><b>{{ $calls }}</b><span>AI calls this month (limit {{ $cap }})</span></div>
        <div class="stat"><b>{{ $blocked }}</b><span>answers stopped by the safety check</span></div>
        <div class="stat"><b>{{ $failed }}</b><span>calls that failed</span></div>
        <div class="stat"><b>{{ number_format($cost, 4) }}</b><span>estimated US dollars (real AI only; {{ number_format($tokensIn + $tokensOut) }} tokens)</span></div>
    </div>

    <h2>Try it</h2>
    <div class="card" id="try">
        <p class="mute small-note">Type a question as a customer would. Nothing is sent to anyone.</p>
        <div class="bar"><input type="text" id="try-text" placeholder="How much is a hydrating facial?" style="max-width:520px"><button class="btn primary" id="try-btn" type="button">Ask</button></div>
        <div id="try-out" class="mute"></div>
    </div>

    <h2>Extra answers you teach the AI</h2>
    <p class="mute">Opening hours, services with prices and the information screens of your menu are added automatically. Add anything else customers ask, such as parking, payment methods or aftercare.</p>
    @foreach ($entries as $e)
        <form method="post" action="{{ url('/admin/ai/knowledge/'.$e->id) }}" class="card row">
            @csrf
            <label class="f"><span>Question or topic</span><input type="text" name="title" value="{{ $e->title }}"></label>
            <label class="f" style="flex:2"><span>Answer (English)</span><textarea name="answer_en" rows="2">{{ $e->answer['en'] ?? '' }}</textarea></label>
            <label class="f" style="flex:2"><span>答案 (中文)</span><textarea name="answer_zh" rows="2">{{ $e->answer['zh'] ?? '' }}</textarea></label>
            <label class="f check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($e->active)><span>Use</span></label>
            <button class="btn" type="submit">Save</button>
            <button class="btn danger" type="submit" formaction="{{ url('/admin/ai/knowledge/'.$e->id.'/delete') }}" onclick="return confirm('Delete this answer?')">Delete</button>
        </form>
    @endforeach
    <form method="post" action="{{ url('/admin/ai/knowledge') }}" class="card row">
        @csrf
        <label class="f"><span>New: question or topic</span><input type="text" name="title" placeholder="Parking" required></label>
        <label class="f" style="flex:2"><span>Answer (English)</span><textarea name="answer_en" rows="2" required></textarea></label>
        <label class="f" style="flex:2"><span>答案 (中文)</span><textarea name="answer_zh" rows="2"></textarea></label>
        <button class="btn primary" type="submit">Add</button>
    </form>

    <details class="card"><summary><b>Everything the AI knows right now</b> (click to see)</summary>
        <pre style="white-space:pre-wrap;font-size:12px;margin-top:10px">{{ $knowledgeText }}</pre></details>

    <h2>Recent AI calls</h2>
    <table class="table">
        <thead><tr><th>When</th><th>For</th><th>Result</th><th>Tokens in / out</th><th>Note</th></tr></thead>
        <tbody>
        @forelse ($recent as $u)
            <tr><td>{{ $u->created_at?->copy()->setTimezone($business->timezone)->format('d M H:i') }}</td><td>{{ $u->purpose }}</td>
                <td><span class="tag {{ $u->status === 'ok' ? 'ok' : ($u->status === 'blocked' ? 'warn' : 'hot') }}">{{ $u->status }}</span></td>
                <td>{{ $u->input_tokens }} / {{ $u->output_tokens }}</td><td class="mute">{{ $u->note }}</td></tr>
        @empty
            <tr><td colspan="5" class="empty">No AI calls yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
@push('scripts')
<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var out = document.getElementById('try-out');
  function ask() {
    var text = document.getElementById('try-text').value.trim();
    if (!text) { return; }
    out.textContent = 'Thinking...';
    fetch('{{ url('/admin/ai/preview') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ text: text })
    }).then(function (r) { return r.json().then(function (j) { return [r.status, j]; }); }).then(function (res) {
      var j = res[1];
      out.innerHTML = '';
      var p = document.createElement('div');
      p.style.color = 'var(--ink)'; p.style.whiteSpace = 'pre-wrap';
      if (res[0] !== 200) { p.style.color = 'var(--red)'; p.textContent = j.error || j.message || 'Failed.'; out.appendChild(p); return; }
      p.textContent = j.reply;
      var meta = document.createElement('small');
      meta.textContent = 'Understood as: ' + j.intent + ', ' + j.mood + (j.wants_human ? ', passes to a person' : '');
      out.appendChild(p); out.appendChild(meta);
    }).catch(function () { out.textContent = 'Could not reach the server.'; });
  }
  document.getElementById('try-btn').addEventListener('click', ask);
  document.getElementById('try-text').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ask(); } });
})();
</script>
@endpush
