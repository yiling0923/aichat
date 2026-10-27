<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inbox: {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="ws">
            <span class="logo" style="width:32px;height:32px">{{ mb_substr($business->name, 0, 1) }}</span>
            <div><b>{{ $business->name }}</b><small>Staff inbox</small></div>
        </div>
        <div class="sec">Work</div>
        <a class="on" href="{{ route('inbox') }}">Inbox</a>
        <a class="soon">Schedule (coming)</a>
        <a class="soon">Customers (coming)</a>
        <a class="soon">Settings (coming)</a>
        <div class="me">
            {{ auth()->user()->name }}<br><span style="opacity:.7">{{ auth()->user()->role }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
        </div>
    </aside>

    <main class="inbox" id="inbox" data-base="{{ url('/admin/api') }}">
        <section class="list">
            <div class="list-head">
                Conversations
                <select class="small" id="status-filter" aria-label="Filter">
                    <option value="open">Open</option>
                    <option value="resolved">Resolved</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div id="conv-list"></div>
        </section>

        <section class="thread">
            <div class="empty" id="thread-empty">Select a conversation on the left.</div>
            <div id="thread" class="hidden">
                <div class="thread-head">
                    <div>
                        <b id="thread-title"></b>
                        <div class="mute" id="thread-meta" style="font-size:12px"></div>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button class="btn" id="btn-give-back" type="button">Give back to bot</button>
                        <button class="btn danger" id="btn-resolve" type="button">Resolve</button>
                    </div>
                </div>
                <div class="thread-log" id="thread-log"></div>
                <div class="composer">
                    <textarea id="reply-text" placeholder="Reply to the customer, or write a private note"></textarea>
                    <div class="row">
                        <button class="btn primary" id="btn-reply" type="button">Send reply</button>
                        <button class="btn" id="btn-note" type="button">Save as private note</button>
                        <span class="mute" style="font-size:12px">A reply makes you the owner of the chat, so the bot stays quiet.</span>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="{{ asset('js/inbox.js') }}"></script>
</body>
</html>
