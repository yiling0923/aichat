@extends('auth.layout')
@section('title', 'Sign in')
@section('heading', 'Sign in')
@section('sub', 'Use the email and password for your staff account.')
@section('body')
    <form method="post" action="{{ route('login') }}" class="auth-card" style="padding:0;border:0;box-shadow:none;max-width:none">
        @csrf
        <label class="f"><span>Email</span>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" @class(['bad' => $errors->has('email')])>
            @error('email')<small class="field-err">{{ $message }}</small>@enderror</label>
        <label class="f"><span class="row-between">Password <a href="{{ route('password.request') }}">Forgot password?</a></span>
            <span class="pw-wrap">
                <input id="password" name="password" type="password" required autocomplete="current-password">
                <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Show password">Show</button>
            </span></label>
        <label class="terms"><input type="checkbox" name="remember" value="1"><span>Keep me signed in on this device</span></label>
        <button class="btn primary block" type="submit" id="login-btn">Sign in</button>
    </form>
    <p class="auth-alt">New here? <a href="{{ route('signup') }}">Start a free trial</a></p>
    @if (app()->environment('local'))<p class="auth-alt">Local testing: owner@example.com / password</p>@endif
@endsection
@push('scripts')
<script>
(function () {
  var pw = document.getElementById('password');
  document.getElementById('pw-toggle').addEventListener('click', function () {
    var show = pw.type === 'password'; pw.type = show ? 'text' : 'password'; this.textContent = show ? 'Hide' : 'Show'; this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
  document.querySelector('form').addEventListener('submit', function () { var b = document.getElementById('login-btn'); setTimeout(function () { b.disabled = true; b.textContent = 'Signing in...'; }, 0); });
})();
</script>
@endpush
