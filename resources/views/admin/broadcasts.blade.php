@extends('admin.layout')
@section('title', __('Broadcasts'))
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
        <div><h1>{{ __('Broadcasts') }}</h1><p class="mute">{{ __('Send one message to many customers. Only people who agreed to be contacted, and never sent STOP, can receive it. Up to :n people each time.', ['n' => $limit]) }}</p></div>
        <button type="button" class="btn primary" id="new-bc">{{ __('New broadcast') }}</button>
    </div>

    <div class="kpis main">
        <div class="kpi"><span class="kpi-l">{{ __('Drafts') }}</span><b>{{ $drafts }}</b><span class="kpi-s">{{ __('waiting to be sent') }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Sent') }}</span><b>{{ $sentCount }}</b><span class="kpi-s">{{ __('broadcasts') }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('People reached') }}</span><b>{{ number_format($reached) }}</b><span class="kpi-s">{{ __('messages delivered') }}</span></div>
    </div>

    <div class="table-wrap" style="max-height:none">
    <table class="table">
        <thead><tr><th>{{ __('Broadcast') }}</th><th>{{ __('Status') }}</th><th>{{ __('Result') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($broadcasts as $b)
            @php $n = $audienceCounts[$b->id] ?? 0; $total = $b->sent_count + $b->skipped_count + $b->failed_count; @endphp
            <tr>
                <td><a href="{{ route('broadcasts.show', $b->id) }}"><b>{{ $b->name }}</b></a><br><small class="mute">{{ \Illuminate\Support\Str::limit($b->message['en'] ?? ($b->message['zh'] ?? ''), 90) }}</small></td>
                <td><span class="tag {{ $b->status === 'sent' ? 'ok' : 'warn' }}">{{ $b->status === 'sent' ? __('Sent') : __('Draft') }}</span>@if ($b->sent_at)<br><small class="mute">{{ \App\Support\Lang::fmt($b->sent_at->copy()->setTimezone($business->timezone), 'j M, H:i', 'n月j日 H:i') }}</small>@endif</td>
                <td>
                    @if ($b->status === 'draft')
                        <b>{{ $n }}</b> <span class="mute">{{ $n === 1 ? __('person can receive it') : __('people can receive it') }}</span>
                    @else
                        <div class="mini-split" title="{{ __(':a sent, :b skipped, :c failed', ['a' => $b->sent_count, 'b' => $b->skipped_count, 'c' => $b->failed_count]) }}">
                            @if ($total)<i class="s" style="flex:{{ $b->sent_count }}"></i><i class="k" style="flex:{{ $b->skipped_count }}"></i><i class="f" style="flex:{{ $b->failed_count }}"></i>@endif
                        </div>
                        <small class="mute">{{ __(':a sent', ['a' => $b->sent_count]) }} · {{ __(':a skipped', ['a' => $b->skipped_count]) }} · {{ __(':a failed', ['a' => $b->failed_count]) }}</small>
                    @endif
                </td>
                <td style="text-align:right;white-space:nowrap">
                    @if ($b->status === 'draft')
                        <button type="button" class="btn small primary" data-send data-action="{{ url('/admin/broadcasts/'.$b->id.'/send') }}" data-name="{{ $b->name }}" data-count="{{ $n }}" @disabled($n === 0)>{{ __('Send') }}</button>
                        <form method="post" action="{{ url('/admin/broadcasts/'.$b->id.'/delete') }}" style="display:inline" onsubmit="return confirm({{ json_encode(__('Delete this draft?'), JSON_UNESCAPED_UNICODE) }})">@csrf<button class="btn small danger">{{ __('Delete') }}</button></form>
                    @else
                        <a class="btn small" href="{{ route('broadcasts.show', $b->id) }}">{{ __('Details') }}</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">{{ __('No broadcasts yet. Press "New broadcast" to write your first one. You always check the number of people before anything is sent.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <dialog id="bc-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3>{{ __('New broadcast') }}</h3>
        <form method="post" action="{{ url('/admin/broadcasts') }}" id="bc-form">
            @csrf
            <label class="f" style="margin-top:12px"><span>{{ __('Name (only you see this)') }}</span><input type="text" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="{{ __('Chinese New Year promo') }}">@error('name')<small class="field-err">{{ $message }}</small>@enderror</label>
            <div class="grid2">
                <label class="f"><span>{{ __('Message (English)') }}</span><textarea name="message_en" id="bc-en" rows="4" required maxlength="1000" placeholder="{{ __('Hi {name}, we have a special offer at {business} this week!') }}">{{ old('message_en') }}</textarea><small class="hint"><span data-count-for="bc-en">0</span> / 1000</small>@error('message_en')<small class="field-err">{{ $message }}</small>@enderror</label>
                <label class="f"><span>{{ __('Message (中文, optional)') }}</span><textarea name="message_zh" id="bc-zh" rows="4" maxlength="1000">{{ old('message_zh') }}</textarea><small class="hint"><span data-count-for="bc-zh">0</span> {{ __('/ 1000. Chinese-speaking customers get this one.') }}</small></label>
            </div>
            <div class="bc-vars"><span class="mute">{{ __('Insert:') }}</span><button type="button" class="fchip" data-var="{name}">{{ __('Customer name') }}</button><button type="button" class="fchip" data-var="{business}">{{ __('Business name') }}</button></div>
            <div class="f" style="margin-top:12px"><span>{{ __('Who gets it') }}</span></div>
            <div class="grid2 bc-aud" style="margin-top:0">
                <select name="tag_id" aria-label="{{ __('Tag') }}"><option value="">{{ __('Any tag') }}</option>@foreach ($tags as $t)<option value="{{ $t->id }}" @selected(old('tag_id') == $t->id)>{{ __($t->name) }}</option>@endforeach</select>
                <select name="score" aria-label="{{ __('Lead level') }}"><option value="">{{ __('Any lead level') }}</option>@foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}" @selected(old('score') === $s)>{{ \App\Support\Lang::lead($s) }}</option>@endforeach</select>
                <select name="language" aria-label="{{ __('Language') }}"><option value="">{{ __('Any language') }}</option>@foreach (config('aichat.languages') as $l)<option value="{{ $l }}" @selected(old('language') === $l)>{{ $l === 'zh' ? '中文' : ($l === 'en' ? 'English' : strtoupper($l)) }}</option>@endforeach</select>
            </div>
            <p class="bc-reach" id="bc-reach">{{ __('Counting...') }}</p>
            <details class="bc-adv"><summary>{{ __('Advanced: WhatsApp template (for people who last wrote more than 24 hours ago)') }}</summary>
                <label class="f"><span>{{ __('Approved template name') }}</span><input type="text" name="template_name" value="{{ old('template_name') }}" placeholder="{{ __('approved_marketing_template') }}" maxlength="80">@error('template_name')<small class="field-err">{{ $message }}</small>@enderror</label></details>
            <div class="f"><span>{{ __('How it will look') }}</span><div class="wa-bubble" id="bc-preview">{{ __('Your message appears here.') }}</div></div>
            <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save as draft') }}</button><span class="mute" style="font-size:12px">{{ __('Nothing is sent yet. You confirm on the next screen.') }}</span></div>
        </form>
    </dialog>

    <dialog id="send-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3 id="sd-title">{{ __('Send this broadcast?') }}</h3>
        <p id="sd-text" class="mute" style="margin-top:6px"></p>
        <form method="post" id="sd-form">@csrf<div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Yes, send now') }}</button><button class="btn" type="button" id="sd-cancel">{{ __('Not yet') }}</button></div></form>
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
  function prev() { var t = en.value.trim() || zh.value.trim(); preview.textContent = t ? t.replace(/\{name\}/g, 'Mia').replace(/\{business\}/g, @json($business->name)) : @json(__('Your message appears here.')); }
  function audience() {
    var q = new URLSearchParams({ tag_id: form.tag_id.value, score: form.score.value, language: form.language.value });
    fetch(@json(route('broadcasts.audience')) + '?' + q, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      reach.innerHTML = d.count === 0 ? @json(__('<b>Nobody</b> can receive this yet. Only customers who agreed to be contacted are counted.')) : (d.count === 1 ? @json(__('<b>1</b> person can receive this (of :n customers).')) : @json(__('<b>:c</b> people can receive this (of :n customers).'))).replace(':c', d.count).replace(':n', d.everyone);
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
      document.getElementById('sd-title').textContent = @json(__('Send ":name"?')).replace(':name', b.dataset.name);
      document.getElementById('sd-text').textContent = (n === 1 ? @json(__('It will go to 1 person right now. This cannot be undone.')) : @json(__('It will go to :n people right now. This cannot be undone.')).replace(':n', n));
      document.getElementById('sd-form').action = b.dataset.action; sd.showModal();
    });
  });
  document.getElementById('sd-cancel').addEventListener('click', function () { sd.close(); });
})();
</script>
@endpush
