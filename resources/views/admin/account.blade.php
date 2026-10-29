@extends('admin.layout')
@section('title', 'My account')
@section('content')
    <div class="page-head"><div><h1>My account</h1><p class="mute">Your own sign-in details. Signed in as <b>{{ auth()->user()->email }}</b> ({{ auth()->user()->role }}).</p></div></div>
    <form method="post" action="{{ url('/admin/account') }}" class="card" style="max-width:560px">
        @csrf
        <label class="f"><span>Your name</span><input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="100"></label>
        <h3 style="margin-top:18px">Change password</h3>
        <p class="mute small-note" style="padding:0">Leave these empty to keep your password.</p>
        <label class="f" style="margin-top:8px"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password"></label>
        <label class="f"><span>New password (8 or more characters)</span><input type="password" name="password" autocomplete="new-password" minlength="8"></label>
        <label class="f"><span>Repeat the new password</span><input type="password" name="password_confirmation" autocomplete="new-password"></label>
        <div class="dlg-foot"><button class="btn primary" type="submit">Save</button></div>
    </form>

    <div class="card" id="phone-alerts" style="max-width:560px;margin-top:16px;padding:22px 24px">
        <h3 style="margin:0 0 8px">Phone alerts</h3>
        <p class="mute" style="margin:0 0 16px;line-height:1.5">Get a pop-up on this phone or computer for new chats and bookings, even when the page is closed. Turn it on for each device you use.</p>
        <div id="pa-status" style="display:flex;align-items:center;gap:8px;margin:0 0 18px;padding:10px 12px;border-radius:10px;background:#f3f4f6;font-weight:600;font-size:14px"><span id="pa-dot" style="width:10px;height:10px;border-radius:50%;background:#9ca3af;flex:none"></span><span id="pa-text">Checking…</span></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="button" class="btn" id="pa-toggle" hidden>Turn on alerts</button>
            <button type="button" class="btn" id="pa-test" hidden>Send me a test alert</button>
        </div>
        <p id="pa-msg" class="mute" style="margin:14px 0 0;min-height:1em"></p>
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
