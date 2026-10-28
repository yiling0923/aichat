<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <meta name="description" content="@yield('title') for AI Messaging Sales Assistant.">
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body class="lp">
<header class="lp-nav">
    <a class="lp-brand" href="{{ route('home') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
    <nav>
        <a href="{{ route('terms') }}" @if (request()->routeIs('terms')) style="color:var(--ink)" @endif>Terms</a>
        <a href="{{ route('privacy') }}" @if (request()->routeIs('privacy')) style="color:var(--ink)" @endif>Privacy</a>
        <a class="btn" href="{{ route('login') }}">Sign in</a>
    </nav>
</header>

<main class="legal">
    <div class="legal-head">
        <h1>@yield('title')</h1>
        <p class="mute">Last updated {{ $updated }}</p>
        <div class="note warn">Starter text only. Have a lawyer review and complete it for your country and company name before you open to the public.</div>
    </div>
    <div class="legal-body">
        <nav class="legal-toc" aria-label="On this page">
            <b>On this page</b>
            @foreach ($sections as $i => [$heading, $paras])<a href="#s{{ $i + 1 }}">{{ $i + 1 }}. {{ $heading }}</a>@endforeach
        </nav>
        <article>
            @foreach ($sections as $i => [$heading, $paras])
                <section id="s{{ $i + 1 }}">
                    <h2>{{ $i + 1 }}. {{ $heading }}</h2>
                    @foreach ($paras as $p)
                        @if (is_array($p))<ul>@foreach ($p as $li)<li>{{ $li }}</li>@endforeach</ul>@else<p>{{ $p }}</p>@endif
                    @endforeach
                </section>
            @endforeach
            <p class="legal-contact">Questions? Write to <a href="mailto:{{ $contact }}">{{ $contact }}</a>. @yield('also')</p>
        </article>
    </div>
</main>

<footer class="lp-foot">
    <span>&copy; {{ date('Y') }} AI Messaging Sales Assistant</span>
    <span><a href="{{ route('home') }}">Home</a> · <a href="{{ route('terms') }}">Terms</a> · <a href="{{ route('privacy') }}">Privacy</a> · <a href="{{ route('login') }}">Sign in</a></span>
</footer>
</body>
</html>
