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
    @include('admin._side', ['active' => 'inbox'])

    <main class="inbox" id="inbox" data-base="{{ url('/admin/api') }}">
        <section class="list">
            <div class="list-head">
                <span>Inbox <a class="mini-link" href="{{ route('conversations') }}">Search all chats</a></span>
                @include('admin._bell')
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
                        <button class="btn" id="btn-summarize" type="button" style="display:none">Summarise (AI)</button>
                        <button class="btn" id="btn-give-back" type="button">Give back to bot</button>
                        <button class="btn danger" id="btn-resolve" type="button">Resolve</button>
                    </div>
                </div>
                <div class="note warn hidden" id="window-bar">
                    <span id="window-text"></span>
                    <button class="btn small" id="btn-template" type="button">Send a template</button>
                </div>
                <div class="info-bar" id="info-bar">
                    <span id="info-customer"></span>
                    <span id="info-tags"></span>
                    <select id="tag-add" aria-label="Add tag"></select>
                    <label>Handled by <select id="assign" aria-label="Assign"></select></label>
                    <button class="btn small" id="btn-task" type="button">Add task</button>
                </div>
                <div class="thread-log" id="thread-log"></div>
                <div class="composer">
                    <div class="qr-row"><select id="qr" aria-label="Saved replies"><option value="">Insert a saved reply...</option></select></div>
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
<script src="{{ asset('js/notifications.js') }}"></script>
</body>
</html>
