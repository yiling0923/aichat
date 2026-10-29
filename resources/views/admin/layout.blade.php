<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title'): {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => $active])
    <main class="page">
        <div class="topbar">
            @include('admin._bell')
            <div class="gsearch">
                <input id="gsearch" type="search" placeholder="Search customers and chats" autocomplete="off" aria-label="Search" data-url="{{ url('/admin/api/search') }}">
                <kbd>Ctrl K</kbd>
                <div id="gresults" class="gresults" hidden></div>
            </div>
        </div>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        @if (in_array($active, ['tags', 'settings', 'billing']) && auth()->user()->canManage())
            <a class="crumb" href="{{ route('manage') }}">&larr; Settings</a>
        @endif
        @php($planState = app(\App\Billing\Plans::class)->state($business))
        @if (in_array($planState, ['trial_expired', 'past_due', 'canceled'], true))
            <div class="note err">Your plan is not active, so the AI is paused. Messages still reach your inbox. @if (auth()->user()->role === 'owner')<a href="{{ route('billing') }}">Plan &amp; billing</a>@endif</div>
        @elseif ($planState === 'trial')
            <div class="note warn">Free trial: @php($dl = app(\App\Billing\Plans::class)->daysLeftInTrial($business)){{ $dl }} {{ $dl === 1 ? 'day' : 'days' }} left. @if (auth()->user()->role === 'owner')<a href="{{ route('billing') }}">Choose a plan</a>@endif</div>
        @endif
        @foreach ($errors->all() as $error)<div class="note err">{{ $error }}</div>@endforeach
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/search.js') }}"></script>
<script src="{{ asset('js/notifications.js') }}"></script>
<script src="{{ asset('js/autofilter.js') }}"></script>
@stack('scripts')
</body>
</html>
