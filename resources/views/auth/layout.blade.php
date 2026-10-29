<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="auth-lang">@include('_guest_lang')</div>
<div class="auth-split">
    <aside class="auth-side">
        <a class="auth-brand" href="{{ url('/') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
        <h2>{{ __('Welcome back.') }}</h2>
        <ul class="auth-points">
            <li>{!! __(':b is waiting: new chats, bookings and follow-ups.', ['b' => '<b>'.e(__('Your inbox')).'</b>']) !!}</li>
            <li>{!! __(':b while you are away and hands over when needed.', ['b' => '<b>'.e(__('The bot keeps working')).'</b>']) !!}</li>
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
