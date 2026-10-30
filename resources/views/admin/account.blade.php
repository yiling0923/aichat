@extends('admin.layout')
@section('title', __('My account'))
@section('content')
    @php
        $me = auth()->user();
        $initials = collect(preg_split('/\s+/', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    @endphp
    <div class="page-head"><div><h1>{{ __('My account') }}</h1><p class="mute">{{ __('Your own sign-in details and alerts.') }}</p></div></div>

    <section class="card ac-me">
        <span class="ac-av">{{ $initials ?: '?' }}</span>
        <div class="ac-who">
            <h2>{{ $me->name }}</h2>
            <p class="mute">{{ $me->email }}</p>
        </div>
        <div class="ac-tags"><span class="tag ok">{{ __(ucfirst(str_replace('_', ' ', $me->role))) }}</span>@if ($business ?? null)<span class="tag">{{ $business->name }}</span>@endif</div>
    </section>

    <div class="ac-grid">
    <form method="post" action="{{ url('/admin/account') }}" class="card ac-form">
        @csrf
        <h3>{{ __('Your details') }}</h3>
        <label class="f"><span>{{ __('Your name') }}</span><input type="text" name="name" value="{{ old('name', $me->name) }}" required maxlength="100"></label>

        <h3 class="ac-sep">{{ __('Change password') }}</h3>
        <p class="mute small-note">{{ __('Leave these empty to keep your current password.') }}</p>
        <label class="f"><span>{{ __('Current password') }}</span><input type="password" name="current_password" autocomplete="current-password"></label>
        <div class="ac-two">
            <label class="f"><span>{{ __('New password') }}</span><input type="password" name="password" autocomplete="new-password" minlength="8" placeholder="{{ __('8 or more characters') }}"></label>
            <label class="f"><span>{{ __('Repeat new password') }}</span><input type="password" name="password_confirmation" autocomplete="new-password"></label>
        </div>
        <div class="ac-foot"><button class="btn primary" type="submit">{{ __('Save changes') }}</button></div>
    </form>

    <div class="card ac-alerts" id="language">
        <h3>{{ __('Language') }}</h3>
        <p class="mute" style="margin:0 0 14px;line-height:1.5">{{ __('The language of the staff screens, just for you. Other team members keep their own.') }}</p>
        <div class="seg lang-seg">
            @foreach (\App\Support\Lang::LOCALES as $code => $name)
                <form method="post" action="{{ route('language') }}">@csrf<input type="hidden" name="locale" value="{{ $code }}"><button type="submit" @class(['on' => app()->getLocale() === $code])>{{ $name }}</button></form>
            @endforeach
        </div>
    </div>

    <div class="card ac-alerts" id="install-app">
        <h3>{{ __('Install as an app') }}</h3>
        <p class="mute" style="margin:0 0 14px;line-height:1.5">{{ __('Put this on your phone or computer like an app, with its own icon and window. Phone alerts work best this way.') }}</p>
        <div id="ia-status" class="mute" style="margin:0 0 12px"></div>
        <button type="button" class="btn" id="ia-btn" hidden>{{ __('Install') }}</button>
    </div>

    <div class="card ac-alerts" id="phone-alerts">
        <h3>{{ __('Phone alerts') }}</h3>
        <p class="mute" style="margin:0 0 16px;line-height:1.5">{{ __('Get a pop-up on this phone or computer for new chats and bookings, even when the page is closed. Turn it on for each device you use.') }}</p>
        <div id="pa-status" style="display:flex;align-items:center;gap:8px;margin:0 0 18px;padding:10px 12px;border-radius:10px;background:#f3f4f6;font-weight:600;font-size:14px"><span id="pa-dot" style="width:10px;height:10px;border-radius:50%;background:#9ca3af;flex:none"></span><span id="pa-text">{{ __('Checking…') }}</span></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="button" class="btn" id="pa-toggle" hidden>{{ __('Turn on alerts') }}</button>
            <button type="button" class="btn" id="pa-test" hidden>{{ __('Send me a test alert') }}</button>
        </div>
        <p id="pa-msg" class="mute" style="margin:0"></p>
    </div>
    </div>
    <script>
    (function () {
      var deferred = null, btn = document.getElementById('ia-btn'), st = document.getElementById('ia-status');
      var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
      var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
      if (standalone) { st.textContent = @json(__('This is already running as an app.')); return; }
      st.textContent = ios ? @json(__('On iPhone or iPad: tap the Share button in Safari, then "Add to Home Screen".')) : @json(__('If the Install button does not appear, open the browser menu and choose "Install app" or "Add to Home screen".'));
      window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; btn.hidden = false; st.textContent = @json(__('Your browser can install this as an app.')); });
      btn.addEventListener('click', function () { if (deferred) { deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; btn.hidden = true; }); } });
    })();
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      var P = window.aichatPush, st = document.getElementById('pa-text'), dot = document.getElementById('pa-dot'), box = document.getElementById('pa-status'), tg = document.getElementById('pa-toggle'), ts = document.getElementById('pa-test'), msg = document.getElementById('pa-msg');
      var text = {
        unconfigured: __t('Not set up on the server yet. Run: php artisan aichat:push-keys'),
        unsupported: __t('This browser cannot show alerts. On iPhone, add this site to the Home Screen first.'),
        blocked: __t('Alerts are blocked in this browser. Allow notifications for this site in the browser settings.'),
        on: __t('Alerts are ON for this device.'), off: __t('Alerts are off for this device.'), error: __t('Something went wrong. Try again.')
      };
      function refresh(s) {
        (s ? Promise.resolve(s) : P.state()).then(function (x) {
          st.textContent = text[x] || text.error;
          dot.style.background = x === 'on' ? '#16a34a' : (x === 'off' ? '#9ca3af' : '#dc2626');
          box.style.background = x === 'on' ? '#ecfdf5' : '#f3f4f6';
          tg.hidden = !(x === 'on' || x === 'off' || x === 'error');
          tg.textContent = x === 'on' ? __t('Turn off alerts') : __t('Turn on alerts');
          tg.dataset.state = x; ts.hidden = x !== 'on';
        });
      }
      tg.addEventListener('click', function () { msg.textContent = ''; (tg.dataset.state === 'on' ? P.disable() : P.enable()).then(refresh); });
      ts.addEventListener('click', function () { msg.textContent = __t('Sending…'); P.test().then(function (r) { msg.textContent = r.message || r.error || __t('Done.'); }); });
      refresh();
    });
    </script>
@endsection
