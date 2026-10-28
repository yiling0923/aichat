@extends('admin.layout')
@section('title', 'Message templates')
@section('content')
    @php
        $languages = ['en' => 'English', 'zh_CN' => '中文（简体）', 'zh_HK' => '中文（香港）', 'ms' => 'Malay'];
        $statusOf = function ($t) {
            $s = strtoupper($t->status);
            return match (true) {
                $s === 'APPROVED' => ['ok', 'Approved', 'Ready to use.'],
                in_array($s, ['REJECTED', 'DISABLED', 'PAUSED']) => ['hot', ucfirst(strtolower($s)), 'Meta will not let you use it. Fix the wording and send a new one.'],
                default => ['warn', 'Waiting for Meta', 'Meta is reviewing it. Usually a few minutes, sometimes a day.'],
            };
        };
        $groups = ['all' => 'All', 'approved' => 'Approved', 'waiting' => 'Waiting', 'problem' => 'Problem'];
        $groupOf = fn ($t) => $t->isApproved() ? 'approved' : (in_array(strtoupper($t->status), ['REJECTED', 'DISABLED', 'PAUSED']) ? 'problem' : 'waiting');
        $counts = collect($templates)->countBy($groupOf);
        $examples = [
            ['Booking reminder', 'booking_reminder', 'UTILITY', 'Hi {{1}}, a reminder of your {{2}} appointment on {{3}}. Reply here if you need to change it.'],
            ['Thank you and feedback', 'thank_you_feedback', 'UTILITY', 'Hi {{1}}, thank you for visiting us for your {{2}}! How was your visit? Reply with a number from 1 to 5.'],
            ['We miss you', 'we_miss_you', 'MARKETING', 'Hi {{1}}, it has been a while since your {{2}}. Would you like to book your next visit? Reply here and we will find a time.'],
        ];
    @endphp
    <div class="page-head">
        <div><h1>Message templates</h1>
            <p class="mute">WhatsApp lets you message someone more than 24 hours after their last message only with a template that Meta has approved. Reminders and follow-ups use them.</p></div>
        @if ($channels->isNotEmpty())
            <div class="bar">
                <form method="post" action="{{ url('/admin/templates/sync') }}">@csrf<button class="btn">Refresh status</button></form>
                <button type="button" class="btn primary" id="new-tpl">New template</button>
            </div>
        @endif
    </div>
    @error('template')<div class="note err">{{ $message }}</div>@enderror

    @if ($channels->isEmpty())
        <div class="card empty-card"><b>Connect a WhatsApp number first</b><p class="mute">Templates belong to a WhatsApp number. Once one is connected you can create and manage them here.</p>
            <a class="btn primary" href="{{ route('channels') }}">Open Channels</a></div>
    @else
        <div class="filters">
            <div class="seg" id="tpl-groups">
                @foreach ($groups as $k => $label)<a href="#" data-group="{{ $k }}" @class(['on' => $k === 'all'])>{{ $label }} ({{ $k === 'all' ? $templates->count() : ($counts[$k] ?? 0) }})</a>@endforeach
            </div>
        </div>

        @if ($templates->isEmpty())
            <div class="card empty-card">
                <b>No templates yet</b>
                <p class="mute">Start from an example below, or press <b>Refresh status</b> to load the ones already in Meta.</p>
            </div>
        @endif

        <div class="masonry" id="tpl-list">
            @foreach ($templates as $t)
                @php [$cls, $label, $help] = $statusOf($t); $used = $usage[$t->name] ?? []; @endphp
                <div class="card tpl" data-group="{{ $groupOf($t) }}">
                    <div class="tpl-top">
                        <div><b>{{ str_replace('_', ' ', ucfirst($t->name)) }}</b><br><small class="mute"><code>{{ $t->name }}</code></small></div>
                        <span class="tag {{ $cls }}" title="{{ $help }}">{{ $label }}</span>
                    </div>
                    <div class="wa-bubble">{!! preg_replace('/\{\{(\d+)\}\}/', '<span class="ph">$1</span>', e($t->body)) !!}</div>
                    <div class="tpl-meta">
                        <span>{{ $languages[$t->language] ?? $t->language }}</span>
                        <span>{{ strtoupper($t->category) === 'MARKETING' ? 'Marketing' : 'Utility' }}</span>
                        <span>{{ $t->channel->name }}</span>
                    </div>
                    <p class="mute tpl-help">{{ $help }}</p>
                    @if ($t->last_error)<div class="note err" style="margin:8px 0 0">{{ $t->last_error }}</div>@endif
                    <div class="tpl-foot">
                        @if ($used)<span class="tpl-used">Used by: {{ implode(', ', $used) }}</span>@else<span class="mute">Not used by anything yet</span>@endif
                        <form method="post" action="{{ url('/admin/templates/'.$t->id.'/delete') }}" onsubmit="return confirm('Remove this template from the list? It stays in Meta until you delete it there.')">@csrf<button class="btn small danger">Remove</button></form>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mute" id="tpl-none" hidden style="text-align:center;margin-top:14px">Nothing in this group.</p>

        <h2>Start from an example</h2>
        <div class="tiles">
            @foreach ($examples as $ex)
                <button type="button" class="tile ex" data-title="{{ $ex[0] }}" data-name="{{ $ex[1] }}" data-cat="{{ $ex[2] }}" data-body="{{ $ex[3] }}"><b>{{ $ex[0] }}</b><span>{{ $ex[3] }}</span></button>
            @endforeach
        </div>

        <dialog id="tpl-dialog" class="dlg wide">
            <button type="button" class="dlg-x" aria-label="Close">&times;</button>
            <h3>New template</h3>
            <p class="mute" style="margin-top:4px">Meta reviews it before you can use it.</p>
            <form method="post" action="{{ url('/admin/templates') }}">
                @csrf
                <div class="grid2" style="margin-top:12px">
                    <label class="f"><span>WhatsApp number</span><select name="channel_id">@foreach ($channels as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
                    <label class="f"><span>Language</span><select name="language">@foreach ($languages as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
                    <label class="f"><span>Name (letters, numbers, underscores)</span><input type="text" name="name" id="tpl-name" value="{{ old('name') }}" placeholder="booking_reminder" required maxlength="80"></label>
                    <label class="f"><span>What is it for?</span><select name="category" id="tpl-cat"><option value="UTILITY">Utility: reminders, confirmations</option><option value="MARKETING">Marketing: offers, win-back</option></select></label>
                </div>
                <label class="f"><span>Message</span><textarea name="body" id="tpl-body" rows="4" required maxlength="1024">{{ old('body') }}</textarea></label>
                <div class="var-row" style="margin:6px 0 14px"><span class="mute">Add a part that changes:</span>
                    <button type="button" class="btn small" data-ph="1">1 · name</button><button type="button" class="btn small" data-ph="2">2 · service</button><button type="button" class="btn small" data-ph="3">3 · date and time</button></div>
                <div class="f"><span>How it will look</span><div class="wa-bubble" id="tpl-preview">Your message appears here.</div></div>
                <div class="dlg-foot"><button class="btn primary" type="submit">Send to Meta for review</button></div>
            </form>
        </dialog>
    @endif
@endsection
@push('scripts')
<script>
(function () {
  var groups = document.querySelectorAll('#tpl-groups a'), cards = document.querySelectorAll('#tpl-list .tpl');
  groups.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault(); var g = a.dataset.group, shown = 0;
      groups.forEach(function (x) { x.classList.toggle('on', x === a); });
      cards.forEach(function (c) { var ok = g === 'all' || c.dataset.group === g; c.hidden = !ok; if (ok) { shown++; } });
      document.getElementById('tpl-none').hidden = shown > 0 || cards.length === 0;
    });
  });

  var dlg = document.getElementById('tpl-dialog');
  if (!dlg) { return; }
  var name = document.getElementById('tpl-name'), body = document.getElementById('tpl-body'), cat = document.getElementById('tpl-cat'), prev = document.getElementById('tpl-preview');
  var sample = { 1: 'Amy', 2: 'Hydrating Facial', 3: 'Tue 10:00' };
  function esc(t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;'); }
  function preview() {
    var t = body.value.trim();
    prev.innerHTML = t ? esc(t).replace(/\{\{(\d+)\}\}/g, function (m, n) { return '<span class="ph">' + (sample[n] || ('Value ' + n)) + '</span>'; }) : 'Your message appears here.';
  }
  body.addEventListener('input', preview);
  name.addEventListener('input', function () { name.value = name.value.toLowerCase().replace(/[^a-z0-9_]+/g, '_'); });
  name.addEventListener('blur', function () { name.value = name.value.replace(/^_+|_+$/g, ''); });
  dlg.querySelectorAll('[data-ph]').forEach(function (b) {
    b.addEventListener('click', function () {
      var s = body.selectionStart == null ? body.value.length : body.selectionStart, e = body.selectionEnd == null ? s : body.selectionEnd;
      body.value = body.value.slice(0, s) + '{{' + b.dataset.ph + '}}' + body.value.slice(e); body.focus(); preview();
    });
  });
  function open(ex) {
    if (ex) { name.value = ex.name; body.value = ex.body; cat.value = ex.cat; }
    preview(); dlg.showModal(); (ex ? body : name).focus();
  }
  document.getElementById('new-tpl').addEventListener('click', function () { open(null); });
  document.querySelectorAll('.ex').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset); }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
  @if ($errors->has('template') || old('body')) open(null); @endif
})();
</script>
@endpush
