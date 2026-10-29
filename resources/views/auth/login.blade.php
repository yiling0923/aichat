@extends('auth.layout')
@section('title', __('Sign in'))
@section('heading', __('Sign in'))
@section('sub', __('Use the email and password for your staff account.'))
@section('body')
    <form method="post" action="{{ route('login') }}" class="auth-card" style="padding:0;border:0;box-shadow:none;max-width:none">
        @csrf
        <label class="f"><span>{{ __('Email') }}</span>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" @class(['bad' => $errors->has('email')])>
            @error('email')<small class="field-err">{{ $message }}</small>@enderror</label>
        <label class="f"><span class="row-between">{{ __('Password') }} <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a></span>
            <span class="pw-wrap">
                <input id="password" name="password" type="password" required autocomplete="current-password">
                <button type="button" class="pw-toggle" id="pw-toggle" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button>
            </span></label>
        <label class="terms"><input type="checkbox" name="remember" value="1"><span>{{ __('Keep me signed in on this device') }}</span></label>
        <button class="btn primary block" type="submit" id="login-btn">{{ __('Sign in') }}</button>
    </form>
    <p class="auth-alt">{{ __('New here?') }} <a href="{{ route('signup') }}">{{ __('Start a free trial') }}</a></p>
    @if (app()->environment('local'))<p class="auth-alt">{{ __('Local testing: owner@example.com / password') }}</p>@endif
@endsection
@push('scripts')
<script>
(function () {
  var pw = document.getElementById('password');
  document.getElementById('pw-toggle').addEventListener('click', function () {
    var show = pw.type === 'password'; pw.type = show ? 'text' : 'password'; this.textContent = show ? @json(__('Hide')) : @json(__('Show')); this.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
  });
  document.querySelector('form').addEventListener('submit', function () { var b = document.getElementById('login-btn'); setTimeout(function () { b.disabled = true; b.textContent = @json(__('Signing in...')); }, 0); });
})();
</script>
@endpush
