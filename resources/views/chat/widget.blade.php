<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $business->name }}: test chat</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="chat-wrap" id="chat"
     data-slug="{{ $business->slug }}"
     data-send-url="{{ route('chat.send', $business->slug) }}"
     data-poll-url="{{ route('chat.poll', $business->slug) }}">
    <div class="chat-head">
        <div class="av">{{ mb_substr($business->name, 0, 1) }}</div>
        <div><b>{{ $business->name }}</b><small>test chat</small></div>
    </div>
    <div class="chat-log" id="chat-log"></div>
    <div class="notice" id="chat-notice"></div>
    <form class="chat-form" id="chat-form" autocomplete="off">
        <input id="chat-input" type="text" maxlength="1000" placeholder="Type a message" aria-label="Message">
        <button type="submit">Send</button>
    </form>
</div>
<script src="{{ asset('js/chat.js') }}"></script>
</body>
</html>
