<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm your email</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="center-page">
    <div class="card auth-card center">
        <div class="verify-ic">&#9993;</div>
        <h1>Check your email</h1>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        <p>We sent a confirmation link to<br><b>{{ $email ?? 'your email' }}</b></p>
        <p class="mute" style="margin:8px 0 16px">Open it to start. No email? Look in spam, or send it again.</p>
        @if (app()->environment('local'))<p class="note warn" style="text-align:left">Testing on your own computer: the email is written to <code>storage/logs/laravel.log</code>. Open that file and copy the link.</p>@endif
        @auth
            <form method="post" action="{{ route('verification.resend') }}">@csrf<button class="btn primary block" type="submit">Send it again</button></form>
            <form method="post" action="{{ route('logout') }}" style="margin-top:8px">@csrf<button class="btn block" type="submit">Sign out</button></form>
        @endauth
    </div>
</div>
</body>
</html>
