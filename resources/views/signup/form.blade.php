<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create your account</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
@php $days = (int) config('plans.trial_days'); @endphp
<div class="auth-split">
    <aside class="auth-side">
        <a class="auth-brand" href="{{ url('/') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
        <h2>Answer every customer, day and night.</h2>
        <ul class="auth-points">
            <li><b>One inbox</b> for WhatsApp, Instagram, Messenger and your website.</li>
            <li><b>A bot that books</b> appointments, answers prices and hands over to you.</li>
            <li><b>English and Chinese</b>, set up in minutes, no technical skills needed.</li>
        </ul>
        <p class="auth-foot">{{ $inviteOnly ? 'Private beta. Invitation needed.' : $days.' days free. No card needed. Cancel any time.' }}</p>
    </aside>

    <main class="auth-main">
        <form method="post" action="{{ route('signup') }}" class="auth-card" id="signup-form" novalidate>
            @csrf
            <a class="auth-brand mobile-only" href="{{ url('/') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
            <h1>Start your free trial</h1>
            <p class="mute">{{ $inviteOnly ? 'Enter your invitation code to begin.' : 'Takes about a minute. You can change everything later.' }}</p>

            @if ($inviteOnly)
                <label class="f"><span>Invitation code</span>
                    <input id="invite" name="invite" value="{{ old('invite', $invite) }}" required autocomplete="off" @class(['bad' => $errors->has('invite')])>
                    @error('invite')<small class="field-err">{{ $message }}</small>@enderror</label>
            @endif

            <label class="f"><span>Business name</span>
                <input id="business_name" name="business_name" value="{{ old('business_name') }}" required minlength="2" maxlength="100" autocomplete="organization" placeholder="Glow Beauty Studio" @unless($inviteOnly) autofocus @endunless @class(['bad' => $errors->has('business_name')])>
                @error('business_name')<small class="field-err">{{ $message }}</small>@enderror
                <small class="hint" id="slug-hint" hidden>Your chat link will look like <b id="slug-out"></b></small></label>

            <div class="grid2">
                <label class="f"><span>Your name</span>
                    <input id="name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @class(['bad' => $errors->has('name')])>
                    @error('name')<small class="field-err">{{ $message }}</small>@enderror</label>
                <label class="f"><span>Email</span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" @class(['bad' => $errors->has('email')])>
                    @error('email')<small class="field-err">{{ $message }}@if (str_contains($message, 'taken')) <a href="{{ route('login') }}">Sign in instead</a>@endif</small>@enderror</label>
            </div>

            <label class="f"><span>Password</span>
                <span class="pw-wrap">
                    <input id="password" name="password" type="password" required minlength="8" maxlength="100" autocomplete="new-password" @class(['bad' => $errors->has('password')])>
                    <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Show password">Show</button>
                </span>
                @error('password')<small class="field-err">{{ $message }}</small>@enderror
                <span class="meter" id="pw-meter" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                <small class="hint" id="pw-hint">At least 8 characters. A short sentence works well.</small></label>

            <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>

            <label class="terms"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                <span>I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener">terms</a> and the <a href="{{ route('privacy') }}" target="_blank" rel="noopener">privacy notice</a>.</span></label>
            @error('terms')<small class="field-err" style="margin-top:-6px">{{ $message }}</small>@enderror

            <button class="btn primary block" type="submit" id="signup-btn">Create my account</button>
            <p class="auth-alt">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
        </form>
    </main>
</div>
<script>
(function () {
  var biz = document.getElementById('business_name'), hint = document.getElementById('slug-hint'), out = document.getElementById('slug-out');
  var host = {!! json_encode(rtrim(preg_replace('#^https?://#', '', url('/')), '/').'/') !!};
  function slug(s) { return s.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
  biz.addEventListener('input', function () { var s = slug(biz.value); hint.hidden = !s; out.textContent = host + 'chat/' + s; });
  if (biz.value) { biz.dispatchEvent(new Event('input')); }

  var pw = document.getElementById('password'), meter = document.getElementById('pw-meter'), ph = document.getElementById('pw-hint');
  var words = ['Too short', 'Weak', 'Okay', 'Good', 'Strong'];
  pw.addEventListener('input', function () {
    var v = pw.value, n = 0;
    if (v.length >= 8) { n++; }
    if (v.length >= 12) { n++; }
    if (/[a-z]/.test(v) && /[A-Z0-9]/.test(v)) { n++; }
    if (/[^A-Za-z0-9 ]/.test(v) || v.length >= 16) { n++; }
    if (v.length < 8) { n = 0; }
    meter.className = 'meter s' + n;
    ph.textContent = v ? words[n] + (n === 0 ? ': use at least 8 characters.' : '.') : 'At least 8 characters. A short sentence works well.';
  });
  document.getElementById('pw-toggle').addEventListener('click', function () {
    var show = pw.type === 'password'; pw.type = show ? 'text' : 'password'; this.textContent = show ? 'Hide' : 'Show'; this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });

  // Gentle checks before sending; the server checks again.
  var form = document.getElementById('signup-form');
  form.addEventListener('submit', function (e) {
    var bad = form.querySelector(':invalid');
    if (bad) { e.preventDefault(); form.querySelectorAll(':invalid').forEach(function (x) { x.classList.add('bad'); }); bad.focus(); return; }
    var b = document.getElementById('signup-btn'); b.disabled = true; b.textContent = 'Creating...';
  });
  form.addEventListener('input', function (e) { if (e.target.validity && e.target.validity.valid) { e.target.classList.remove('bad'); } });
})();
</script>
</body>
</html>
