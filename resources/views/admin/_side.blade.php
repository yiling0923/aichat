@php
    $accent = $business->settings['accent'] ?? null;
    $waiting = \App\Models\Conversation::query()->where('channel', '!=', 'preview')->where('status', 'open')->where('mode', 'human')->count();
    $isOwner = auth()->user()->role === 'owner';
@endphp
@if ($accent && preg_match('/^#[0-9a-fA-F]{6}$/', $accent))
    <style>:root { --teal: {{ $accent }}; }</style>
@endif
<aside class="side">
    <div class="ws">
        <span class="logo" style="width:32px;height:32px">{{ mb_substr($business->name, 0, 1) }}</span>
        <div><b>{{ $business->name }}</b><small>Staff area</small></div>
    </div>
    <div class="sec">Work</div>
    <a @class(['on' => $active === 'today']) href="{{ route('today') }}">Today</a>
    <a @class(['on' => $active === 'inbox']) href="{{ route('inbox') }}">Inbox @if ($waiting)<span class="badge">{{ $waiting }}</span>@endif</a>
    <a @class(['on' => $active === 'conversations']) href="{{ route('conversations') }}">All conversations</a>
    <a @class(['on' => $active === 'schedule']) href="{{ route('schedule') }}">Schedule</a>
    <a @class(['on' => $active === 'tasks']) href="{{ route('tasks') }}">Tasks</a>
    <div class="sec">People</div>
    <a @class(['on' => $active === 'customers']) href="{{ route('customers') }}">Customers</a>
    @if (auth()->user()->canManage())
        <a @class(['on' => $active === 'tags']) href="{{ route('tags') }}">Tags</a>
    @endif
    <div class="sec">Set up</div>
    <a @class(['on' => $active === 'library']) href="{{ route('library') }}">Library</a>
    @if ($isOwner)
        <a @class(['on' => $active === 'menu']) href="{{ route('menu') }}">Menu builder</a>
        <a @class(['on' => $active === 'setup']) href="{{ route('booking.setup') }}">Booking setup</a>
        <a @class(['on' => $active === 'settings']) href="{{ route('settings') }}">Settings</a>
    @endif
    <div class="me">
        {{ auth()->user()->name }}<br><span style="opacity:.7">{{ auth()->user()->role }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
    </div>
</aside>
