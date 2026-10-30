@extends('admin.layout')
@section('title', __('Channels'))
@section('content')
    @php
        $types = \App\Models\Channel::TYPES;
        $embed = '<iframe src="'.$chatUrl.'" style="width:380px;height:600px;border:0;border-radius:12px" title="Chat"></iframe>';
        $byType = $channels->groupBy('type');
        $checks = [
            ['ok' => ! $isLocal, 'text' => __('Public web address'), 'fix' => __('This address only works on your own computer. Meta needs a public address: start the free tunnel and set APP_URL to it.')],
            ['ok' => $appIdSet, 'text' => __('Meta app ID'), 'fix' => __('Add META_APP_ID to .env.')],
            ['ok' => $secretSet, 'text' => __('Meta app secret'), 'fix' => __('Add META_APP_SECRET to .env. Without it every message from Meta is rejected, for safety.')],
            ['ok' => $verifyToken !== '', 'text' => __('Verify token'), 'fix' => __('Add META_VERIFY_TOKEN to .env (any long random word).')],
        ];
        $problems = collect($checks)->where('ok', false)->count();
    @endphp
    <div class="page-head"><div><h1>{{ __('Channels') }}</h1><p class="mute">{{ __('Where customers can message you. Everyone lands in the same Inbox.') }}</p></div>
        <button type="button" class="btn primary" id="open-connect">{{ __('+ Connect a channel') }}</button></div>

    @if ($problems && $channels->isEmpty())
        <div class="note warn">{!! __('WhatsApp, Messenger and Instagram need a one-time setup with Meta first (:steps to finish, see :link at the bottom). The website chat works right now.', ['steps' => $problems === 1 ? e(__('1 step')) : e(__(':n steps', ['n' => $problems])), 'link' => '<a href="#meta-setup">'.e(__('Meta app connection')).'</a>']) !!}</div>
    @endif

    <div class="ch-cards">
        <div class="ch-card on">
            <div class="ch-top"><span class="ch-ic web">&#9678;</span><b>{{ __('Website chat') }}</b><span class="tag ok">{{ __('Always on') }}</span></div>
            <p class="mute">{{ __('Works right away. Add it to your website or share the link.') }}</p>
            <a class="btn small" href="#website">{{ __('Get the link') }}</a>
        </div>
        @foreach ($types as $type => $label)
            @php
                $list = $byType[$type] ?? collect();
                $bad = $list->contains(fn ($c) => $c->last_error || ! $c->active);
            @endphp
            <div class="ch-card {{ $list->isEmpty() ? '' : ($bad ? 'warn' : 'on') }}">
                <div class="ch-top"><span class="ch-ic {{ $type }}">{{ strtoupper(substr($type, 0, 1)) }}</span><b>{{ $label }}</b>
                    @if ($list->isEmpty())<span class="tag">{{ __('Not connected') }}</span>
                    @elseif ($bad)<span class="tag warn">{{ __('Needs attention') }}</span>
                    @else<span class="tag ok">{{ __('Connected') }}</span>@endif
                </div>
                <p class="mute">
                    @if ($list->isEmpty()) {{ __('Customers can message you on :channel.', ['channel' => __($label)]) }}
                    @else {{ $list->count() === 1 ? __('1 account') : __(':n accounts', ['n' => $list->count()]) }} ·
                        {{ $list->max('last_inbound_at') ? __('last message :when', ['when' => \Carbon\Carbon::parse($list->max('last_inbound_at'))->diffForHumans()]) : __('no message yet') }}
                    @endif
                </p>
                @if ($list->isEmpty())
                    <a class="btn small primary" href="#connect" data-pick="{{ $type }}">{{ __('Connect') }}</a>
                @elseif ($list->count() === 1)
                    <button type="button" class="btn small {{ $bad ? 'primary' : '' }}" data-open="acct-{{ $list->first()->id }}">{{ $bad ? __('Fix connection') : __('Manage') }}</button>
                @else
                    <a class="btn small" href="#accounts">{{ __('Manage') }}</a>
                @endif
            </div>
        @endforeach
    </div>

    <h2 id="website">{{ __('Website chat') }}</h2>
    <div class="card">
        <div class="grid2">
            <div class="f"><span>{{ __('Link to share') }}</span>
                <div class="copy-row"><input type="text" readonly value="{{ $chatUrl }}" id="chat-link"><button type="button" class="btn small" data-copy="chat-link">{{ __('Copy') }}</button><a class="btn small" href="{{ $chatUrl }}" target="_blank">{{ __('Open') }}</a></div></div>
            <div class="f"><span>{{ __('Add it to your website (paste this where you want the chat)') }}</span>
                <div class="copy-row"><input type="text" readonly value="{{ $embed }}" id="chat-embed"><button type="button" class="btn small" data-copy="chat-embed">{{ __('Copy') }}</button></div></div>
        </div>
    </div>

    <h2 id="accounts">{{ __('Connected accounts') }}</h2>
    @forelse ($channels as $c)
        @php $state = $c->last_error ? 'bad' : (! $c->active ? 'off' : 'ok'); @endphp
        <div class="card acct">
            <div class="acct-head">
                <span class="dot {{ $state }}"></span>
                <div class="acct-title"><b>{{ $c->name }}</b> <span class="tag">{{ $c->label() }}</span></div>
                <span class="mute acct-meta">ID {{ $c->external_id }} · {{ $c->last_inbound_at ? __('last message :when', ['when' => $c->last_inbound_at->diffForHumans()]) : __('no message received yet') }}</span>
                <span class="tag {{ $state === 'ok' ? 'ok' : 'warn' }}">{{ $state === 'ok' ? __('Working') : ($state === 'off' ? __('Switched off') : __('Problem')) }}</span>
            </div>
            @if ($c->last_error)<div class="note err" style="margin:10px 0 0">{{ __('Last problem:') }} {{ $c->last_error }}</div>@endif
            <form method="post" action="{{ url('/admin/channels/'.$c->id) }}">
                @csrf
                <div class="acct-actions">
                    <button class="btn small" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/check') }}">{{ __('Check connection') }}</button>
                    <button class="btn small" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/subscribe') }}">{{ __('Subscribe to messages') }}</button>
                    <button type="button" class="btn small" data-open="acct-{{ $c->id }}">{{ __('Settings') }}</button>
                </div>
            </form>
        <dialog id="acct-{{ $c->id }}" class="dlg wide">
            <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
            <h3>{{ $c->name }} <span class="tag">{{ $c->label() }}</span></h3>
            <form method="post" action="{{ url('/admin/channels/'.$c->id) }}">
                @csrf
                <div class="grid2" style="margin-top:12px">
                        <label class="f"><span>{{ __('Name') }}</span><input type="text" name="name" value="{{ $c->name }}"></label>
                        <label class="f"><span>{{ $c->type === 'instagram' ? 'Linked Facebook Page ID' : ($c->type === 'whatsapp' ? 'WhatsApp Business Account ID' : 'Page ID (not needed)') }}</span>
                            <input type="text" name="waba_id" value="{{ $c->waba_id }}"></label>
                        <label class="f"><span>{{ __('New access token (leave empty to keep the current one)') }}</span><input type="password" name="access_token" autocomplete="off"></label>
                        <label class="f"><span>{{ __('Status') }}</span>
                            <select name="active"><option value="1" @selected($c->active)>{{ __('On') }}</option><option value="0" @selected(! $c->active)>{{ __('Switched off') }}</option></select></label>
                        @if ($c->type === 'whatsapp')
                            <label class="f"><span>{{ __('Booking reminder template (when the 24-hour window is closed)') }}</span>
                                <select name="reminder_template"><option value="">{{ __('None: only tell n8n') }}</option>
                                    @foreach ($c->templates->filter->isApproved() as $t)<option value="{{ $t->name }}" @selected(($c->settings['reminder_template'] ?? '') === $t->name)>{{ $t->name }} ({{ $t->language }})</option>@endforeach
                                </select></label>
                        @endif
                    </div>
                    <div class="dlg-foot">
                        <button class="btn primary small" type="submit">{{ __('Save settings') }}</button>
                        <button class="btn danger small" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/delete') }}" onclick="return confirm({{ json_encode(__('Remove this channel? Chats already received stay in the Inbox.'), JSON_UNESCAPED_UNICODE) }})">{{ __('Remove') }}</button>
                    </div>
                
            </form>
        </dialog>
            @if ($c->type === 'whatsapp')
                <form method="post" action="{{ url('/admin/channels/'.$c->id.'/test') }}" class="test-row">
                    @csrf
                    <input type="text" name="to" placeholder="{{ __('Your own number with country code, e.g. 60123456789') }}">
                    <button class="btn small" type="submit">{{ __('Send me a test message') }}</button>
                </form>
            @endif
        </div>
    @empty
        <div class="card empty-card"><b>{{ __('Nothing connected yet') }}</b><p class="mute">{{ __('The website chat works without any setup. To add WhatsApp, Messenger or Instagram, use "Connect a channel" below.') }}</p></div>
    @endforelse

    <dialog id="connect-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3 id="connect">{{ __('Connect a channel') }}</h3>
        <p class="mute" style="margin-top:2px">{{ __('Pick the app, follow the short steps, then paste the details.') }}</p>
    <form method="post" action="{{ url('/admin/channels') }}">
        @csrf
        <div class="seg" id="type-seg">
            @foreach ($types as $v => $l)<a href="#" data-type="{{ $v }}" @class(['on' => old('type', 'whatsapp') === $v])>{{ $l }}</a>@endforeach
        </div>
        <input type="hidden" name="type" id="ch-type" value="{{ old('type', 'whatsapp') }}">

        <div class="howto" id="howto"></div>

        <div class="grid2">
            <label class="f"><span>{{ __('Name (only you see this)') }}</span><input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('Clinic WhatsApp') }}" required></label>
            <label class="f"><span id="ch-id-label">{{ __('Phone number ID') }}</span><input type="text" name="external_id" value="{{ old('external_id') }}" required></label>
            <label class="f"><span id="ch-extra-label">{{ __('WhatsApp Business Account ID') }}</span><input type="text" name="waba_id" value="{{ old('waba_id') }}"></label>
            <label class="f"><span>{{ __('Access token') }}</span>
                <span class="copy-row"><input type="password" name="access_token" id="tok" autocomplete="off" required><button type="button" class="btn small" id="tok-show">{{ __('Show') }}</button></span></label>
        </div>
        <button class="btn primary" type="submit">{{ __('Connect') }}</button>
        <p class="mute small-note" style="margin-top:8px">{!! __('The token is stored encrypted and is never shown again. After connecting, press :btn on the account.', ['btn' => '<b>'.e(__('Check connection')).'</b>']) !!}</p>
    </form>

    </dialog>

    <details class="card fold" id="meta-setup" @if ($problems) open @endif>
        <summary><span><b>{{ __('Meta app connection') }}</b> {{ __('(advanced, set up once)') }}
            <span class="tag {{ $problems ? 'warn' : 'ok' }}">{{ $problems ? $problems.' to fix' : 'All set' }}</span>
            <small class="mute fold-help">{{ __('The address Meta sends messages to. Needed for WhatsApp, Messenger and Instagram, not for the website chat.') }}</small></span></summary>
        <div style="padding:0 18px 18px">
            <ul class="checks">
                @foreach ($checks as $ch)
                    <li class="{{ $ch['ok'] ? 'ok' : 'bad' }}"><span class="ci">{{ $ch['ok'] ? '✓' : '!' }}</span><span><b>{{ $ch['text'] }}</b>@unless ($ch['ok'])<br><span class="mute">{{ $ch['fix'] }}</span>@endunless</span></li>
                @endforeach
            </ul>
            <div class="grid2">
                <div class="f"><span>{{ __('Callback URL') }}</span><div class="copy-row"><input type="text" readonly value="{{ $webhookUrl }}" id="cb-url"><button type="button" class="btn small" data-copy="cb-url">{{ __('Copy') }}</button></div></div>
                <div class="f"><span>{{ __('Verify token') }}</span><div class="copy-row"><input type="text" readonly value="{{ $verifyToken ?: __('(not set: add META_VERIFY_TOKEN to .env)') }}" id="cb-token"><button type="button" class="btn small" data-copy="cb-token">{{ __('Copy') }}</button></div></div>
            </div>
            <form method="post" action="{{ url('/admin/channels/webhook') }}" class="bar" style="margin-top:12px">
                @csrf
                <button class="btn primary small" type="submit">{{ __('Save these in Meta for me') }}</button>
                <span class="mute small-note">{{ __('Needs all four checks above to be green. Or paste the two values yourself in your Meta app under WhatsApp (or Messenger) > Configuration.') }}</span>
            </form>
        </div>
    </details>
@endsection
@push('scripts')
<script>
(function () {
  var info = {
    whatsapp: { id: @json(__('Phone number ID')), extra: @json(__('WhatsApp Business Account ID')), steps: [@json(__('In your Meta app open <b>WhatsApp &gt; API Setup</b>.')), @json(__('Copy the <b>Phone number ID</b> and the <b>WhatsApp Business Account ID</b> shown there.')), @json(__('Create a token for this number (a permanent one under Business settings &gt; System users) and paste it below.'))] },
    messenger: { id: @json(__('Facebook Page ID')), extra: @json(__('Not needed')), steps: [@json(__('In your Meta app add the <b>Messenger</b> product and connect your Facebook Page.')), @json(__('Copy the <b>Page ID</b> (Page &gt; About).')), @json(__('Generate a <b>Page access token</b> and paste it below.'))] },
    instagram: { id: @json(__('Instagram account ID')), extra: @json(__('Linked Facebook Page ID')), steps: [@json(__('Your Instagram must be a Business or Creator account linked to a Facebook Page.')), @json(__('In your Meta app add <b>Instagram messaging</b> and copy the Instagram account ID and the linked Page ID.')), @json(__('Generate an access token for the Page and paste it below.'))] }
  };
  var hidden = document.getElementById('ch-type');
  function pick(type) {
    hidden.value = type;
    document.querySelectorAll('#type-seg a').forEach(function (a) { a.classList.toggle('on', a.dataset.type === type); });
    var i = info[type];
    document.getElementById('ch-id-label').textContent = i.id;
    document.getElementById('ch-extra-label').textContent = i.extra;
    document.getElementById('howto').innerHTML = '<b>' + @json(__('Where to find these')) + '</b><ol>' + i.steps.map(function (s) { return '<li>' + s + '</li>'; }).join('') + '</ol>';
  }
  document.querySelectorAll('#type-seg a').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); pick(a.dataset.type); }); });
  document.querySelectorAll('[data-pick]').forEach(function (a) { a.addEventListener('click', function () { pick(a.dataset.pick); }); });
  pick(hidden.value || 'whatsapp');

  // Pop-ups: connect a channel, and each account's settings.
  document.querySelectorAll('dialog.dlg').forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('.dlg-x')) { d.close(); } }); });
  var cd = document.getElementById('connect-dialog');
  document.getElementById('open-connect').addEventListener('click', function () { cd.showModal(); });
  document.querySelectorAll('[data-pick]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); cd.showModal(); }); });
  document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
  @if ($errors->has('type') || $errors->has('external_id') || $errors->has('access_token') || $errors->has('name')) cd.showModal(); @endif

  // Say what happened right where you are looking, whatever the scroll position (a note at the top of the page is easy to miss).
  var said = @json($errors->has('channel') ? $errors->first('channel') : (string) session('status')), bad = @json($errors->has('channel'));
  if (said) {
    var t = document.createElement('div'); t.className = 'toast' + (bad ? ' bad' : ''); t.textContent = said; t.style.maxWidth = '92vw'; t.style.zIndex = 60; t.style.cursor = 'pointer';
    document.body.appendChild(t); t.addEventListener('click', function () { t.remove(); }); setTimeout(function () { t.remove(); }, bad ? 15000 : 9000);
  }

  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.dataset.copy);
      var done = function () { var t = b.textContent; b.textContent = @json(__('Copied')); setTimeout(function () { b.textContent = t; }, 1400); };
      if (navigator.clipboard) { navigator.clipboard.writeText(el.value).then(done).catch(function () { el.select(); document.execCommand('copy'); done(); }); }
      else { el.select(); document.execCommand('copy'); done(); }
    });
  });
  var tok = document.getElementById('tok'), show = document.getElementById('tok-show');
  show.addEventListener('click', function () { tok.type = tok.type === 'password' ? 'text' : 'password'; show.textContent = tok.type === 'password' ? @json(__('Show')) : @json(__('Hide')); });
})();
</script>
@endpush
