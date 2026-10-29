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
        <div class="card team-list">
            @foreach ($sorted as $u)
                <div class="member {{ $u->active ? '' : 'off' }}">
                    <span class="m-av">{{ $initials($u->name) }}</span>
                    <div class="m-id"><b>{{ $u->name }}@if ($u->id === auth()->id()) <span class="m-you">{{ __('(you)') }}</span>@endif</b><small class="mute">{{ $u->email }}</small></div>
                    <div class="m-tags"><span class="tag {{ $roleTone[$u->role] ?? '' }}">{{ __(ucfirst($u->role)) }}</span>@unless ($u->active)<span class="tag hot">{{ __('Switched off') }}</span>@endunless</div>
                    <button type="button" class="btn small" data-edit data-action="{{ url('/admin/settings/team/'.$u->id) }}" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-role="{{ $u->role }}" data-active="{{ $u->active ? 1 : 0 }}">{{ __('Edit') }}</button>
                </div>
            @endforeach
        </div>
        <h3 class="set-group" style="margin-top:22px">{{ __('What each role can do') }}</h3>
        <div class="role-cards">
            @foreach ($roleHelp as $r => $text)
                <div class="card role-card"><span class="tag {{ $roleTone[$r] }}">{{ __(ucfirst($r)) }}</span><p>{{ $text }}</p></div>
            @endforeach
        </div>
    </section>

    <dialog id="member-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3 id="md-title">{{ __('Add a team member') }}</h3>
        <form method="post" id="md-form" action="{{ url('/admin/settings/team') }}">
            @csrf
            <div id="md-new">
                <label class="f" style="margin-top:12px"><span>{{ __('Name') }}</span><input type="text" name="name" id="md-name" maxlength="100"></label>
                <label class="f"><span>{{ __('Email') }}</span><input type="email" name="email" id="md-email" maxlength="150"></label>
            </div>
            <p id="md-who" class="mute" hidden style="margin:6px 0 10px"></p>
            <label class="f"><span>{{ __('Role') }}</span><select name="role" id="md-role">@foreach ($roleHelp as $r => $text)<option value="{{ $r }}" data-help="{{ $text }}">{{ __(ucfirst($r)) }}</option>@endforeach</select></label>
            <p class="mute small-note" id="md-help" style="padding:0;margin:-6px 0 12px"></p>
            <label class="f" id="md-active-wrap" hidden><span>{{ __('Account') }}</span><select name="active" id="md-active"><option value="1">{{ __('Active') }}</option><option value="0">{{ __('Switched off (cannot sign in)') }}</option></select></label>
            <label class="f"><span id="md-pw-label">{{ __('Password (8 or more characters)') }}</span><input type="password" name="password" id="md-pw" autocomplete="new-password" minlength="8" maxlength="100"></label>
            <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save') }}</button></div>
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
    document.getElementById('md-title').textContent = edit ? {{ json_encode(__('Edit :name', ['name' => '@@NAME@@']), JSON_UNESCAPED_UNICODE) }}.replace('@@NAME@@', d.name) : {{ json_encode(__('Add a team member'), JSON_UNESCAPED_UNICODE) }};
    form.action = edit ? d.action : '{{ url('/admin/settings/team') }}';
    document.getElementById('md-new').hidden = !!edit;
    f.name.required = !edit; f.email.required = !edit; f.pw.required = !edit;
    f.name.disabled = !!edit; f.email.disabled = !!edit;
    var who = document.getElementById('md-who'); who.hidden = !edit; who.textContent = edit ? d.email : '';
    document.getElementById('md-active-wrap').hidden = !edit; f.active.disabled = !edit;
    f.role.value = edit ? d.role : 'staff'; f.active.value = edit ? d.active : '1'; f.pw.value = ''; f.name.value = ''; f.email.value = '';
    document.getElementById('md-pw-label').textContent = edit ? {{ json_encode(__('New password (leave empty to keep the current one)'), JSON_UNESCAPED_UNICODE) }} : {{ json_encode(__('Password (8 or more characters)'), JSON_UNESCAPED_UNICODE) }};
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
