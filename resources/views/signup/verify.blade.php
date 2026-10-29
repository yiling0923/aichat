<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Confirm your email') }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="auth-lang">@include('_guest_lang')</div>
<div class="center-page">
    <div class="card auth-card center">
        <div class="verify-ic">&#9993;</div>
        <h1>{{ __('Check your email') }}</h1>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        <p>{{ __('We sent a confirmation link to') }}<br><b>{{ $email ?? __('your email') }}</b></p>
        <p class="mute" style="margin:8px 0 16px">{{ __('Open it to start. No email? Look in spam, or send it again.') }}</p>
        @if (app()->environment('local'))<p class="note warn" style="text-align:left">{!! __('Testing on your own computer: the email is written to :file. Open that file and copy the link.', ['file' => '<code>storage/logs/laravel.log</code>']) !!}</p>@endif
        @auth
            <form method="post" action="{{ route('verification.resend') }}">@csrf<button class="btn primary block" type="submit">{{ __('Send it again') }}</button></form>
            <form method="post" action="{{ route('logout') }}" style="margin-top:8px">@csrf<button class="btn block" type="submit">{{ __('Sign out') }}</button></form>
        @endauth
    </div>
</div>
</body>
</html>
