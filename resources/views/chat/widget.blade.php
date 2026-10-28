@php $accent = $business->settings['accent'] ?? null; @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Chat with {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
    @if ($accent && preg_match('/^#[0-9a-fA-F]{6}$/', $accent))<style>:root { --accent: {{ $accent }}; }</style><meta name="theme-color" content="{{ $accent }}">@endif
</head>
<body class="chat-body">
<div class="chat-wrap" id="chat"
     data-slug="{{ $business->slug }}"
     data-name="{{ $business->name }}"
     data-send-url="{{ route('chat.send', $business->slug) }}"
     data-poll-url="{{ route('chat.poll', $business->slug) }}">
    <div class="chat-head">
        <div class="av">{{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}</div>
        <div class="who-box"><b>{{ $business->name }}</b><small><i class="dot"></i><span id="chat-status">Replies instantly</span></small></div>
        <button type="button" class="chat-restart" id="chat-restart" title="Start a new chat" aria-label="Start a new chat">&#8635;</button>
    </div>
    <div class="chat-log" id="chat-log" aria-live="polite"></div>
    <div class="notice" id="chat-notice" role="status"></div>
    <div class="human-bar" id="human-bar" hidden><span>A team member will reply here. This can take a few minutes.<br>团队成员会尽快在此回复您。</span><button type="button" id="human-menu">Back to menu / 返回菜单</button></div>
    <form class="chat-form" id="chat-form" autocomplete="off">
        <input id="chat-input" type="text" maxlength="1000" placeholder="Type a message / 输入信息" aria-label="Message">
        <button type="submit" id="chat-send" aria-label="Send" disabled><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6 3.3 10l12 2-12 2z"/></svg></button>
    </form>
    <div class="chat-foot">Powered by AI Messaging Sales Assistant</div>
</div>
<script src="{{ asset('js/chat.js') }}"></script>
</body>
</html>
