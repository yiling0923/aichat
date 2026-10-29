@extends('auth.layout')
@section('title', __('Forgot password'))
@section('heading', __('Forgot your password?'))
@section('sub', __('Enter your email and we will send you a link to choose a new one.'))
@section('body')
    <form method="post" action="{{ route('password.email') }}" class="auth-card" style="padding:0;border:0;box-shadow:none;max-width:none">@csrf
        <label class="f"><span>{{ __('Your email') }}</span>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" @class(['bad' => $errors->has('email')])>
            @error('email')<small class="field-err">{{ $message }}</small>@enderror</label>
        <button class="btn primary block" type="submit">{{ __('Email me a link') }}</button>
    </form>
    <p class="auth-alt"><a href="{{ route('login') }}">&larr; {{ __('Back to sign in') }}</a></p>
@endsection
