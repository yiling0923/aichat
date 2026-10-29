@extends('auth.layout')
@section('title', __('Choose a new password'))
@section('heading', __('Choose a new password'))
@section('sub', __('At least 8 characters.'))
@section('body')
    <form method="post" action="{{ route('password.update') }}" class="auth-card" style="padding:0;border:0;box-shadow:none;max-width:none">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="f"><span>{{ __('Email') }}</span>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" @class(['bad' => $errors->has('email')])>
            @error('email')<small class="field-err">{{ $message }}</small>@enderror</label>
        <label class="f"><span>{{ __('New password') }}</span>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" @class(['bad' => $errors->has('password')])>
            @error('password')<small class="field-err">{{ $message }}</small>@enderror</label>
        <label class="f"><span>{{ __('Repeat it') }}</span><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></label>
        <button class="btn primary block" type="submit">{{ __('Save password') }}</button>
    </form>
    <p class="auth-alt"><a href="{{ route('login') }}">&larr; {{ __('Back to sign in') }}</a></p>
@endsection
