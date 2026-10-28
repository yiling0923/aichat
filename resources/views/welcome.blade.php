<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Turn every chat into a booking</title>
    <meta name="description" content="One inbox for WhatsApp, Instagram, Messenger and your website. A bot that answers instantly, takes bookings and hands over to your team.">
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body class="lp">
<header class="lp-nav">
    <a class="lp-brand" href="{{ route('home') }}"><span class="logo">A</span> AI Messaging Sales Assistant</a>
    <nav>
        <a href="#features">Features</a>
        <a href="#pricing">Pricing</a>
        <a class="btn" href="{{ route('login') }}">Sign in</a>
        <a class="btn primary" href="{{ route('signup') }}">Start free trial</a>
    </nav>
</header>

<section class="lp-hero">
    <h1>Turn every chat into a booking</h1>
    <p>One inbox for WhatsApp, Instagram, Messenger and your website. A bot answers customers instantly in English and 中文, takes bookings straight into your calendar, and hands over to your team when a person is needed.</p>
    <div class="lp-cta">
        <a class="btn primary big" href="{{ route('signup') }}">Start your {{ config('plans.trial_days') }}-day free trial</a>
        <a class="btn big" href="#how">See how it works</a>
    </div>
    <small class="mute">No card needed. Set up in about 10 minutes.</small>
</section>

<section id="features" class="lp-section">
    <h2>Everything a busy front desk does, without the busy</h2>
    <div class="lp-grid">
        @foreach ([
            ['Instant replies, day and night', 'Prices, opening hours, location and offers answered in seconds, in English and Chinese.'],
            ['Bookings that cannot clash', 'Customers pick a service, day and time. Double bookings are blocked and Google Calendar stays in sync.'],
            ['One shared team inbox', 'Every channel in one place. The bot steps aside when a person takes over, and gives the chat back when you are done.'],
            ['Smart AI help, under control', 'AI can answer free-text questions from your own information only, or just suggest replies for your team to approve.'],
            ['Follow-ups that run themselves', 'Reminders, satisfaction surveys, review requests and win-back messages, only to people who agreed to hear from you.'],
            ['Clear reports', 'Chats, bookings, no-shows, busiest times, survey scores and revenue, with CSV export.'],
        ] as [$title, $text])
            <div class="lp-card"><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
        @endforeach
    </div>
</section>

<section id="how" class="lp-section alt">
    <h2>How it works</h2>
    <div class="lp-steps">
        <div><span class="n">1</span><h3>Describe your business</h3><p>Paste your price list or website text. We draft your services and answers. You approve them.</p></div>
        <div><span class="n">2</span><h3>Connect your channels</h3><p>Link your calendar and WhatsApp with guided steps and test buttons. Or start with the website chat.</p></div>
        <div><span class="n">3</span><h3>Go live</h3><p>Share your chat link. Your team sees every conversation and steps in whenever they want.</p></div>
    </div>
</section>

<section id="pricing" class="lp-section">
    <h2>Simple plans</h2>
    <p class="mute lp-sub">Start free. Upgrade when you are ready.</p>
    <div class="lp-grid three">
        @foreach (config('plans.plans') as $key => $plan)
            <div class="lp-card price">
                <h3>{{ $plan['name'] }}</h3>
                <p class="amount">{{ $plan['price_cents'] === 0 ? 'Free' : '$'.number_format($plan['price_cents'] / 100) }}<small>{{ $plan['price_cents'] === 0 ? ' for '.config('plans.trial_days').' days' : ' / month' }}</small></p>
                <ul>
                    @foreach ($plan['limits'] as $kind => $limit)
                        <li>{{ number_format($limit) }} {{ ['ai' => 'AI replies a month', 'staff' => 'team members', 'practitioners' => 'practitioners or rooms', 'channels' => $limit === 1 ? 'connected channel' : 'connected channels'][$kind] }}</li>
                    @endforeach
                </ul>
                <a class="btn {{ $key === 'starter' ? 'primary' : '' }}" href="{{ route('signup') }}">{{ $plan['price_cents'] === 0 ? 'Start free trial' : 'Choose '.$plan['name'] }}</a>
            </div>
        @endforeach
    </div>
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
    <span><a href="{{ route('terms') }}">Terms</a> · <a href="{{ route('privacy') }}">Privacy</a> · <a href="{{ route('login') }}">Sign in</a></span>
</footer>
</body>
</html>
