@php
    $accent = $business->settings['accent'] ?? null;
    $waiting = \App\Models\Conversation::query()->where('channel', '!=', 'preview')->where('status', 'open')->where('mode', 'human')->count();
    $isOwner = auth()->user()->role === 'owner';
    $canManage = auth()->user()->canManage();
    $hubPages = ['manage', 'tags', 'settings', 'billing', 'setup-guide'];
    $marketingPages = ['automations', 'broadcasts', 'activity', 'templates'];
    $guideProgress = $isOwner ? app(\App\Support\SetupGuide::class)->cached($business, auth()->user()) : null;
@endphp
@if ($accent && preg_match('/^#[0-9a-fA-F]{6}$/', $accent))
    <style>:root { --accent: {{ $accent }}; }</style>
@endif
<aside class="side">
    <div class="ws">
        <span class="logo" style="width:32px;height:32px">{{ mb_substr($business->name, 0, 1) }}</span>
        <div><b>{{ $business->name }}</b><small>Staff area</small></div>
    </div>
    <a @class(['on' => $active === 'today']) href="{{ route('today') }}"><svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>Today</a>
    <a @class(['on' => $active === 'inbox']) href="{{ route('inbox') }}"><svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v7H2v-7z"/></svg>Inbox @if ($waiting)<span class="badge">{{ $waiting }}</span>@endif</a>
    <a @class(['on' => $active === 'schedule']) href="{{ route('schedule') }}"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Schedule</a>
    <a @class(['on' => in_array($active, ['customers', 'conversations'])]) href="{{ route('customers') }}"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg>Customers</a>
    <a @class(['on' => $active === 'tasks']) href="{{ route('tasks') }}"><svg viewBox="0 0 24 24"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>Tasks</a>
    <a @class(['on' => $active === 'orders']) href="{{ route('orders') }}"><svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>Orders</a>
    <div class="sec">Grow</div>
    @if ($canManage)
    <a @class(['on' => in_array($active, $marketingPages)]) href="{{ route('automations') }}"><svg viewBox="0 0 24 24"><path d="M3 11l18-8v18z"/><path d="M11 17l-1 5"/></svg>Marketing</a>
    @endif
    <a @class(['on' => $active === 'reports']) href="{{ route('reports') }}"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>Reports</a>
    @if ($canManage)
    <div class="sec">Set up</div>
    @if ($isOwner && $guideProgress && ! $guideProgress['complete'])
    <a @class(['on' => $active === 'guide']) href="{{ route('guide') }}"><svg viewBox="0 0 24 24"><path d="M5 3v18"/><path d="M5 4h12l-2 4 2 4H5"/></svg>Setup guide <span class="badge guide-badge">{{ $guideProgress['done'] }}/{{ $guideProgress['total'] }}</span></a>
    @endif
    @if ($isOwner)
    <a @class(['on' => $active === 'menu']) href="{{ route('menu') }}"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Menu builder</a>
    <a @class(['on' => $active === 'setup']) href="{{ route('booking.setup') }}"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>Booking setup</a>
    <a @class(['on' => $active === 'channels']) href="{{ route('channels') }}"><svg viewBox="0 0 24 24"><path d="M5 12.5a7 7 0 0 1 14 0M8.5 12.5a3.5 3.5 0 0 1 7 0"/><circle cx="12" cy="12.5" r="1"/><path d="M12 14v7"/></svg>Channels</a>
    @endif
    <a @class(['on' => $active === 'ai']) href="{{ route('ai') }}"><svg viewBox="0 0 24 24"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>AI assistant</a>
    <a @class(['on' => $active === 'library']) href="{{ route('library') }}"><svg viewBox="0 0 24 24"><path d="M4 4h16v13H8l-4 4z"/><path d="M8 9h8M8 13h5"/></svg>Saved replies</a>
    @endif
    @if ($canManage)
    <a @class(['on' => in_array($active, $hubPages)]) href="{{ route('manage') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>Settings</a>
    @endif
    <a @class(['on' => $active === 'help']) href="{{ route('help') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2 1-1.2 1.8"/><circle cx="12" cy="17" r=".6"/></svg>Help</a>
    @php($meName = auth()->user()->name)
    <div class="me">
        <a href="{{ route('account') }}" class="me-av" title="My account">{{ collect(preg_split('/\s+/', trim($meName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?' }}</a>
        <div class="me-text"><a href="{{ route('account') }}" class="me-link">{{ $meName }}</a><span>{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</span></div>
        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="me-out" title="Sign out" aria-label="Sign out"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></button></form>
    </div>
</aside>
