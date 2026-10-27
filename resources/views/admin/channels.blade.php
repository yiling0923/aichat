@extends('admin.layout')
@section('title', 'Channels')
@section('content')
    <h1>Channels</h1>
    <p class="mute">Connect WhatsApp, Messenger and Instagram so customers can chat with the bot there. The full guide is in the README (Step 4).</p>

    @error('channel')<div class="note err">{{ $message }}</div>@enderror

    <section class="card">
        <h3>1. Tell Meta where to send messages</h3>
        <p class="mute small-note">In your Meta app, open WhatsApp (or Messenger) &gt; Configuration, and paste these two values.</p>
        <div class="grid2">
            <label class="f"><span>Callback URL</span><input type="text" readonly value="{{ $webhookUrl }}" onclick="this.select()"></label>
            <label class="f"><span>Verify token</span><input type="text" readonly value="{{ $verifyToken ?: '(not set: add META_VERIFY_TOKEN to .env)' }}" onclick="this.select()"></label>
        </div>
        @if ($isLocal)
            <div class="note warn">This address only works on your own computer. Meta needs a public address: start a free tunnel (ngrok) and set <code>APP_URL</code> in <code>.env</code> to the tunnel address, then run <code>php artisan config:clear</code>.</div>
        @endif
        @unless ($secretSet)
            <div class="note err">META_APP_SECRET is not set in <code>.env</code>. Without it every message from Meta is rejected, for safety.</div>
        @endunless
    </section>

    <h2>2. Connected accounts</h2>
    @forelse ($channels as $c)
        <form method="post" action="{{ url('/admin/channels/'.$c->id) }}" class="card">
            @csrf
            <div class="bar spread">
                <div><b>{{ $c->name }}</b> <span class="tag">{{ $c->label() }}</span>
                    <span class="mute"> id {{ $c->external_id }}</span>
                    @if ($c->last_inbound_at)<span class="mute"> · last message {{ $c->last_inbound_at->diffForHumans() }}</span>@else<span class="mute"> · no message received yet</span>@endif
                </div>
                <span class="tag {{ $c->active ? 'ok' : 'warn' }}">{{ $c->active ? 'on' : 'switched off' }}</span>
            </div>
            @if ($c->last_error)<div class="note err">Last problem: {{ $c->last_error }}</div>@endif
            <div class="grid2">
                <label class="f"><span>Name</span><input type="text" name="name" value="{{ $c->name }}"></label>
                <label class="f"><span>{{ $c->type === 'instagram' ? 'Linked Facebook Page ID' : ($c->type === 'whatsapp' ? 'WhatsApp Business Account ID' : 'Page ID (not needed)') }}</span>
                    <input type="text" name="waba_id" value="{{ $c->waba_id }}"></label>
                <label class="f"><span>New access token (leave empty to keep)</span><input type="password" name="access_token" autocomplete="off"></label>
                <label class="f"><span>Status</span>
                    <select name="active"><option value="1" @selected($c->active)>On</option><option value="0" @selected(! $c->active)>Switched off</option></select></label>
                @if ($c->type === 'whatsapp')
                    <label class="f"><span>Booking reminder template (when the 24-hour window is closed)</span>
                        <select name="reminder_template"><option value="">None: only notify n8n</option>
                            @foreach ($c->templates->filter->isApproved() as $t)<option value="{{ $t->name }}" @selected(($c->settings['reminder_template'] ?? '') === $t->name)>{{ $t->name }} ({{ $t->language }})</option>@endforeach
                        </select></label>
                @endif
            </div>
            <div class="bar">
                <button class="btn primary" type="submit">Save</button>
                <button class="btn" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/check') }}">Check connection</button>
                <button class="btn" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/subscribe') }}">Subscribe to messages</button>
                <button class="btn danger" type="submit" formaction="{{ url('/admin/channels/'.$c->id.'/delete') }}" onclick="return confirm('Remove this channel?')">Remove</button>
            </div>
            @if ($c->type === 'whatsapp')
                <div class="bar">
                    <input type="text" name="to" placeholder="Your phone number with country code, e.g. 60123456789" style="max-width:340px" form="test-{{ $c->id }}">
                    <button class="btn" type="submit" form="test-{{ $c->id }}">Send a test message</button>
                </div>
            @endif
        </form>
        @if ($c->type === 'whatsapp')
            <form id="test-{{ $c->id }}" method="post" action="{{ url('/admin/channels/'.$c->id.'/test') }}">@csrf</form>
        @endif
    @empty
        <div class="card mute">Nothing connected yet. Add your first account below.</div>
    @endforelse

    <h2>3. Add an account</h2>
    <form method="post" action="{{ url('/admin/channels') }}" class="card">
        @csrf
        <div class="grid2">
            <label class="f"><span>Channel</span>
                <select name="type" id="ch-type">@foreach (\App\Models\Channel::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('type') === $v)>{{ $l }}</option>@endforeach</select></label>
            <label class="f"><span>Name (for you)</span><input type="text" name="name" value="{{ old('name') }}" placeholder="Clinic WhatsApp" required></label>
            <label class="f"><span id="ch-id-label">Phone number ID</span><input type="text" name="external_id" value="{{ old('external_id') }}" required></label>
            <label class="f"><span id="ch-extra-label">WhatsApp Business Account ID</span><input type="text" name="waba_id" value="{{ old('waba_id') }}"></label>
            <label class="f"><span>Access token</span><input type="password" name="access_token" autocomplete="off" required></label>
        </div>
        <button class="btn primary" type="submit">Add channel</button>
        <p class="mute small-note" style="margin-top:8px">The token is stored encrypted and is never shown again.</p>
    </form>
@endsection
@push('scripts')
<script>
(function () {
  var type = document.getElementById('ch-type');
  var labels = {
    whatsapp: ['Phone number ID', 'WhatsApp Business Account ID'],
    messenger: ['Facebook Page ID', 'Not needed'],
    instagram: ['Instagram account ID', 'Linked Facebook Page ID']
  };
  function sync() {
    var l = labels[type.value];
    document.getElementById('ch-id-label').textContent = l[0];
    document.getElementById('ch-extra-label').textContent = l[1];
  }
  type.addEventListener('change', sync); sync();
})();
</script>
@endpush
