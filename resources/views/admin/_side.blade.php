@php
    $accent = $business->settings['accent'] ?? null;
    $waiting = \App\Models\Conversation::query()->where('channel', '!=', 'preview')->where('status', 'open')->where('mode', 'human')->count();
    $isOwner = auth()->user()->role === 'owner';
@endphp
@if ($accent && preg_match('/^#[0-9a-fA-F]{6}$/', $accent))
    <style>:root { --accent: {{ $accent }}; }</style>
@endif
<aside class="side">
    <div class="ws">
        <span class="logo" style="width:32px;height:32px">{{ mb_substr($business->name, 0, 1) }}</span>
        <div><b>{{ $business->name }}</b><small>Staff area</small></div>
    </div>
    <div class="sec">Work</div>
    <a @class(['on' => $active === 'today']) href="{{ route('today') }}"><svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>Today</a>
    <a @class(['on' => $active === 'inbox']) href="{{ route('inbox') }}"><svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v7H2v-7z"/></svg>Inbox @if ($waiting)<span class="badge">{{ $waiting }}</span>@endif</a>
    <a @class(['on' => $active === 'conversations']) href="{{ route('conversations') }}"><svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-5.4A8 8 0 1 1 21 12z"/></svg>All conversations</a>
    <a @class(['on' => $active === 'schedule']) href="{{ route('schedule') }}"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Schedule</a>
    <a @class(['on' => $active === 'tasks']) href="{{ route('tasks') }}"><svg viewBox="0 0 24 24"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>Tasks</a>
    <div class="sec">People</div>
    <a @class(['on' => $active === 'customers']) href="{{ route('customers') }}"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg>Customers</a>
    <a @class(['on' => $active === 'orders']) href="{{ route('orders') }}"><svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>Orders</a>
    @if (auth()->user()->canManage())
        <a @class(['on' => $active === 'tags']) href="{{ route('tags') }}"><svg viewBox="0 0 24 24"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>Tags</a>
    @endif
    <div class="sec">Grow</div>
    @if (auth()->user()->canManage())
        <a @class(['on' => $active === 'automations']) href="{{ route('automations') }}"><svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>Automations</a>
        <a @class(['on' => $active === 'broadcasts']) href="{{ route('broadcasts') }}"><svg viewBox="0 0 24 24"><path d="M3 11l18-8v18z"/><path d="M11 17l-1 5"/></svg>Broadcasts</a>
    @endif
    <a @class(['on' => $active === 'reports']) href="{{ route('reports') }}"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>Reports</a>
    <div class="sec">Set up</div>
    <a @class(['on' => $active === 'library']) href="{{ route('library') }}"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16"/></svg>Library</a>
    @if (auth()->user()->canManage())
        <a @class(['on' => $active === 'ai']) href="{{ route('ai') }}"><svg viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.4L12 15l-1.9-4.6L5.5 9l4.6-1.4z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/></svg>AI assistant</a>
        <a @class(['on' => $active === 'templates']) href="{{ route('templates') }}"><svg viewBox="0 0 24 24"><path d="M4 4h16v13H8l-4 4z"/><path d="M8 9h8M8 13h5"/></svg>Templates</a>
    @endif
    @if ($isOwner)
        <a @class(['on' => $active === 'channels']) href="{{ route('channels') }}"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>Channels</a>
        <a @class(['on' => $active === 'menu']) href="{{ route('menu') }}"><svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>Menu builder</a>
        <a @class(['on' => $active === 'setup']) href="{{ route('booking.setup') }}"><svg viewBox="0 0 24 24"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/></svg>Booking setup</a>
        <a @class(['on' => $active === 'settings']) href="{{ route('settings') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>Settings</a>
    @endif
    <div class="me">
        {{ auth()->user()->name }}<br><span style="opacity:.7">{{ auth()->user()->role }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
    </div>
</aside>
