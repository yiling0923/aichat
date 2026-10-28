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
    <div class="card">
        <div class="brand-row"><span class="logo">A</span> Check your email</div>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        <p>We sent a confirmation link to <b>{{ $email ?? 'your email' }}</b>. Open it to continue.</p>
        <p class="mute" style="margin:10px 0 14px">No email? Check spam, or send it again. On your own computer (mail driver "log") the email is written to <code>storage/logs/laravel.log</code>.</p>
        @auth
            <form method="post" action="{{ route('verification.resend') }}">@csrf<button class="btn primary" type="submit">Send it again</button></form>
            <form method="post" action="{{ route('logout') }}" style="margin-top:8px">@csrf<button class="btn" type="submit">Sign out</button></form>
        @endauth
    </div>
</div>
</body>
</html>
