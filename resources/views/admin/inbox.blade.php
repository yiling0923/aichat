<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inbox: {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
    @include('admin._push')
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => 'inbox'])

    <main class="inbox" id="inbox" data-base="{{ url('/admin/api') }}" data-business="{{ $business->name }}">
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
            <div class="empty" id="thread-empty">
                <div class="empty-ic">&#9993;</div>
                <b>Select a conversation</b>
                <p class="mute">Pick a chat on the left to read it and reply.</p>
            </div>
            <div id="thread" class="hidden">
                <div class="thread-head">
                    <button class="btn small back-btn" id="btn-back" type="button" aria-label="Back to the list">&larr;</button>
                    <span class="th-av" id="thread-av"></span>
                    <div class="th-id">
                        <b id="thread-title"></b>
                        <div class="mute" id="thread-meta"></div>
                    </div>
                    <span class="tag" id="mode-badge"></span>
                    <div class="th-actions">
                        <button class="btn small primary" id="btn-resolve" type="button">Resolve</button>
                        <div class="more" id="more">
                            <button class="btn small more-btn" id="more-btn" type="button" aria-label="More actions" aria-haspopup="true">&middot;&middot;&middot;</button>
                            <div class="more-menu" id="more-menu" hidden>
                                <button type="button" id="btn-summarize" style="display:none">Summarise with AI</button>
                                <button type="button" id="btn-give-back">Give back to the bot</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="note warn hidden" id="window-bar">
                    <span id="window-text"></span>
                    <button class="btn small" id="btn-template" type="button">Send a template</button>
                </div>
                <div class="info-bar" id="info-bar">
                    <span id="info-customer" class="ib-cust"></span>
                    <span id="info-tags" class="ib-tags"></span>
                    <select id="tag-add" class="ib-add" aria-label="Add tag"></select>
                    <span class="ib-right">
                        <label class="ib-assign" title="Who looks after this chat"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg><select id="assign" aria-label="Handled by"></select></label>
                        <button class="btn small" id="btn-task" type="button">+ Task</button>
                    </span>
                </div>
                <div class="log-wrap">
                    <div class="thread-log" id="thread-log"></div>
                    <button class="jump hidden" id="btn-jump" type="button">New messages &darr;</button>
                </div>
                <div class="composer" id="composer">
                    <div class="c-tabs">
                        <button type="button" class="on" data-mode="reply">Reply to customer</button>
                        <button type="button" data-mode="note">Private note</button>
                        <select id="qr" aria-label="Saved replies"><option value="">Saved replies...</option></select>
                    </div>
                    <div class="qr-sugg" id="qr-sugg" hidden><span>Suggested</span></div>
                    <textarea id="reply-text" rows="2" placeholder="Write a reply. Enter sends, Shift+Enter starts a new line."></textarea>
                    <div class="row">
                        <span class="mute" id="c-hint">Replying makes you the owner of the chat, so the bot stays quiet.</span>
                        <button class="btn primary" id="btn-send" type="button">Send reply</button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<dialog id="task-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="Close">&times;</button>
    <h3>Add a task for this chat</h3>
    <label class="f" style="margin-top:12px"><span>What needs doing?</span><input type="text" id="tk-title" maxlength="200" placeholder="Call the customer back"></label>
    <label class="f"><span>Due (optional)</span><input type="datetime-local" id="tk-due"></label>
    <div class="dlg-foot"><button class="btn primary" id="tk-save" type="button">Add task</button></div>
</dialog>
<dialog id="tpl-dialog" class="dlg wide">
    <button type="button" class="dlg-x" aria-label="Close">&times;</button>
    <h3>Send a template</h3>
    <p class="mute" style="margin-top:4px">The 24-hour window is closed, so WhatsApp only allows a template Meta has approved.</p>
    <div id="tpl-options" class="tpl-options"></div>
    <div id="tpl-params"></div>
    <div class="f"><span>How it will look</span><div class="wa-bubble" id="tpl-preview">Choose a template.</div></div>
    <div class="dlg-foot"><button class="btn primary" id="tpl-send" type="button" disabled>Send template</button></div>
</dialog>
<div class="toast" id="toast" hidden></div>
<script src="{{ asset('js/suggest.js') }}"></script>
<script src="{{ asset('js/inbox.js') }}"></script>
<script src="{{ asset('js/push.js') }}"></script>
<script src="{{ asset('js/notifications.js') }}"></script>
</body>
</html>
