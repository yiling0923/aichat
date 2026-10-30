<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title'): {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
    @include('admin._push')
    @include('admin._i18n')
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => $active])
    <main class="page">
        <div class="topbar">
            @include('admin._bell')
            @include('admin._lang_switch')
            <div class="gsearch">
                <input id="gsearch" type="search" placeholder="{{ __('Search customers and chats') }}" autocomplete="off" aria-label="{{ __('Search') }}" data-url="{{ url('/admin/api/search') }}">
                <kbd>Ctrl K</kbd>
                <div id="gresults" class="gresults" hidden></div>
            </div>
        </div>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        @if (in_array($active, ['tags', 'settings', 'billing']) && auth()->user()->canManage())
            <a class="crumb" href="{{ route('manage') }}">&larr; {{ __('Settings') }}</a>
        @endif
        @if (in_array($active, ['today', 'automations', 'manage', 'settings'], true) && auth()->user()->canManage() && ! (($beat = \Illuminate\Support\Facades\Cache::get('scheduler-heartbeat')) && \Illuminate\Support\Carbon::parse($beat)->gt(now()->subMinutes(5))))
            <div class="note warn" id="scheduler-warn"><b>{{ __('Automatic jobs are not running.') }}</b> {{ __('Staff reminders, automations, booking reminders and the daily email only work while they run.') }} {!! __('On this PC, double-click :file in the project folder once.', ['file' => '<code>tools\\setup-scheduler.bat</code>']) !!}</div>
        @endif
        @php($planState = app(\App\Billing\Plans::class)->state($business))
        @if (in_array($planState, ['trial_expired', 'past_due', 'canceled'], true))
            <div class="note err">{{ __('Your plan is not active, so the AI is paused. Messages still reach your inbox.') }} @if (auth()->user()->role === 'owner')<a href="{{ route('billing') }}">{{ __('Plan & billing') }}</a>@endif</div>
        @elseif ($planState === 'trial')
            <div class="note warn">@php($dl = app(\App\Billing\Plans::class)->daysLeftInTrial($business)){{ $dl === 1 ? __('Free trial: 1 day left.') : __('Free trial: :n days left.', ['n' => $dl]) }} @if (auth()->user()->role === 'owner')<a href="{{ route('billing') }}">{{ __('Choose a plan') }}</a>@endif</div>
        @endif
        @foreach ($errors->all() as $error)<div class="note err">{{ $error }}</div>@endforeach
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/search.js') }}"></script>
<script src="{{ asset('js/push.js') }}"></script>
<script src="{{ asset('js/notifications.js') }}"></script>
<script src="{{ asset('js/autofilter.js') }}"></script>
@stack('scripts')
</body>
</html>
