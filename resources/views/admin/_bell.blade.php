<div class="bell" id="bell" data-url="{{ url('/admin/api/notifications') }}">
    <button type="button" class="bell-btn" id="bell-btn" aria-label="{{ __('Notifications') }}" aria-haspopup="true">
        <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <span class="bell-badge" id="bell-badge" hidden>0</span>
    </button>
    <div class="bell-menu" id="bell-menu" hidden>
        <div class="bell-head"><b>{{ __('Notifications') }}</b><button type="button" id="bell-all">{{ __('Mark all read') }}</button></div>
        <div class="bell-list" id="bell-list"><p class="bell-empty">{{ __('Loading...') }}</p></div>
        <div class="bell-foot"><button type="button" id="bell-sound">{{ __('Sound is on') }}</button><button type="button" id="bell-desktop">{{ __('Turn on desktop alerts') }}</button></div>
    </div>
</div>
