@extends('admin.layout')
@section('title', 'Business and team')
@section('content')
    @php
        $accent = $business->settings['accent'] ?? '#0f766e';
        $currency = $business->settings['currency'] ?? 'RM';
        $roleHelp = ['owner' => 'Everything, including billing, channels and the team.', 'manager' => 'Saved replies, tags, marketing, AI and templates, plus everything staff can do.', 'staff' => 'Inbox, schedule, customers, tasks, orders and reports.'];
        $roleTone = ['owner' => 'ok', 'manager' => 'warn', 'staff' => ''];
    @endphp
    <div class="page-head">
        <div><h1>Business and team</h1><p class="mute">Your business details and who can sign in. Prices and services are in Booking setup; what the bot says is in the Menu builder.</p></div>
        <div class="seg" id="set-tabs">
            <a href="{{ route('settings') }}" data-tab="business" @class(['on' => $tab === 'business'])>Business</a>
            <a href="{{ route('settings', ['tab' => 'team']) }}" data-tab="team" @class(['on' => $tab === 'team'])>Team ({{ $team->count() }})</a>
        </div>
    </div>

    <section data-panel="business" @if ($tab !== 'business') hidden @endif>
        <form method="post" action="{{ url('/admin/settings/profile') }}" class="card">
            @csrf
            <h3>About your business</h3>
            <div class="grid2" style="margin-top:12px">
                <label class="f"><span>Business name</span><input type="text" name="name" value="{{ old('name', $business->name) }}" required maxlength="120"></label>
                <label class="f"><span>Address (used when customers ask where you are)</span><input type="text" name="address" value="{{ old('address', $business->settings['address'] ?? '') }}" maxlength="200" placeholder="12 Jalan Example, Kuala Lumpur"></label>
                <label class="f"><span>Time zone</span>
                    <select name="timezone">@foreach ($timezones as $group => $zones)<optgroup label="{{ $group }}">@foreach ($zones as $z)<option value="{{ $z }}" @selected($business->timezone === $z)>{{ str_replace('_', ' ', $z) }}</option>@endforeach</optgroup>@endforeach</select></label>
                <label class="f"><span>Currency shown with prices</span>
                    <select name="currency">@foreach ($currencies as $sym => $label)<option value="{{ $sym }}" @selected($currency === $sym)>{{ $label }}</option>@endforeach
                        @unless (isset($currencies[$currency]))<option value="{{ $currency }}" selected>{{ $currency }}</option>@endunless</select></label>
            </div>
            <div class="f"><span>Colour of your staff area and chat</span>
                <div class="swatches" id="accent-swatches">
                    @foreach ($palette as $c)<button type="button" class="sw {{ strtolower($accent) === $c ? 'on' : '' }}" data-color="{{ $c }}" style="background: {{ $c }}" aria-label="Colour {{ $c }}"></button>@endforeach
                    <input type="color" name="accent" id="accent-input" value="{{ $accent }}" aria-label="Other colour">
                    <span class="mute" style="font-size:12px">Changes show as you pick. Press Save to keep it.</span>
                </div></div>
            <div class="dlg-foot"><button class="btn primary" type="submit">Save</button></div>
        </form>

        <div class="card" style="margin-top:14px">
            <h3>Your customer chat link</h3>
            <div class="copy-row" style="margin-top:10px"><input type="text" readonly value="{{ $chatUrl }}" id="chat-link"><button type="button" class="btn small" data-copy="chat-link">Copy</button><a class="btn small" href="{{ $chatUrl }}" target="_blank">Open</a></div>
            <p class="mute small-note" style="padding:0;margin-top:6px">More ways to add the chat to your website are on the <a href="{{ route('channels') }}">Channels</a> page.</p>
        </div>
    </section>

    <section data-panel="team" @if ($tab !== 'team') hidden @endif>
        <div class="bar spread" style="margin-bottom:10px">
            <span class="mute">@if ($teamLimit !== null)<b>{{ $teamUsed }} of {{ $teamLimit }}</b> team members on your plan @else <b>{{ $teamUsed }}</b> active team members @endif</span>
            <button type="button" class="btn primary" id="add-member">Add a team member</button>
        </div>
        <div class="card team-list">
            @foreach ($team as $u)
                <div class="member {{ $u->active ? '' : 'off' }}">
                    <span class="m-av">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                    <div class="m-id"><b>{{ $u->name }}@if ($u->id === auth()->id()) <span class="mute">(you)</span>@endif</b><small class="mute">{{ $u->email }}</small></div>
                    <span class="tag {{ $roleTone[$u->role] ?? '' }}">{{ ucfirst($u->role) }}</span>
                    @unless ($u->active)<span class="tag hot">Switched off</span>@endunless
                    <button type="button" class="btn small" data-edit data-action="{{ url('/admin/settings/team/'.$u->id) }}" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-role="{{ $u->role }}" data-active="{{ $u->active ? 1 : 0 }}">Edit</button>
                </div>
            @endforeach
        </div>
        <div class="card role-help">
            <b>What each role can do</b>
            <ul>@foreach ($roleHelp as $r => $text)<li><span class="tag {{ $roleTone[$r] }}">{{ ucfirst($r) }}</span> {{ $text }}</li>@endforeach</ul>
        </div>
    </section>

    <dialog id="member-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3 id="md-title">Add a team member</h3>
        <form method="post" id="md-form" action="{{ url('/admin/settings/team') }}">
            @csrf
            <div id="md-new">
                <label class="f" style="margin-top:12px"><span>Name</span><input type="text" name="name" id="md-name" maxlength="100"></label>
                <label class="f"><span>Email</span><input type="email" name="email" id="md-email" maxlength="150"></label>
            </div>
            <p id="md-who" class="mute" hidden style="margin:6px 0 10px"></p>
            <label class="f"><span>Role</span><select name="role" id="md-role">@foreach ($roleHelp as $r => $text)<option value="{{ $r }}" data-help="{{ $text }}">{{ ucfirst($r) }}</option>@endforeach</select></label>
            <p class="mute small-note" id="md-help" style="padding:0;margin:-6px 0 12px"></p>
            <label class="f" id="md-active-wrap" hidden><span>Account</span><select name="active" id="md-active"><option value="1">Active</option><option value="0">Switched off (cannot sign in)</option></select></label>
            <label class="f"><span id="md-pw-label">Password (8 or more characters)</span><input type="password" name="password" id="md-pw" autocomplete="new-password" minlength="8" maxlength="100"></label>
            <div class="dlg-foot"><button class="btn primary" type="submit">Save</button></div>
        </form>
    </dialog>
@endsection
@push('scripts')
<script>
(function () {
  // Tabs without a reload.
  document.querySelectorAll('#set-tabs a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      document.querySelectorAll('#set-tabs a').forEach(function (x) { x.classList.toggle('on', x === a); });
      document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== a.dataset.tab; });
    });
  });

  // Colour: swatches and the colour box, with a live preview of the whole staff area.
  var input = document.getElementById('accent-input');
  function setAccent(c) {
    input.value = c;
    document.documentElement.style.setProperty('--accent', c);
    document.querySelectorAll('#accent-swatches .sw').forEach(function (s) { s.classList.toggle('on', s.dataset.color.toLowerCase() === c.toLowerCase()); });
  }
  document.querySelectorAll('#accent-swatches .sw').forEach(function (s) { s.addEventListener('click', function () { setAccent(s.dataset.color); }); });
  input.addEventListener('input', function () { setAccent(input.value); });

  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.dataset.copy), done = function () { var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1300); };
      if (navigator.clipboard) { navigator.clipboard.writeText(el.value).then(done); } else { el.select(); document.execCommand('copy'); done(); }
    });
  });

  // Team pop-up: add and edit.
  var dlg = document.getElementById('member-dialog'), form = document.getElementById('md-form');
  var f = { name: 'md-name', email: 'md-email', role: 'md-role', active: 'md-active', pw: 'md-pw' };
  Object.keys(f).forEach(function (k) { f[k] = document.getElementById(f[k]); });
  function open(d) {
    var edit = d && d.action;
    document.getElementById('md-title').textContent = edit ? 'Edit ' + d.name : 'Add a team member';
    form.action = edit ? d.action : '{{ url('/admin/settings/team') }}';
    document.getElementById('md-new').hidden = !!edit;
    f.name.required = !edit; f.email.required = !edit; f.pw.required = !edit;
    f.name.disabled = !!edit; f.email.disabled = !!edit;
    var who = document.getElementById('md-who'); who.hidden = !edit; who.textContent = edit ? d.email : '';
    document.getElementById('md-active-wrap').hidden = !edit; f.active.disabled = !edit;
    f.role.value = edit ? d.role : 'staff'; f.active.value = edit ? d.active : '1'; f.pw.value = ''; f.name.value = ''; f.email.value = '';
    document.getElementById('md-pw-label').textContent = edit ? 'New password (leave empty to keep the current one)' : 'Password (8 or more characters)';
    help(); dlg.showModal(); (edit ? f.role : f.name).focus();
  }
  function help() { document.getElementById('md-help').textContent = f.role.options[f.role.selectedIndex].dataset.help; }
  f.role.addEventListener('change', help);
  document.getElementById('add-member').addEventListener('click', function () { open(null); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset); }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
  @if ($errors->has('email') || $errors->has('role')) dlg.showModal(); @endif
})();
</script>
@endpush
