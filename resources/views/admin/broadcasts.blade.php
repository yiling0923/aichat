@extends('admin.layout')
@section('title', 'Broadcasts')
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'broadcasts'])
    @php
        $levels = config('aichat.lead_levels');
        $drafts = $broadcasts->where('status', 'draft')->count();
        $sentCount = $broadcasts->where('status', 'sent')->count();
        $reached = $broadcasts->where('status', 'sent')->sum('sent_count');
        $reopen = $errors->any();
    @endphp
    <div class="page-head">
        <div><h1>Broadcasts</h1><p class="mute">Send one message to many customers. Only people who agreed to be contacted, and never sent STOP, can receive it. Up to {{ $limit }} people each time.</p></div>
        <button type="button" class="btn primary" id="new-bc">New broadcast</button>
    </div>

    <div class="kpis main">
        <div class="kpi"><span class="kpi-l">Drafts</span><b>{{ $drafts }}</b><span class="kpi-s">waiting to be sent</span></div>
        <div class="kpi"><span class="kpi-l">Sent</span><b>{{ $sentCount }}</b><span class="kpi-s">broadcasts</span></div>
        <div class="kpi"><span class="kpi-l">People reached</span><b>{{ number_format($reached) }}</b><span class="kpi-s">messages delivered</span></div>
    </div>

    <div class="table-wrap" style="max-height:none">
    <table class="table">
        <thead><tr><th>Broadcast</th><th>Status</th><th>Result</th><th></th></tr></thead>
        <tbody>
        @forelse ($broadcasts as $b)
            @php $n = $audienceCounts[$b->id] ?? 0; $total = $b->sent_count + $b->skipped_count + $b->failed_count; @endphp
            <tr>
                <td><a href="{{ route('broadcasts.show', $b->id) }}"><b>{{ $b->name }}</b></a><br><small class="mute">{{ \Illuminate\Support\Str::limit($b->message['en'] ?? ($b->message['zh'] ?? ''), 90) }}</small></td>
                <td><span class="tag {{ $b->status === 'sent' ? 'ok' : 'warn' }}">{{ $b->status === 'sent' ? 'Sent' : 'Draft' }}</span>@if ($b->sent_at)<br><small class="mute">{{ $b->sent_at->copy()->setTimezone($business->timezone)->format('j M, H:i') }}</small>@endif</td>
                <td>
                    @if ($b->status === 'draft')
                        <b>{{ $n }}</b> <span class="mute">{{ $n === 1 ? 'person' : 'people' }} can receive it</span>
                    @else
                        <div class="mini-split" title="{{ $b->sent_count }} sent, {{ $b->skipped_count }} skipped, {{ $b->failed_count }} failed">
                            @if ($total)<i class="s" style="flex:{{ $b->sent_count }}"></i><i class="k" style="flex:{{ $b->skipped_count }}"></i><i class="f" style="flex:{{ $b->failed_count }}"></i>@endif
                        </div>
                        <small class="mute">{{ $b->sent_count }} sent · {{ $b->skipped_count }} skipped · {{ $b->failed_count }} failed</small>
                    @endif
                </td>
                <td style="text-align:right;white-space:nowrap">
                    @if ($b->status === 'draft')
                        <button type="button" class="btn small primary" data-send data-action="{{ url('/admin/broadcasts/'.$b->id.'/send') }}" data-name="{{ $b->name }}" data-count="{{ $n }}" @disabled($n === 0)>Send</button>
                        <form method="post" action="{{ url('/admin/broadcasts/'.$b->id.'/delete') }}" style="display:inline" onsubmit="return confirm('Delete this draft?')">@csrf<button class="btn small danger">Delete</button></form>
                    @else
                        <a class="btn small" href="{{ route('broadcasts.show', $b->id) }}">Details</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">No broadcasts yet. Press "New broadcast" to write your first one. You always check the number of people before anything is sent.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <dialog id="bc-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3>New broadcast</h3>
        <form method="post" action="{{ url('/admin/broadcasts') }}" id="bc-form">
            @csrf
            <label class="f" style="margin-top:12px"><span>Name (only you see this)</span><input type="text" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Chinese New Year promo">@error('name')<small class="field-err">{{ $message }}</small>@enderror</label>
            <div class="grid2">
                <label class="f"><span>Message (English)</span><textarea name="message_en" id="bc-en" rows="4" required maxlength="1000" placeholder="Hi {name}, we have a special offer at {business} this week!">{{ old('message_en') }}</textarea><small class="hint"><span data-count-for="bc-en">0</span> / 1000</small>@error('message_en')<small class="field-err">{{ $message }}</small>@enderror</label>
                <label class="f"><span>Message (中文, optional)</span><textarea name="message_zh" id="bc-zh" rows="4" maxlength="1000">{{ old('message_zh') }}</textarea><small class="hint"><span data-count-for="bc-zh">0</span> / 1000. Chinese-speaking customers get this one.</small></label>
            </div>
            <div class="bc-vars"><span class="mute">Insert:</span><button type="button" class="fchip" data-var="{name}">Customer name</button><button type="button" class="fchip" data-var="{business}">Business name</button></div>
            <div class="f" style="margin-top:12px"><span>Who gets it</span></div>
            <div class="grid2 bc-aud" style="margin-top:0">
                <select name="tag_id" aria-label="Tag"><option value="">Any tag</option>@foreach ($tags as $t)<option value="{{ $t->id }}" @selected(old('tag_id') == $t->id)>{{ $t->name }}</option>@endforeach</select>
                <select name="score" aria-label="Lead level"><option value="">Any lead level</option>@foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}" @selected(old('score') === $s)>{{ $levels[$s] }}</option>@endforeach</select>
                <select name="language" aria-label="Language"><option value="">Any language</option>@foreach (config('aichat.languages') as $l)<option value="{{ $l }}" @selected(old('language') === $l)>{{ strtoupper($l) }}</option>@endforeach</select>
            </div>
            <p class="bc-reach" id="bc-reach">Counting...</p>
            <details class="bc-adv"><summary>Advanced: WhatsApp template (for people who last wrote more than 24 hours ago)</summary>
                <label class="f"><span>Approved template name</span><input type="text" name="template_name" value="{{ old('template_name') }}" placeholder="approved_marketing_template" maxlength="80">@error('template_name')<small class="field-err">{{ $message }}</small>@enderror</label></details>
            <div class="f"><span>How it will look</span><div class="wa-bubble" id="bc-preview">Your message appears here.</div></div>
            <div class="dlg-foot"><button class="btn primary" type="submit">Save as draft</button><span class="mute" style="font-size:12px">Nothing is sent yet. You confirm on the next screen.</span></div>
        </form>
    </dialog>

    <dialog id="send-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3 id="sd-title">Send this broadcast?</h3>
        <p id="sd-text" class="mute" style="margin-top:6px"></p>
        <form method="post" id="sd-form">@csrf<div class="dlg-foot"><button class="btn primary" type="submit">Yes, send now</button><button class="btn" type="button" id="sd-cancel">Not yet</button></div></form>
    </dialog>
@endsection
@push('scripts')
<script>
(function () {
  function dlg(id) { var d = document.getElementById(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('.dlg-x')) { d.close(); } }); return d; }
  var bc = dlg('bc-dialog'), sd = dlg('send-dialog');
  var form = document.getElementById('bc-form'), en = document.getElementById('bc-en'), zh = document.getElementById('bc-zh');
  var reach = document.getElementById('bc-reach'), preview = document.getElementById('bc-preview'), timer = null;

  function counts() { document.querySelectorAll('[data-count-for]').forEach(function (s) { s.textContent = document.getElementById(s.dataset.countFor).value.length; }); }
  function prev() { var t = en.value.trim() || zh.value.trim(); preview.textContent = t ? t.replace(/\{name\}/g, 'Mia').replace(/\{business\}/g, @json($business->name)) : 'Your message appears here.'; }
  function audience() {
    var q = new URLSearchParams({ tag_id: form.tag_id.value, score: form.score.value, language: form.language.value });
    fetch(@json(route('broadcasts.audience')) + '?' + q, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      reach.innerHTML = d.count === 0 ? '<b>Nobody</b> can receive this yet. Only customers who agreed to be contacted are counted.' : '<b>' + d.count + '</b> ' + (d.count === 1 ? 'person' : 'people') + ' can receive this (of ' + d.everyone + ' customers).';
      reach.classList.toggle('none', d.count === 0);
    }).catch(function () { reach.textContent = ''; });
  }
  [en, zh].forEach(function (t) { t.addEventListener('input', function () { counts(); prev(); }); });
  form.querySelectorAll('.bc-aud select').forEach(function (s) { s.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(audience, 150); }); });
  document.querySelectorAll('[data-var]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = document.activeElement === zh ? zh : en, p = t.selectionStart || t.value.length;
      t.value = t.value.slice(0, p) + b.dataset.var + t.value.slice(t.selectionEnd || p); t.focus(); t.selectionStart = t.selectionEnd = p + b.dataset.var.length; counts(); prev();
    });
  });
  document.getElementById('new-bc').addEventListener('click', function () { counts(); prev(); audience(); bc.showModal(); en.value ? null : form.name.focus(); });
  @if ($reopen) counts(); prev(); audience(); bc.showModal(); @endif

  document.querySelectorAll('[data-send]').forEach(function (b) {
    b.addEventListener('click', function () {
      var n = parseInt(b.dataset.count, 10);
      document.getElementById('sd-title').textContent = 'Send "' + b.dataset.name + '"?';
      document.getElementById('sd-text').textContent = 'It will go to ' + n + (n === 1 ? ' person' : ' people') + ' right now. This cannot be undone.';
      document.getElementById('sd-form').action = b.dataset.action; sd.showModal();
    });
  });
  document.getElementById('sd-cancel').addEventListener('click', function () { sd.close(); });
})();
</script>
@endpush
