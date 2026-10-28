<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="auth-split">
    <aside class="auth-side">
        <a class="auth-brand" href="{{ url('/') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
        <h2>Welcome back.</h2>
        <ul class="auth-points">
            <li><b>Your inbox</b> is waiting: new chats, bookings and follow-ups.</li>
            <li><b>The bot keeps working</b> while you are away and hands over when needed.</li>
        </ul>
    </aside>
    <main class="auth-main">
        <div class="auth-card">
            <a class="auth-brand mobile-only" href="{{ url('/') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
            <h1>@yield('heading')</h1>
            @hasSection('sub')<p class="mute">@yield('sub')</p>@endif
            @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
            @yield('body')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
