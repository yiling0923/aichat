<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Messaging Sales Assistant</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="center-page">
    <div class="card">
        <div class="brand-row"><span class="logo">A</span> AI Messaging Sales Assistant</div>
        <h1>Welcome</h1>
        <p class="mute" style="margin-bottom:14px">Try the customer chat, or sign in to the staff area.</p>

        <ul class="plain">
            @forelse ($businesses as $business)
                <li>
                    <span><b>{{ $business->name }}</b></span>
                    <a class="btn primary" href="{{ route('chat.show', $business->slug) }}">Open test chat</a>
                </li>
            @empty
                <li class="mute">No business yet. Run <code>php artisan migrate --seed</code>.</li>
            @endforelse
            <li>
                <span><b>Staff area</b><br><span class="mute">owner@example.com / password (sample login)</span></span>
                <a class="btn" href="{{ route('login') }}">Sign in</a>
            </li>
        </ul>
    </div>
</div>
</body>
</html>
