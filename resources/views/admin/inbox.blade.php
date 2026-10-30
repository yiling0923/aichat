<!doctype html>
<html lang="{{ app()->getLocale() === 'zh' ? 'zh-CN' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Inbox') }}: {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
    @include('admin._pwa')
    @include('admin._push')
    @include('admin._i18n')
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => 'inbox'])

    <main class="inbox" id="inbox" data-base="{{ url('/admin/api') }}" data-business="{{ $business->name }}">
        <section class="list">
            <div class="list-head inbox-head">
                <div class="ih-top">
                    <h2 class="ih-title">{{ __('Inbox') }}</h2>
                    <div class="ih-tools">@include('admin._bell')@include('admin._lang_switch')</div>
                </div>
                <div class="ih-sub">
                    <select class="small" id="status-filter" aria-label="{{ __('Filter') }}">
                        <option value="open">{{ __('Open') }}</option>
                        <option value="resolved">{{ __('Resolved') }}</option>
                        <option value="all">{{ __('All') }}</option>
                    </select>
                    <a class="mini-link" href="{{ route('conversations') }}">{{ __('Search all chats') }} &rarr;</a>
                </div>
            </div>
            <div id="conv-list"></div>
        </section>

        <section class="thread">
            <div class="empty" id="thread-empty">
                <div class="empty-ic">&#9993;</div>
                <b>{{ __('Select a conversation') }}</b>
                <p class="mute">{{ __('Pick a chat on the left to read it and reply.') }}</p>
            </div>
            <div id="thread" class="hidden">
                <div class="thread-head">
                    <button class="btn small back-btn" id="btn-back" type="button" aria-label="{{ __('Back to the list') }}">&larr;</button>
                    <span class="th-av" id="thread-av"></span>
                    <div class="th-id">
                        <b id="thread-title"></b>
                        <div class="mute" id="thread-meta"></div>
                    </div>
                    <span class="tag" id="mode-badge"></span>
                    <div class="th-actions">
                        <button class="btn small primary" id="btn-resolve" type="button">{{ __('Resolve') }}</button>
                        <div class="more" id="more">
                            <button class="btn small more-btn" id="more-btn" type="button" aria-label="{{ __('More actions') }}" aria-haspopup="true">&middot;&middot;&middot;</button>
                            <div class="more-menu" id="more-menu" hidden>
                                <button type="button" id="btn-summarize" style="display:none">{{ __('Summarise with AI') }}</button>
                                <button type="button" id="btn-give-back">{{ __('Give back to the bot') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="note warn hidden" id="window-bar">
                    <span id="window-text"></span>
                    <button class="btn small" id="btn-template" type="button">{{ __('Send a template') }}</button>
                </div>
                <div class="info-bar" id="info-bar">
                    <span id="info-customer" class="ib-cust"></span>
                    <span id="info-tags" class="ib-tags"></span>
                    <select id="tag-add" class="ib-add" aria-label="{{ __('Add tag') }}"></select>
                    <span class="ib-right">
                        <label class="ib-assign" title="{{ __('Who looks after this chat') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg><select id="assign" aria-label="{{ __('Handled by') }}"></select></label>
                        <button class="btn small" id="btn-task" type="button">{{ __('+ Task') }}</button>
                    </span>
                </div>
                <div class="log-wrap">
                    <div class="thread-log" id="thread-log"></div>
                    <button class="jump hidden" id="btn-jump" type="button">{{ __('New messages') }} &darr;</button>
                </div>
                <div class="composer" id="composer">
                    <div class="c-tabs">
                        <button type="button" class="on" data-mode="reply">{{ __('Reply to customer') }}</button>
                        <button type="button" data-mode="note">{{ __('Private note') }}</button>
                        <select id="qr" aria-label="{{ __('Saved replies') }}"><option value="">{{ __('Saved replies...') }}</option></select>
                    </div>
                    <div class="qr-sugg" id="qr-sugg" hidden><span>{{ __('Suggested') }}</span></div>
                    <textarea id="reply-text" rows="2" placeholder="{{ __('Write a reply. Enter sends, Shift+Enter starts a new line.') }}"></textarea>
                    <div class="row">
                        <input type="file" id="file-pick" hidden accept="image/jpeg,image/png,video/mp4,audio/ogg,audio/mpeg,application/pdf,.doc,.docx,.xls,.xlsx,.txt">
                        <button class="btn attach" id="btn-attach" type="button" title="{{ __('Send a photo or file') }}" aria-label="{{ __('Send a photo or file') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.4 11.6l-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/></svg>
                            <span id="attach-label">{{ __('Attach') }}</span>
                        </button>
                        <span class="mute" id="c-hint">{{ __('Replying makes you the owner of the chat, so the bot stays quiet.') }}</span>
                        <button class="btn primary" id="btn-send" type="button">{{ __('Send reply') }}</button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<dialog id="task-dialog" class="dlg">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Add a task for this chat') }}</h3>
    <label class="f" style="margin-top:12px"><span>{{ __('What needs doing?') }}</span><input type="text" id="tk-title" maxlength="200" placeholder="{{ __('Call the customer back') }}"></label>
    <label class="f"><span>{{ __('Due (optional)') }}</span><input type="datetime-local" id="tk-due"></label>
    <div class="dlg-foot"><button class="btn primary" id="tk-save" type="button">{{ __('Add task') }}</button></div>
</dialog>
<dialog id="tpl-dialog" class="dlg wide">
    <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
    <h3>{{ __('Send a template') }}</h3>
    <p class="mute" style="margin-top:4px">{{ __('The 24-hour window is closed, so WhatsApp only allows a template Meta has approved.') }}</p>
    <div id="tpl-options" class="tpl-options"></div>
    <div id="tpl-params"></div>
    <div class="f"><span>{{ __('How it will look') }}</span><div class="wa-bubble" id="tpl-preview">{{ __('Choose a template.') }}</div></div>
    <div class="dlg-foot"><button class="btn primary" id="tpl-send" type="button" disabled>{{ __('Send template') }}</button></div>
</dialog>
<div class="toast" id="toast" hidden></div>
<script src="{{ asset('js/suggest.js') }}"></script>
<script src="{{ asset('js/inbox.js') }}"></script>
<script src="{{ asset('js/push.js') }}"></script>
<script src="{{ asset('js/notifications.js') }}"></script>
</body>
</html>
