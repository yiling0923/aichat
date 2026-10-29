@extends('admin.layout')
@section('title', 'My account')
@section('content')
    @php
        $me = auth()->user();
        $initials = collect(preg_split('/\s+/', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    @endphp
    <div class="page-head"><div><h1>My account</h1><p class="mute">Your own sign-in details and alerts.</p></div></div>

    <section class="card ac-me">
        <span class="ac-av">{{ $initials ?: '?' }}</span>
        <div class="ac-who">
            <h2>{{ $me->name }}</h2>
            <p class="mute">{{ $me->email }}</p>
        </div>
        <div class="ac-tags"><span class="tag ok">{{ ucfirst(str_replace('_', ' ', $me->role)) }}</span>@if ($business ?? null)<span class="tag">{{ $business->name }}</span>@endif</div>
    </section>

    <div class="ac-grid">
    <form method="post" action="{{ url('/admin/account') }}" class="card ac-form">
        @csrf
        <h3>Your details</h3>
        <label class="f"><span>Your name</span><input type="text" name="name" value="{{ old('name', $me->name) }}" required maxlength="100"></label>

        <h3 class="ac-sep">Change password</h3>
        <p class="mute small-note">Leave these empty to keep your current password.</p>
        <label class="f"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password"></label>
        <div class="ac-two">
            <label class="f"><span>New password</span><input type="password" name="password" autocomplete="new-password" minlength="8" placeholder="8 or more characters"></label>
            <label class="f"><span>Repeat new password</span><input type="password" name="password_confirmation" autocomplete="new-password"></label>
        </div>
        <div class="ac-foot"><button class="btn primary" type="submit">Save changes</button></div>
    </form>

    <div class="card ac-alerts" id="phone-alerts">
        <h3>Phone alerts</h3>
        <p class="mute" style="margin:0 0 16px;line-height:1.5">Get a pop-up on this phone or computer for new chats and bookings, even when the page is closed. Turn it on for each device you use.</p>
        <div id="pa-status" style="display:flex;align-items:center;gap:8px;margin:0 0 18px;padding:10px 12px;border-radius:10px;background:#f3f4f6;font-weight:600;font-size:14px"><span id="pa-dot" style="width:10px;height:10px;border-radius:50%;background:#9ca3af;flex:none"></span><span id="pa-text">Checking…</span></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="button" class="btn" id="pa-toggle" hidden>Turn on alerts</button>
            <button type="button" class="btn" id="pa-test" hidden>Send me a test alert</button>
        </div>
        <p id="pa-msg" class="mute" style="margin:0"></p>
    </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      var P = window.aichatPush, st = document.getElementById('pa-text'), dot = document.getElementById('pa-dot'), box = document.getElementById('pa-status'), tg = document.getElementById('pa-toggle'), ts = document.getElementById('pa-test'), msg = document.getElementById('pa-msg');
      var text = {
        unconfigured: 'Not set up on the server yet. Run: php artisan aichat:push-keys',
        unsupported: 'This browser cannot show alerts. On iPhone, add this site to the Home Screen first.',
        blocked: 'Alerts are blocked in this browser. Allow notifications for this site in the browser settings.',
        on: 'Alerts are ON for this device.', off: 'Alerts are off for this device.', error: 'Something went wrong. Try again.'
      };
      function refresh(s) {
        (s ? Promise.resolve(s) : P.state()).then(function (x) {
          st.textContent = text[x] || text.error;
          dot.style.background = x === 'on' ? '#16a34a' : (x === 'off' ? '#9ca3af' : '#dc2626');
          box.style.background = x === 'on' ? '#ecfdf5' : '#f3f4f6';
          tg.hidden = !(x === 'on' || x === 'off' || x === 'error');
          tg.textContent = x === 'on' ? 'Turn off alerts' : 'Turn on alerts';
          tg.dataset.state = x; ts.hidden = x !== 'on';
        });
      }
      tg.addEventListener('click', function () { msg.textContent = ''; (tg.dataset.state === 'on' ? P.disable() : P.enable()).then(refresh); });
      ts.addEventListener('click', function () { msg.textContent = 'Sending…'; P.test().then(function (r) { msg.textContent = r.message || r.error || 'Done.'; }); });
      refresh();
    });
    </script>
@endsection
