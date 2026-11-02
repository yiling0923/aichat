@extends('admin.layout')
@section('title', __('Business and team'))
@section('content')
    @php
        $accent = $business->settings['accent'] ?? '#0f766e';
        $currency = $business->settings['currency'] ?? 'RM';
        $roleHelp = ['owner' => __('Everything, including billing, channels and the team.'), 'manager' => __('Saved replies, tags, marketing, AI and templates, plus everything staff can do.'), 'staff' => __('Inbox, schedule, customers, tasks, orders and reports.')];
        $roleTone = ['owner' => 'ok', 'manager' => 'warn', 'staff' => ''];
    @endphp
    <div class="page-head">
        <div><h1>{{ __('Business and team') }}</h1><p class="mute">{{ __('Your business details and who can sign in. Prices and services are in Booking setup; what the bot says is in the Menu builder.') }}</p></div>
        <div class="seg" id="set-tabs">
            <a href="{{ route('settings') }}" data-tab="business" @class(['on' => $tab === 'business'])>{{ __('Business') }}</a>
            <a href="{{ route('settings', ['tab' => 'team']) }}" data-tab="team" @class(['on' => $tab === 'team'])>{{ __('Team') }} ({{ $team->count() }})</a>
        </div>
    </div>

    <section data-panel="business" @if ($tab !== 'business') hidden @endif>
        <form method="post" action="{{ url('/admin/settings/profile') }}" class="card set-card">
            @csrf
            <div class="set-head"><h3>{{ __('About your business') }}</h3><p class="mute">{{ __('Shown to customers and used for dates, times and prices.') }}</p></div>
            <div class="grid2">
                <label class="f"><span>{{ __('Business name') }}</span><input type="text" name="name" value="{{ old('name', $business->name) }}" required maxlength="120"></label>
                <label class="f"><span>{{ __('Address (used when customers ask where you are)') }}</span><input type="text" name="address" value="{{ old('address', $business->settings['address'] ?? '') }}" maxlength="200" placeholder="{{ __('12 Jalan Example, Kuala Lumpur') }}"></label>
                <label class="f"><span>{{ __('Time zone') }}</span>
                    <select name="timezone">@foreach ($timezones as $group => $zones)<optgroup label="{{ $group }}">@foreach ($zones as $z)<option value="{{ $z }}" @selected($business->timezone === $z)>{{ str_replace('_', ' ', $z) }}</option>@endforeach</optgroup>@endforeach</select></label>
                <label class="f"><span>{{ __('Currency shown with prices') }}</span>
                    <select name="currency">@foreach ($currencies as $sym => $label)<option value="{{ $sym }}" @selected($currency === $sym)>{{ $label }}</option>@endforeach
                        @unless (isset($currencies[$currency]))<option value="{{ $currency }}" selected>{{ $currency }}</option>@endunless</select></label>
            </div>
            <div class="f"><span>{{ __('Colour of your staff area and chat') }}</span>
                <div class="swatches" id="accent-swatches">
                    @foreach ($palette as $c)<button type="button" class="sw {{ strtolower($accent) === $c ? 'on' : '' }}" data-color="{{ $c }}" style="background: {{ $c }}" aria-label="Colour {{ $c }}"></button>@endforeach
                    <input type="color" name="accent" id="accent-input" value="{{ $accent }}" aria-label="{{ __('Other colour') }}">
                    <span class="mute" style="font-size:12px">{{ __('Changes show as you pick. Press Save to keep it.') }}</span>
                </div></div>
            <div class="set-foot"><button class="btn primary" type="submit">{{ __('Save') }}</button></div>
        </form>

        <form method="post" action="{{ url('/admin/settings/alerts') }}" class="card set-card" id="alerts-form">
            @csrf
            <div class="set-head"><h3>{{ __('When a customer waits for a person') }}</h3><p class="mute">{{ __('The bell already chimes and reminds the team. Add more ways to reach them, and decide what happens if nobody answers.') }}</p></div>
            <label class="f"><span>{{ __('If nobody answers, give the chat back to the bot after') }}</span>
                <select name="return_minutes">
                    @foreach ([0 => 'Never (the chat waits for a person)', 15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', 120 => '2 hours', 240 => '4 hours'] as $m => $l)<option value="{{ $m }}" @selected((int) ($business->settings['handover_return_minutes'] ?? 0) === $m)>{{ __($l) }}</option>@endforeach
                </select>
                <small class="hint">{{ __('The customer is told sorry and sees the menu again. Staff still get a bell alert and can take over any time.') }}</small></label>
            <label class="terms set-check"><input type="checkbox" name="alert_email" value="1" @checked(! empty($business->settings['alert_email']))><span><b>{{ __('Email the owner and managers') }}</b> {{ __('when a customer has waited 10 minutes, and again at 30 minutes (urgent).') }}</span></label>
            <div class="grid2">
                <label class="f"><span>{{ __('WhatsApp alert to this number (optional)') }}</span>
                    <input type="text" name="alert_whatsapp" value="{{ old('alert_whatsapp', $business->settings['alert_whatsapp'] ?? '') }}" placeholder="60123456789" inputmode="tel">
                    @error('alert_whatsapp')<small class="field-err">{{ $message }}</small>@enderror</label>
                <label class="f"><span>{{ __('Approved WhatsApp template for it (optional)') }}</span>
                    <input type="text" name="alert_whatsapp_template" value="{{ old('alert_whatsapp_template', $business->settings['alert_whatsapp_template'] ?? '') }}" placeholder="{{ __('staff_alert') }}" maxlength="80">
                    @error('alert_whatsapp_template')<small class="field-err">{{ $message }}</small>@enderror</label>
            </div>
            <small class="hint">{!! __('WhatsApp only delivers a message to staff if the number wrote to your business in the last 24 hours, or if you give an approved template (use :one for the customer name and :two for the minutes). It needs a connected WhatsApp channel.', ['one' => '{'.'{1}'.'}', 'two' => '{'.'{2}'.'}']) !!}</small>
            <div class="set-foot"><button class="btn" type="submit" formaction="{{ url('/admin/settings/alerts/test') }}" title="{{ __('Sends a test email to you, and to the WhatsApp number if set') }}">{{ __('Send me a test alert') }}</button><button class="btn primary" type="submit">{{ __('Save') }}</button></div>
        </form>

        <form method="post" action="{{ url('/admin/settings/smart') }}" class="card set-card">
            @csrf
            <div class="set-head"><h3>{{ __('Smart helpers') }}</h3><p class="mute">{{ __('Chats are tagged by what the customer writes: Price question, Booking help, Cancel or change, Location, Payment, Aftercare and Complaint. Someone who asks about prices or booking becomes at least a warm lead, and owners and managers get a bell alert for a possible complaint. It uses simple word matching, no AI.') }}</p></div>
            <label class="terms set-check"><input type="checkbox" name="auto_tag" value="1" @checked(($business->settings['auto_tag'] ?? true) !== false)><span><b>{{ __('Tag chats automatically') }}</b> {{ __('(you can still add or remove tags by hand).') }}</span></label>
            <label class="terms set-check"><input type="checkbox" name="alert_new_chat" value="1" @checked(($business->settings['alert_new_chat'] ?? true) !== false)><span><b>{{ __('Alert me when a customer sends a message') }}</b> {{ __('(bell and phone alert, even while the bot is answering). More messages in the same chat update one alert instead of piling up. You are always alerted when a person is needed.') }}</span></label>
            <label class="terms set-check"><input type="checkbox" name="away_notice" value="1" @checked(($business->settings['away_notice'] ?? true) !== false)><span><b>{{ __('Tell customers when we are closed') }}</b> {{ __('(once, at the start of a chat that begins outside your opening hours, with when you are back).') }}</span></label>
            <label class="terms set-check"><input type="checkbox" name="auto_assign" value="1" @checked(! empty($business->settings['auto_assign']))><span><b>{{ __('Give new chats that need a person to the least busy team member') }}</b> {{ __('(you can still change the person in the chat).') }}</span></label>
            <label class="terms set-check"><input type="checkbox" name="daily_email" value="1" @checked(! empty($business->settings['daily_email']))><span><b>{{ __('Email me yesterday\'s summary every morning') }}</b> {{ __('(about 8:00, to owners and managers). The same numbers are always on the Today page.') }}</span></label>
            <div class="set-foot"><button class="btn primary" type="submit">{{ __('Save') }}</button></div>
        </form>

        <div class="card set-card">
            <div class="set-head"><h3>{{ __('Your customer chat link') }}</h3><p class="mute">{{ __('Share this link, or add the chat to your website.') }}</p></div>
            <div class="copy-row"><input type="text" readonly value="{{ $chatUrl }}" id="chat-link"><button type="button" class="btn small" data-copy="chat-link">{{ __('Copy') }}</button><a class="btn small" href="{{ $chatUrl }}" target="_blank">{{ __('Open') }}</a></div>
            <p class="mute small-note" style="padding:0;margin-top:6px">{!! __('More ways to add the chat to your website are on the :link page.', ['link' => '<a href="'.route('channels').'">'.e(__('Channels')).'</a>']) !!}</p>
        </div>
    </section>

    <section data-panel="team" @if ($tab !== 'team') hidden @endif>
        @php
            $rank = ['owner' => 0, 'manager' => 1, 'staff' => 2];
            $sorted = $team->sortBy(fn ($u) => [$u->active ? 0 : 1, $rank[$u->role] ?? 3, mb_strtolower($u->name)])->values();
            $seatPct = $teamLimit ? min(100, round($teamUsed / $teamLimit * 100)) : 0;
            $initials = fn ($n) => collect(preg_split('/\s+/', trim($n)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
        @endphp
        <div class="card set-card tm-head">
            <div class="tm-seats">
                <h3>{{ __('Your team') }}</h3>
                <p class="mute">@if ($teamLimit !== null)<b>{{ __(':a of :b', ['a' => $teamUsed, 'b' => $teamLimit]) }}</b> {{ __('team members on your plan') }}@else<b>{{ $teamUsed }}</b> {{ __('active team members') }}@endif</p>
                @if ($teamLimit !== null)<div class="progress {{ $seatPct >= 100 ? 'hot' : ($seatPct >= 70 ? 'warn' : '') }}"><span style="width: {{ $seatPct }}%"></span></div>@endif
            </div>
            <button type="button" class="btn primary" id="add-member">{{ __('+ Add a team member') }}</button>
        </div>
        <div class="card team-card">
            <div class="tm-tools">
                <input type="search" id="tm-q" placeholder="{{ __('Search name or email') }}" aria-label="{{ __('Search name or email') }}">
                <select id="tm-role" aria-label="{{ __('Role') }}"><option value="">{{ __('All roles') }}</option>@foreach ($roleHelp as $r => $text)<option value="{{ $r }}">{{ __(ucfirst($r)) }}</option>@endforeach</select>
                <select id="tm-status" aria-label="{{ __('Account') }}"><option value="">{{ __('All accounts') }}</option><option value="1">{{ __('Active') }}</option><option value="0">{{ __('Switched off') }}</option></select>
                <span class="mute tm-count" id="tm-count"></span>
            </div>
            <div id="tm-flash" hidden role="status" style="margin:10px 0 0"></div>
            @error('bulk')<div class="note err" style="margin:10px 0 0">{{ $message }}</div>@enderror
            <form method="post" action="{{ url('/admin/settings/team/bulk') }}" id="bulk-form" class="tm-bulk" hidden>
                @csrf
                <b><span id="bulk-n">0</span> {{ __('selected') }}</b>
                <select name="role" id="bulk-role" aria-label="{{ __('Change role to') }}"><option value="">{{ __('Change role to...') }}</option>@foreach ($roleHelp as $r => $text)<option value="{{ $r }}">{{ __(ucfirst($r)) }}</option>@endforeach</select>
                <button class="btn small" type="submit" name="action" value="role" id="bulk-apply" disabled>{{ __('Apply role') }}</button>
                <button class="btn small" type="submit" name="action" value="activate">{{ __('Switch on') }}</button>
                <button class="btn small" type="submit" name="action" value="deactivate">{{ __('Switch off') }}</button>
                <button class="btn small" type="submit" name="action" value="reset_access" title="{{ __('Return them to the usual access for their role') }}">{{ __('Usual access') }}</button>
                <button class="btn small" type="button" id="bulk-clear">{{ __('Clear') }}</button>
            </form>
            <div class="tm-table" id="tm-table">
                <div class="tm-row tm-headrow">
                    <label class="tm-check"><input type="checkbox" id="tm-all" aria-label="{{ __('Select all') }}"></label>
                    <span>{{ __('Person') }}</span><span>{{ __('Role') }}</span><span>{{ __('Access') }}</span><span>{{ __('Active') }}</span><span class="tm-actions-h">{{ __('Actions') }}</span>
                </div>
                @foreach ($sorted as $u)
                    <div class="tm-row member {{ $u->active ? '' : 'off' }}" data-q="{{ mb_strtolower($u->name.' '.$u->email) }}" data-role="{{ $u->role }}" data-active="{{ $u->active ? 1 : 0 }}">
                        <label class="tm-check"><input type="checkbox" class="tm-sel" name="ids[]" form="bulk-form" value="{{ $u->id }}" aria-label="{{ __('Select :name', ['name' => $u->name]) }}"></label>
                        <div class="tm-person"><span class="m-av">{{ $initials($u->name) }}</span><div class="m-id"><b>{{ $u->name }}@if ($u->id === auth()->id()) <span class="m-you">{{ __('(you)') }}</span>@endif</b><small class="mute">{{ $u->email }}</small></div></div>
                        <div class="tm-role" data-label="{{ __('Role') }}"><span class="tag {{ $roleTone[$u->role] ?? '' }}">{{ __(ucfirst($u->role)) }}</span></div>
                        <div class="tm-access" data-label="{{ __('Access') }}"><small class="mute">{{ $u->role === 'owner' ? __('Everything') : (is_array($u->permissions) ? __('Custom: :n areas', ['n' => count($u->areas())]) : __('Usual for the role')) }}</small></div>
                        <div class="tm-on" data-label="{{ __('Active') }}">
                            <label class="switch" data-tip="{{ $u->id === auth()->id() ? __('You cannot switch yourself off') : ($u->active ? __('Switch off (cannot sign in)') : __('Switch on')) }}">
                                <input type="checkbox" data-toggle data-url="{{ url('/admin/settings/team/'.$u->id.'/active') }}" data-name="{{ $u->name }}" @checked($u->active) @disabled($u->id === auth()->id()) aria-label="{{ __('Active') }}"><i></i>
                            </label>
                        </div>
                        <div class="tm-actions">
                            <button type="button" class="ibtn" data-edit data-tip="{{ __('Edit') }}" aria-label="{{ __('Edit') }}" data-action="{{ url('/admin/settings/team/'.$u->id) }}" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-role="{{ $u->role }}" data-active="{{ $u->active ? 1 : 0 }}" data-custom="{{ is_array($u->permissions) ? 1 : 0 }}" data-areas="{{ json_encode($u->areas()) }}"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>
                            <button type="button" class="ibtn" data-pw data-tip="{{ __('Change password') }}" aria-label="{{ __('Change password') }}" data-action="{{ url('/admin/settings/team/'.$u->id.'/password') }}" data-name="{{ $u->name }}" data-id="{{ $u->id }}"><svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M16 7l3 3M14 9l2 2"/></svg></button>
                        </div>
                    </div>
                @endforeach
                <p class="mute tm-none" id="tm-none" hidden>{{ __('No one matches these filters.') }}</p>
            </div>
        </div>
        <h3 class="set-group" style="margin-top:22px">{{ __('What each role can do') }}</h3>
        <div class="role-cards">
            @foreach ($roleHelp as $r => $text)
                <div class="card role-card"><span class="tag {{ $roleTone[$r] }}">{{ __(ucfirst($r)) }}</span><p>{{ $text }}</p></div>
            @endforeach
        </div>
    </section>

    <dialog id="member-dialog" class="dlg xwide">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3 id="md-title">{{ __('Add a team member') }}</h3>
        <form method="post" id="md-form" action="{{ url('/admin/settings/team') }}">
            @csrf
            <div class="md-cols" id="md-cols">
                <div class="md-left">
            <div id="md-new">
                <label class="f" style="margin-top:12px"><span>{{ __('Name') }}</span><input type="text" name="name" id="md-name" maxlength="100"></label>
                <label class="f"><span>{{ __('Email') }}</span><input type="email" name="email" id="md-email" maxlength="150"></label>
            </div>
            <p id="md-who" class="mute" hidden style="margin:6px 0 10px"></p>
            <label class="f"><span>{{ __('Role') }}</span><select name="role" id="md-role">@foreach ($roleHelp as $r => $text)<option value="{{ $r }}" data-help="{{ $text }}">{{ __(ucfirst($r)) }}</option>@endforeach</select></label>
            <p class="mute small-note" id="md-help" style="padding:0;margin:-6px 0 12px"></p>
            <label class="f" id="md-active-wrap" hidden><span>{{ __('Account') }}</span><select name="active" id="md-active"><option value="1">{{ __('Active') }}</option><option value="0">{{ __('Switched off (cannot sign in)') }}</option></select></label>
            <label class="f"><span id="md-pw-label">{{ __('Password (8 or more characters)') }}</span><input type="password" name="password" id="md-pw" autocomplete="new-password" minlength="8" maxlength="100"></label>
                </div>
                <div class="md-right">
            <div id="md-access">
                <label class="chk"><input type="checkbox" name="custom" value="1" id="md-custom"> {{ __('Choose exactly what this person can use') }}</label>
                <p class="mute small-note" id="md-access-help" style="padding:0;margin:4px 0 8px"></p>
                <div class="area-grid" id="md-areas">
                    @foreach (\App\Support\Access::AREAS as $key => [$name, $what])
                        <label class="area"><input type="checkbox" name="areas[]" value="{{ $key }}"><span><b>{{ __($name) }}</b><small>{{ __($what) }}</small></span></label>
                    @endforeach
                </div>
                <p class="mute small-note" style="padding:0">{{ __('Channels (connections and tokens), billing, backups, settings and the team are always for owners only.') }}</p>
            </div>
                </div>
            </div>
            <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save') }}</button></div>
        </form>
    </dialog>

    <dialog id="pw-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3>{{ __('Change password') }}</h3>
        <p class="mute" id="pw-who" style="margin:4px 0 10px"></p>
        <form method="post" id="pw-form" action="">
            @csrf
            <input type="hidden" name="pw_user" id="pw-user" value="{{ old('pw_user') }}">
            <label class="f"><span>{{ __('New password (8 or more characters)') }}</span><input type="password" name="new_password" id="pw-new" autocomplete="new-password" minlength="8" maxlength="100" required></label>
            @error('new_password')<div class="note err" style="margin:8px 0 0">{{ $message }}</div>@enderror
            <label class="chk" style="margin-top:8px"><input type="checkbox" id="pw-show"> {{ __('Show the password') }}</label>
            <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save password') }}</button></div>
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
  f.custom = document.getElementById('md-custom');
  var ROLE_AREAS = {!! json_encode(['owner' => \App\Support\Access::keys(), 'manager' => \App\Support\Access::forRole('manager'), 'staff' => \App\Support\Access::forRole('staff')]) !!};
  var areasNow = [];
  var boxes = document.querySelectorAll('#md-areas input[type=checkbox]');
  // The tick boxes show the role's usual access until the owner chooses exactly (then they are free to change).
  function syncAreas() {
    var isOwner = f.role.value === 'owner';
    document.getElementById('md-access').hidden = isOwner;
    document.getElementById('md-cols').classList.toggle('noaccess', isOwner);
    if (!f.custom.checked) { areasNow = ROLE_AREAS[f.role.value] || []; }
    boxes.forEach(function (b) { b.checked = areasNow.indexOf(b.value) !== -1; b.disabled = isOwner || !f.custom.checked; });
    f.custom.disabled = isOwner;
    document.getElementById('md-access-help').textContent = f.custom.checked ? {!! json_encode(__('Tick what this person may use.'), JSON_UNESCAPED_UNICODE) !!} : {!! json_encode(__('Switched off: this person gets the usual access for the role (shown below).'), JSON_UNESCAPED_UNICODE) !!};
  }
  f.custom.addEventListener('change', function () { if (f.custom.checked) { areasNow = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).map(function (b) { return b.value; }); } syncAreas(); });
  boxes.forEach(function (b) { b.addEventListener('change', function () { areasNow = Array.prototype.filter.call(boxes, function (x) { return x.checked; }).map(function (x) { return x.value; }); }); });
  function open(d) {
    var edit = d && d.action;
    document.getElementById('md-title').textContent = edit ? {!! json_encode(__('Edit :name', ['name' => '@@NAME@@']), JSON_UNESCAPED_UNICODE) !!}.replace('@@NAME@@', d.name) : {!! json_encode(__('Add a team member'), JSON_UNESCAPED_UNICODE) !!};
    form.action = edit ? d.action : '{{ url('/admin/settings/team') }}';
    document.getElementById('md-new').hidden = !!edit;
    f.name.required = !edit; f.email.required = !edit; f.pw.required = !edit;
    f.name.disabled = !!edit; f.email.disabled = !!edit;
    var who = document.getElementById('md-who'); who.hidden = !edit; who.textContent = edit ? d.email : '';
    document.getElementById('md-active-wrap').hidden = !edit; f.active.disabled = !edit;
    f.role.value = edit ? d.role : 'staff'; f.active.value = edit ? d.active : '1'; f.pw.value = ''; f.name.value = ''; f.email.value = '';
    document.getElementById('md-pw-label').textContent = edit ? {!! json_encode(__('New password (leave empty to keep the current one)'), JSON_UNESCAPED_UNICODE) !!} : {!! json_encode(__('Password (8 or more characters)'), JSON_UNESCAPED_UNICODE) !!};
    f.custom.checked = !!(edit && d.custom === '1'); areasNow = edit ? JSON.parse(d.areas || '[]') : []; syncAreas();
    help(); dlg.showModal(); (edit ? f.role : f.name).focus();
  }
  function help() { document.getElementById('md-help').textContent = f.role.options[f.role.selectedIndex].dataset.help; }
  f.role.addEventListener('change', function () { help(); syncAreas(); });
  document.getElementById('add-member').addEventListener('click', function () { open(null); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset); }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
  @if ($errors->has('email') || $errors->has('role')) dlg.showModal(); @endif

  // ---- Team list: filters, bulk actions, the On/Off switch and the password button ----
  var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  var rows = Array.prototype.slice.call(document.querySelectorAll('#tm-table .tm-row.member'));
  var q = document.getElementById('tm-q'), fr = document.getElementById('tm-role'), fs = document.getElementById('tm-status');
  var bulk = document.getElementById('bulk-form'), all = document.getElementById('tm-all'), bn = document.getElementById('bulk-n'), brole = document.getElementById('bulk-role'), bapply = document.getElementById('bulk-apply');
  var T = { showing: {!! json_encode(__('Showing :a of :b', ['a' => '@@A@@', 'b' => '@@B@@']), JSON_UNESCAPED_UNICODE) !!}, tipOff: {!! json_encode(__('Switch off (cannot sign in)'), JSON_UNESCAPED_UNICODE) !!}, tipOn: {!! json_encode(__('Switch on'), JSON_UNESCAPED_UNICODE) !!}, saved: {!! json_encode(__('Team member saved.'), JSON_UNESCAPED_UNICODE) !!}, failed: {!! json_encode(__('Could not save. Try again.'), JSON_UNESCAPED_UNICODE) !!}, sure: {!! json_encode(__('Switch these people off? They will not be able to sign in.'), JSON_UNESCAPED_UNICODE) !!} };
  function sel() { return rows.filter(function (r) { return !r.hidden && r.querySelector('.tm-sel').checked; }); }
  function syncBulk() {
    var s = sel(), vis = rows.filter(function (r) { return !r.hidden; });
    bulk.hidden = s.length === 0; bn.textContent = s.length;
    all.checked = vis.length > 0 && s.length === vis.length; all.indeterminate = s.length > 0 && s.length < vis.length;
    bapply.disabled = !brole.value;
  }
  function applyFilter() {
    var text = q.value.trim().toLowerCase(), shown = 0;
    rows.forEach(function (r) {
      var ok = (!text || r.dataset.q.indexOf(text) !== -1) && (!fr.value || r.dataset.role === fr.value) && (fs.value === '' || r.dataset.active === fs.value);
      r.hidden = !ok;
      if (ok) { shown++; } else { r.querySelector('.tm-sel').checked = false; }
    });
    document.getElementById('tm-count').textContent = shown === rows.length ? '' : T.showing.replace('@@A@@', shown).replace('@@B@@', rows.length);
    document.getElementById('tm-none').hidden = shown !== 0;
    syncBulk();
  }
  [q, fr, fs].forEach(function (el) { el.addEventListener('input', applyFilter); });
  rows.forEach(function (r) { r.querySelector('.tm-sel').addEventListener('change', syncBulk); });
  all.addEventListener('change', function () { rows.forEach(function (r) { if (!r.hidden) { r.querySelector('.tm-sel').checked = all.checked; } }); syncBulk(); });
  brole.addEventListener('change', syncBulk);
  document.getElementById('bulk-clear').addEventListener('click', function () { rows.forEach(function (r) { r.querySelector('.tm-sel').checked = false; }); syncBulk(); });
  bulk.addEventListener('submit', function (e) { if (e.submitter && e.submitter.value === 'deactivate' && !confirm(T.sure)) { e.preventDefault(); } });

  function flash(text, bad) {
    var n = document.getElementById('tm-flash'); n.textContent = text; n.className = 'note ' + (bad ? 'err' : 'ok'); n.hidden = false;
    clearTimeout(flash.t); flash.t = setTimeout(function () { n.hidden = true; }, 4000);
  }
  document.querySelectorAll('[data-toggle]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var on = cb.checked, row = cb.closest('.tm-row'), wrap = cb.closest('.switch');
      cb.disabled = true;
      fetch(cb.dataset.url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ active: on ? 1 : 0 }) })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
        .then(function (x) {
          if (!x.ok) { cb.checked = !on; flash(x.j.message || T.failed, true); return; }
          row.dataset.active = on ? '1' : '0'; row.classList.toggle('off', !on); wrap.dataset.tip = on ? T.tipOff : T.tipOn;
          flash(cb.dataset.name + ': ' + T.saved); applyFilter();
        })
        .catch(function () { cb.checked = !on; flash(T.failed, true); })
        .then(function () { cb.disabled = false; });
    });
  });

  var pdlg = document.getElementById('pw-dialog'), pform = document.getElementById('pw-form'), pnew = document.getElementById('pw-new');
  function openPw(b) {
    pform.action = b.dataset.action; document.getElementById('pw-user').value = b.dataset.id; document.getElementById('pw-who').textContent = b.dataset.name;
    pnew.value = ''; pnew.type = 'password'; document.getElementById('pw-show').checked = false; pdlg.showModal(); pnew.focus();
  }
  document.querySelectorAll('[data-pw]').forEach(function (b) { b.addEventListener('click', function () { openPw(b); }); });
  document.getElementById('pw-show').addEventListener('change', function (e) { pnew.type = e.target.checked ? 'text' : 'password'; });
  pdlg.addEventListener('click', function (e) { if (e.target === pdlg || e.target.closest('.dlg-x')) { pdlg.close(); } });
  @if ($errors->has('new_password') && old('pw_user')) (function () { var b = document.querySelector('[data-pw][data-id="{{ (int) old('pw_user') }}"]'); if (b) { openPw(b); } })(); @endif
  applyFilter();
})();
</script>
@endpush
