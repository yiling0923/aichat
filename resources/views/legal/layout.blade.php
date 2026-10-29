<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <meta name="description" content="@yield('title')">
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body class="lp">
<header class="lp-nav">
    <a class="lp-brand" href="{{ route('home') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
    <nav>
        <a href="{{ route('terms') }}" @if (request()->routeIs('terms')) style="color:var(--ink)" @endif>{{ __('Terms') }}</a>
        <a href="{{ route('privacy') }}" @if (request()->routeIs('privacy')) style="color:var(--ink)" @endif>{{ __('Privacy') }}</a>
        @include('_guest_lang')
        <a class="btn" href="{{ route('login') }}">{{ __('Sign in') }}</a>
    </nav>
</header>

<main class="legal">
    <div class="legal-head">
        <h1>@yield('title')</h1>
        <p class="mute">{{ __('Last updated :date', ['date' => $updated]) }}</p>
        <div class="note warn">{{ __('Starter text only. Have a lawyer review and complete it for your country and company name before you open to the public.') }}</div>
    </div>
    <div class="legal-body">
        <nav class="legal-toc" aria-label="{{ __('On this page') }}">
            <b>{{ __('On this page') }}</b>
            @foreach ($sections as $i => [$heading, $paras])<a href="#s{{ $i + 1 }}">{{ $i + 1 }}. {{ __($heading) }}</a>@endforeach
        </nav>
        <article>
            @foreach ($sections as $i => [$heading, $paras])
                <section id="s{{ $i + 1 }}">
                    <h2>{{ $i + 1 }}. {{ __($heading) }}</h2>
                    @foreach ($paras as $p)
                        @if (is_array($p))<ul>@foreach ($p as $li)<li>{{ __($li) }}</li>@endforeach</ul>@else<p>{{ __($p) }}</p>@endif
                    @endforeach
                </section>
            @endforeach
            <p class="legal-contact">{!! __('Questions? Write to :email.', ['email' => '<a href="mailto:'.e($contact).'">'.e($contact).'</a>']) !!} @yield('also')</p>
        </article>
    </div>
</main>

<footer class="lp-foot">
    <span>&copy; {{ date('Y') }} AI Messaging Sales Assistant</span>
    <span><a href="{{ route('home') }}">{{ __('Home') }}</a> · <a href="{{ route('terms') }}">{{ __('Terms') }}</a> · <a href="{{ route('privacy') }}">{{ __('Privacy') }}</a> · <a href="{{ route('login') }}">{{ __('Sign in') }}</a></span>
</footer>
</body>
</html>
