@extends('admin.layout')
@section('title', 'Channels')
@section('content')
    @php
        $types = \App\Models\Channel::TYPES;
        $embed = '<iframe src="'.$chatUrl.'" style="width:380px;height:600px;border:0;border-radius:12px" title="Chat"></iframe>';
        $byType = $channels->groupBy('type');
        $checks = [
            ['ok' => ! $isLocal, 'text' => 'Public web address', 'fix' => 'This address only works on your own computer. Meta needs a public address: start the free tunnel and set APP_URL to it.'],
            ['ok' => $appIdSet, 'text' => 'Meta app ID', 'fix' => 'Add META_APP_ID to .env.'],
            ['ok' => $secretSet, 'text' => 'Meta app secret', 'fix' => 'Add META_APP_SECRET to .env. Without it every message from Meta is rejected, for safety.'],
            ['ok' => $verifyToken !== '', 'text' => 'Verify token', 'fix' => 'Add META_VERIFY_TOKEN to .env (any long random word).'],
        ];
        $problems = collect($checks)->where('ok', false)->count();
    @endphp
    <div class="page-head"><div><h1>Channels</h1><p class="mute">Where customers can message you. Everyone lands in the same Inbox.</p></div>
        <button type="button" class="btn primary" id="open-connect">+ Connect a channel</button></div>
    @error('channel')<div class="note err">{{ $message }}</div>@enderror
    @error('type')<div class="note err">{{ $message }}</div>@enderror

    @if ($problems && $channels->isEmpty())
        <div class="note warn">WhatsApp, Messenger and Instagram need a one-time setup with Meta first ({{ $problems }} {{ $problems === 1 ? 'step' : 'steps' }} to finish, see <a href="#meta-setup">Meta app connection</a> at the bottom). The website chat works right now.</div>
    @endif

    <div class="ch-cards">
        <div class="ch-card on">
            <div class="ch-top"><span class="ch-ic web">&#9678;</span><b>Website chat</b><span class="tag ok">Always on</span></div>
            <p class="mute">Works right away. Add it to your website or share the link.</p>
            <a class="btn small" href="#website">Get the link</a>
        </div>
        @foreach ($types as $type => $label)
            @php
                $list = $byType[$type] ?? collect();
                $bad = $list->contains(fn ($c) => $c->last_error || ! $c->active);
            @endphp
            <div class="ch-card {{ $list->isEmpty() ? '' : ($bad ? 'warn' : 'on') }}">
                <div class="ch-top"><span class="ch-ic {{ $type }}">{{ strtoupper(substr($type, 0, 1)) }}</span><b>{{ $label }}</b>
                    @if ($list->isEmpty())<span class="tag">Not connected</span>
                    @elseif ($bad)<span class="tag warn">Needs attention</span>
                    @else<span class="tag ok">Connected</span>@endif
                </div>
                <p class="mute">
                    @if ($list->isEmpty()) Customers can message you on {{ $label }}.
                    @else {{ $list->count() }} {{ \Illuminate\Support\Str::plural('account', $list->count()) }} ·
                        {{ $list->max('last_inbound_at') ? 'last message '.\Carbon\Carbon::parse($list->max('last_inbound_at'))->diffForHumans() : 'no message yet' }}
                    @endif
                </p>
                <a class="btn small {{ $list->isEmpty() ? 'primary' : '' }}" href="#{{ $list->isEmpty() ? 'connect' : 'accounts' }}" @if ($list->isEmpty()) data-pick="{{ $type }}" @endif>{{ $list->isEmpty() ? 'Connect' : 'Manage' }}</a>
            </div>
        @endforeach
    </div>

    <h2 id="website">Website chat</h2>
    <div class="card">
        <div class="grid2">
            <div class="f"><span>Link to share</span>
                <div class="copy-row"><input type="text" readonly value="{{ $chatUrl }}" id="chat-link"><button type="button" class="btn small" data-copy="chat-link">Copy</button><a class="btn small" href="{{ $chatUrl }}" target="_blank">Open</a></div></div>
            <div class="f"><span>Add it to your website (paste this where you want the chat)</span>
                <div class="copy-row"><input type="text" readonly value="{{ $embed }}" id="chat-embed"><button type="button" class="btn small" data-copy="chat-embed">Copy</button></div></div>
        </div>
    </div>

    <h2 id="accounts">Connected accounts</h2>
    @forelse ($channels as $c)
        @php $state = $c->last_error ? 'bad' : (! $c->active ? 'off' : 'ok'); @endphp
        <div class="card acct">
            <div class="acct-head">
                <span class="dot {{ $state }}"></span>
                <div class="acct-title"><b>{{ $c->name }}</b> <span class="tag">{{ $c->label() }}</span></div>
                <span class="mute acct-meta">ID {{ $c->external_id }} · {{ $c->last_inbound_at ? 'last message '.$c->last_inbound_at->diffForHumans() : 'no message received yet' }}</span>
                <span class="tag {{ $state === 'ok' ? 'ok' : 'warn' }}">{{ $state === 'ok' ? 'Working' : ($state === 'off' ? 'Switched off' : 'Problem') }}</span>
            </div>
            @if ($c->last_error)<div class="note err" style="margin:10px 0 0">Last problem: {{ $c->last_error }}</div>@endif
            <form method="post" action="{{ url('/admin/channels/'.$c->id) }}">
                @csrf
                <div class="acct-actions">
                    <button class="btn small" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/check') }}">Check connection</button>
                    <button class="btn small" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/subscribe') }}">Subscribe to messages</button>
                    <button type="button" class="btn small" data-open="acct-{{ $c->id }}">Settings</button>
                </div>
            </form>
        <dialog id="acct-{{ $c->id }}" class="dlg wide">
            <button type="button" class="dlg-x" aria-label="Close">&times;</button>
            <h3>{{ $c->name }} <span class="tag">{{ $c->label() }}</span></h3>
            <form method="post" action="{{ url('/admin/channels/'.$c->id) }}">
                @csrf
                <div class="grid2" style="margin-top:12px">
                        <label class="f"><span>Name</span><input type="text" name="name" value="{{ $c->name }}"></label>
                        <label class="f"><span>{{ $c->type === 'instagram' ? 'Linked Facebook Page ID' : ($c->type === 'whatsapp' ? 'WhatsApp Business Account ID' : 'Page ID (not needed)') }}</span>
                            <input type="text" name="waba_id" value="{{ $c->waba_id }}"></label>
                        <label class="f"><span>New access token (leave empty to keep the current one)</span><input type="password" name="access_token" autocomplete="off"></label>
                        <label class="f"><span>Status</span>
                            <select name="active"><option value="1" @selected($c->active)>On</option><option value="0" @selected(! $c->active)>Switched off</option></select></label>
                        @if ($c->type === 'whatsapp')
                            <label class="f"><span>Booking reminder template (when the 24-hour window is closed)</span>
                                <select name="reminder_template"><option value="">None: only tell n8n</option>
                                    @foreach ($c->templates->filter->isApproved() as $t)<option value="{{ $t->name }}" @selected(($c->settings['reminder_template'] ?? '') === $t->name)>{{ $t->name }} ({{ $t->language }})</option>@endforeach
                                </select></label>
                        @endif
                    </div>
                    <div class="dlg-foot">
                        <button class="btn primary small" type="submit">Save settings</button>
                        <button class="btn danger small" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/delete') }}" onclick="return confirm('Remove this channel? Chats already received stay in the Inbox.')">Remove</button>
                    </div>
                
            </form>
        </dialog>
            @if ($c->type === 'whatsapp')
                <form method="post" action="{{ url('/admin/channels/'.$c->id.'/test') }}" class="test-row">
                    @csrf
                    <input type="text" name="to" placeholder="Your own number with country code, e.g. 60123456789">
                    <button class="btn small" type="submit">Send me a test message</button>
                </form>
            @endif
        </div>
    @empty
        <div class="card empty-card"><b>Nothing connected yet</b><p class="mute">The website chat works without any setup. To add WhatsApp, Messenger or Instagram, use "Connect a channel" below.</p></div>
    @endforelse

    <dialog id="connect-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3 id="connect">Connect a channel</h3>
        <p class="mute" style="margin-top:2px">Pick the app, follow the short steps, then paste the details.</p>
    <form method="post" action="{{ url('/admin/channels') }}">
        @csrf
        <div class="seg" id="type-seg">
            @foreach ($types as $v => $l)<a href="#" data-type="{{ $v }}" @class(['on' => old('type', 'whatsapp') === $v])>{{ $l }}</a>@endforeach
        </div>
        <input type="hidden" name="type" id="ch-type" value="{{ old('type', 'whatsapp') }}">

        <div class="howto" id="howto"></div>

        <div class="grid2">
            <label class="f"><span>Name (only you see this)</span><input type="text" name="name" value="{{ old('name') }}" placeholder="Clinic WhatsApp" required></label>
            <label class="f"><span id="ch-id-label">Phone number ID</span><input type="text" name="external_id" value="{{ old('external_id') }}" required></label>
            <label class="f"><span id="ch-extra-label">WhatsApp Business Account ID</span><input type="text" name="waba_id" value="{{ old('waba_id') }}"></label>
            <label class="f"><span>Access token</span>
                <span class="copy-row"><input type="password" name="access_token" id="tok" autocomplete="off" required><button type="button" class="btn small" id="tok-show">Show</button></span></label>
        </div>
        <button class="btn primary" type="submit">Connect</button>
        <p class="mute small-note" style="margin-top:8px">The token is stored encrypted and is never shown again. After connecting, press <b>Check connection</b> on the account.</p>
    </form>

    </dialog>

    <details class="card fold" id="meta-setup" @if ($problems) open @endif>
        <summary><span><b>Meta app connection</b> (advanced, set up once)
            <span class="tag {{ $problems ? 'warn' : 'ok' }}">{{ $problems ? $problems.' to fix' : 'All set' }}</span>
            <small class="mute fold-help">The address Meta sends messages to. Needed for WhatsApp, Messenger and Instagram, not for the website chat.</small></span></summary>
        <div style="padding:0 18px 18px">
            <ul class="checks">
                @foreach ($checks as $ch)
                    <li class="{{ $ch['ok'] ? 'ok' : 'bad' }}"><span class="ci">{{ $ch['ok'] ? '✓' : '!' }}</span><span><b>{{ $ch['text'] }}</b>@unless ($ch['ok'])<br><span class="mute">{{ $ch['fix'] }}</span>@endunless</span></li>
                @endforeach
            </ul>
            <div class="grid2">
                <div class="f"><span>Callback URL</span><div class="copy-row"><input type="text" readonly value="{{ $webhookUrl }}" id="cb-url"><button type="button" class="btn small" data-copy="cb-url">Copy</button></div></div>
                <div class="f"><span>Verify token</span><div class="copy-row"><input type="text" readonly value="{{ $verifyToken ?: '(not set: add META_VERIFY_TOKEN to .env)' }}" id="cb-token"><button type="button" class="btn small" data-copy="cb-token">Copy</button></div></div>
            </div>
            <form method="post" action="{{ url('/admin/channels/webhook') }}" class="bar" style="margin-top:12px">
                @csrf
                <button class="btn primary small" type="submit">Save these in Meta for me</button>
                <span class="mute small-note">Needs all four checks above to be green. Or paste the two values yourself in your Meta app under WhatsApp (or Messenger) &gt; Configuration.</span>
            </form>
        </div>
    </details>
@endsection
@push('scripts')
<script>
(function () {
  var info = {
    whatsapp: { id: 'Phone number ID', extra: 'WhatsApp Business Account ID', steps: ['In your Meta app open <b>WhatsApp &gt; API Setup</b>.', 'Copy the <b>Phone number ID</b> and the <b>WhatsApp Business Account ID</b> shown there.', 'Create a token for this number (a permanent one under Business settings &gt; System users) and paste it below.'] },
    messenger: { id: 'Facebook Page ID', extra: 'Not needed', steps: ['In your Meta app add the <b>Messenger</b> product and connect your Facebook Page.', 'Copy the <b>Page ID</b> (Page &gt; About).', 'Generate a <b>Page access token</b> and paste it below.'] },
    instagram: { id: 'Instagram account ID', extra: 'Linked Facebook Page ID', steps: ['Your Instagram must be a Business or Creator account linked to a Facebook Page.', 'In your Meta app add <b>Instagram messaging</b> and copy the Instagram account ID and the linked Page ID.', 'Generate an access token for the Page and paste it below.'] }
  };
  var hidden = document.getElementById('ch-type');
  function pick(type) {
    hidden.value = type;
    document.querySelectorAll('#type-seg a').forEach(function (a) { a.classList.toggle('on', a.dataset.type === type); });
    var i = info[type];
    document.getElementById('ch-id-label').textContent = i.id;
    document.getElementById('ch-extra-label').textContent = i.extra;
    document.getElementById('howto').innerHTML = '<b>Where to find these</b><ol>' + i.steps.map(function (s) { return '<li>' + s + '</li>'; }).join('') + '</ol>';
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
  @if ($errors->has('channel') || $errors->has('type') || $errors->has('external_id') || $errors->has('access_token') || $errors->has('name')) cd.showModal(); @endif

  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.dataset.copy);
      var done = function () { var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1400); };
      if (navigator.clipboard) { navigator.clipboard.writeText(el.value).then(done).catch(function () { el.select(); document.execCommand('copy'); done(); }); }
      else { el.select(); document.execCommand('copy'); done(); }
    });
  });
  var tok = document.getElementById('tok'), show = document.getElementById('tok-show');
  show.addEventListener('click', function () { tok.type = tok.type === 'password' ? 'text' : 'password'; show.textContent = tok.type === 'password' ? 'Show' : 'Hide'; });
})();
</script>
@endpush
