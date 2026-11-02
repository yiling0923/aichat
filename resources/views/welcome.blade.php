<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Turn every chat into a booking') }}</title>
    <meta property="og:title" content="{{ __('Turn every chat into a booking') }}">
    <meta name="theme-color" content="#0f766e">
    <meta name="description" content="{{ __('One inbox for WhatsApp, Instagram, Messenger and your website. A bot that answers instantly, takes bookings and hands over to your team.') }}">
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body class="lp">
<header class="lp-nav">
    <a class="lp-brand" href="{{ route('home') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
    <nav>
        <a href="#features">{{ __('Features') }}</a>
        <a href="#pricing">{{ __('Pricing') }}</a>
        <a href="#faq">{{ __('FAQ') }}</a>
        @include('_guest_lang')
        <a class="btn" href="{{ route('login') }}">{{ __('Sign in') }}</a>
        <a class="btn primary" href="{{ route('signup') }}">{{ __('Start free trial') }}</a>
    </nav>
</header>

<section class="lp-hero">
    <div class="lp-hero-in">
        <div class="lp-hero-text">
            <span class="lp-pill">{{ __('For salons, clinics, studios and shops') }}</span>
            <h1>{{ __('Turn every chat into a booking') }}</h1>
            <p>{{ __('One inbox for WhatsApp, Instagram, Messenger and your website. A bot answers customers instantly in English and 中文, books them into your calendar, and hands over to your team when a person is needed.') }}</p>
            <div class="lp-cta">
                <a class="btn primary big" href="{{ route('signup') }}">{{ __('Start your :n-day free trial', ['n' => config('plans.trial_days')]) }}</a>
                <a class="btn big" href="#how">{{ __('See how it works') }}</a>
            </div>
            <small class="mute">{{ __('No card needed. Set up in about 10 minutes.') }}</small>
        </div>
        <div class="lp-phone" aria-hidden="true">
            <div class="lp-phone-top"><span class="logo">G</span><div><b>Glow Beauty Studio</b><small>{{ __('Replies instantly') }}</small></div></div>
            <div class="lp-msgs">
                <div class="m in">{{ __('Hi, do you have a slot for a facial this Saturday?') }}</div>
                <div class="m out">{{ __('Yes! Saturday has 11:00, 14:30 and 16:00 free. Which suits you?') }}</div>
                <div class="m in">{{ __('14:30 please') }}</div>
                <div class="m out ok">&#10003; {{ __('Booked: Facial, Sat 14:30. We will remind you the day before.') }}</div>
            </div>
        </div>
    </div>
    <div class="lp-channels"><span>{{ __('Works with') }}</span><b>WhatsApp</b><b>Instagram</b><b>Messenger</b><b>{{ __('Website chat') }}</b><b>Google Calendar</b></div>
</section>

<section id="features" class="lp-section">
    <h2>{{ __('Everything a busy front desk does, without the busy') }}</h2>
    <div class="lp-grid">
        @foreach ([
            ['&#9889;', 'Instant replies, day and night', 'Prices, opening hours, location and offers answered in seconds, in English and Chinese.'],
            ['&#128197;', 'Bookings that cannot clash', 'Customers pick a service, day and time. Double bookings are blocked and Google Calendar stays in sync.'],
            ['&#128172;', 'One shared team inbox', 'Every channel in one place. The bot steps aside when a person takes over, and gives the chat back when you are done.'],
            ['&#10024;', 'Smart AI help, under control', 'AI can answer free-text questions from your own information only, or just suggest replies for your team to approve.'],
            ['&#128276;', 'Follow-ups that run themselves', 'Reminders, satisfaction surveys, review requests and win-back messages, only to people who agreed to hear from you.'],
            ['&#128200;', 'Clear reports', 'Chats, bookings, no-shows, busiest times, survey scores and revenue, with CSV export.'],
        ] as [$icon, $title, $text])
            <div class="lp-card"><span class="lp-ic">{!! $icon !!}</span><h3>{{ __($title) }}</h3><p>{{ __($text) }}</p></div>
        @endforeach
    </div>
</section>

<section id="how" class="lp-section alt">
    <h2>{{ __('How it works') }}</h2>
    <div class="lp-steps">
        <div><span class="n">1</span><h3>{{ __('Describe your business') }}</h3><p>{{ __('Paste your price list or website text. We draft your services and answers. You approve them.') }}</p></div>
        <div><span class="n">2</span><h3>{{ __('Connect your channels') }}</h3><p>{{ __('Link your calendar and WhatsApp with guided steps and test buttons. Or start with the website chat.') }}</p></div>
        <div><span class="n">3</span><h3>{{ __('Go live') }}</h3><p>{{ __('Share your chat link. Your team sees every conversation and steps in whenever they want.') }}</p></div>
    </div>
</section>

<section id="pricing" class="lp-section">
    <h2>{{ __('Simple plans') }}</h2>
    <p class="mute lp-sub">{{ __('Start free. Upgrade when you are ready.') }}</p>
    <div class="lp-grid three">
        @foreach ($plansCatalog as $key => $plan)
            <div class="lp-card price {{ $key === 'starter' ? 'pop' : '' }}">@if ($key === 'starter')<span class="lp-badge">{{ __('Most popular') }}</span>@endif
                <h3>{{ __($plan['name']) }}</h3>
                <p class="amount">{{ $plan['price_cents'] === 0 ? __('Free') : $plansCurrency.' '.number_format($plan['price_cents'] / 100) }}<small>{{ $plan['price_cents'] === 0 ? ' '.__('for :n days', ['n' => config('plans.trial_days')]) : ' '.__('/ month') }}</small></p>
                <ul>
                    @foreach ($plan['limits'] as $kind => $limit)
                        <li>{{ number_format($limit) }} {{ __(['ai' => 'AI replies a month', 'staff' => 'team members', 'practitioners' => 'practitioners or rooms', 'channels' => $limit === 1 ? 'connected channel' : 'connected channels'][$kind]) }}</li>
                    @endforeach
                </ul>
                <a class="btn {{ $key === 'starter' ? 'primary' : '' }}" href="{{ route('signup') }}">{{ $plan['price_cents'] === 0 ? __('Start free trial') : __('Choose :plan', ['plan' => __($plan['name'])]) }}</a>
            </div>
        @endforeach
    </div>
</section>

<section id="faq" class="lp-section">
    <h2>{{ __('Questions people ask') }}</h2>
    <div class="lp-faq">
        <details><summary>{{ __('Do I need technical skills?') }}</summary><p>{{ __('No. You paste your price list, we draft your services and answers, and you approve them. Connecting channels is guided, with test buttons.') }}</p></details>
        <details><summary>{{ __('Will the bot say something wrong?') }}</summary><p>{{ __('The menu bot only says what you wrote. The optional AI answers from your own information and can be set to only suggest replies that your team approves first.') }}</p></details>
        <details><summary>{{ __('Can my team take over a chat?') }}</summary><p>{{ __('Yes. Reply in the inbox and the bot stays quiet. Press "Give back to bot" when you are done.') }}</p></details>
        <details><summary>{{ __('Which languages are supported?') }}</summary><p>{{ __('English and Chinese today. The bot replies in the language the customer uses.') }}</p></details>
        <details><summary>{{ __('What happens after the free trial?') }}</summary><p>{{ __('You choose a plan or stop. Nothing is charged without your approval, and you can export your customer list any time.') }}</p></details>
        <details><summary>{{ __('Is my customers\' data safe?') }}</summary><p>{{ __('Each business only sees its own data. Customers can ask for their data to be downloaded or erased, and promotions go only to people who agreed.') }}</p></details>
    </div>
</section>

<section class="lp-final">
    <h2>{{ __('Stop losing customers to slow replies') }}</h2>
    <p>{{ __('Start your free trial and have your first chat link live today.') }}</p>
    <a class="btn big lp-white" href="{{ route('signup') }}">{{ __('Start your :n-day free trial', ['n' => config('plans.trial_days')]) }}</a>
</section>

@if (app()->environment('local'))
    <section class="lp-section alt">
        <h2>Try it now <span class="tag warn">local testing only</span></h2>
        <ul class="plain lp-demo">
            @foreach ($businesses as $business)
                <li><span><b>{{ $business->name }}</b></span><a class="btn primary" href="{{ route('chat.show', $business->slug) }}">Open test chat</a></li>
            @endforeach
            <li><span><b>Staff area</b><br><span class="mute">owner@example.com / password (sample login)</span></span><a class="btn" href="{{ route('login') }}">Sign in</a></li>
        </ul>
    </section>
@endif

<footer class="lp-foot">
    <span>&copy; {{ date('Y') }} AI Messaging Sales Assistant</span>
    <span><a href="#features">{{ __('Features') }}</a> · <a href="#pricing">{{ __('Pricing') }}</a> · <a href="#faq">{{ __('FAQ') }}</a> · <a href="{{ route('terms') }}">{{ __('Terms') }}</a> · <a href="{{ route('privacy') }}">{{ __('Privacy') }}</a> · <a href="{{ route('login') }}">{{ __('Sign in') }}</a></span>
</footer>
</body>
</html>
