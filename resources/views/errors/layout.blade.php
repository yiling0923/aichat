<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="center-page">
    <div class="card err-card">
        <div class="err-code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p class="mute">@yield('message')</p>
        <div class="err-actions">
            @hasSection('actions')@yield('actions')@else<a class="btn primary" href="{{ url('/') }}">{{ __('Go to the home page') }}</a>@endif
        </div>
    </div>
</div>
</body>
</html>
