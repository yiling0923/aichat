@extends('admin.layout')
@section('title', __('Automations'))
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'automations'])
    @php $onCount = $automations->where('enabled', true)->count(); @endphp
    <div class="bar spread au-head">
        <div>
            <h1>{{ __('Automations') }}</h1>
            <p class="mute">{{ __('Messages the app sends by itself. They only go to customers who agreed to be contacted and never sent STOP. Everything starts switched off.') }}</p>
        </div>
        <form method="post" action="{{ url('/admin/automations/run') }}">@csrf<button class="btn primary" onclick="return confirm({{ json_encode(__('Send every message that is due right now?'), JSON_UNESCAPED_UNICODE) }})">{{ __('Run now (test)') }}</button></form>
    </div>
    <div class="au-strip">
        <span class="au-count"><b>{{ $onCount }}</b> / {{ $automations->count() }} <small>{{ __('switched on') }}</small></span>
        <span class="au-note">{{ __('Checked every 5 minutes. On WhatsApp, Messenger and Instagram a message can only go out within 24 hours of the customer\'s last message, unless you give an approved WhatsApp template name.') }}</span>
    </div>
    @error('automation')<div class="note err">{{ $message }}</div>@enderror

    @foreach ($automations as $a)
        @php $d = $defs[$a->key]; @endphp
        <details class="card fold au-card @if ($a->enabled) is-on @endif" @if ($a->enabled) open @endif>
        <summary><span class="au-ic" aria-hidden="true"><svg viewBox="0 0 24 24">{!! ['survey' => '<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.5 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>', 'review_request' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', 'rebooking' => '<path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/>', 'no_show' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'hot_lead' => '<path d="M12 3c1 4 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-6 1-9z"/>'][$a->key] ?? '<circle cx="12" cy="12" r="9"/>' !!}</svg></span><span class="au-main"><b>{{ __($d['title']) }}</b> <span class="tag {{ $a->enabled ? 'ok' : '' }}">{{ $a->enabled ? __('On') : __('Off') }}</span><small class="mute fold-help">{{ __($d['help']) }}</small></span><span class="au-edit">{{ __('Edit') }}</span></summary>
        <form method="post" action="{{ url('/admin/automations/'.$a->key) }}">
            @csrf
            <div class="au-status">
                <label class="f"><span>{{ __('Status') }}</span>
                    <select name="enabled"><option value="0" @selected(! $a->enabled)>{{ __('Off') }}</option><option value="1" @selected($a->enabled)>{{ __('On') }}</option></select></label>
            </div>
            <div class="grid2">
                @if (in_array($a->key, ['survey', 'no_show', 'hot_lead']))
                    <label class="f"><span>{{ $a->key === 'survey' ? __('Wait (hours) after the visit ends') : ($a->key === 'no_show' ? __('Wait (hours) after it is marked No-show') : __('Wait (hours) after the customer was added')) }}</span>
                        <input type="number" name="delay_hours" value="{{ $a->delay_hours }}"></label>
                @endif
                @if ($a->key === 'rebooking')
                    <label class="f"><span>{{ __('Days since the last visit') }}</span><input type="number" name="rebook_days" value="{{ $a->settings['rebook_days'] ?? 30 }}"></label>
                @endif
                @if ($a->key === 'review_request')
                    <label class="f"><span>{{ __('Your review link (https://...)') }}</span><input type="text" name="review_url" value="{{ $a->settings['review_url'] ?? '' }}"></label>
                    <label class="f"><span>{{ __('Send it from this rating up (1 to 5)') }}</span><input type="number" name="min_rating" min="1" max="5" value="{{ $a->settings['min_rating'] ?? 4 }}"></label>
                @endif
                <label class="f"><span>{{ __('WhatsApp template name for after 24 hours (optional)') }}</span><input type="text" name="template_name" value="{{ $a->template_name }}" placeholder="{{ __('approved_template_name') }}"></label>
            </div>
            <div class="grid2">
                <label class="f"><span>{{ __('Message (English). You can use :vars', ['vars' => '{name} {service} {business}'.($a->key === 'review_request' ? ' {review_url}' : '')]) }}</span>
                    <textarea name="message_en" rows="3" required>{{ $a->message['en'] ?? '' }}</textarea></label>
                <label class="f"><span>{{ __('Message (中文)') }}</span><textarea name="message_zh" rows="3">{{ $a->message['zh'] ?? '' }}</textarea></label>
            </div>
            <button class="btn primary" type="submit">{{ __('Save') }}</button>
        </form>
        @if ($a->key === 'survey')
            <form method="post" action="{{ url('/admin/automations/survey/test') }}" class="test-row">
                @csrf
                @if ($testCustomers->isEmpty())
                    <span class="mute">{{ __('To send a test you need a customer who agreed to be contacted and has a chat. A customer agrees when they leave their details in the chat.') }}</span>
                @else
                    <label class="f grow"><span>{{ __('Send a test now to') }}</span>
                        <select name="customer_id">@foreach ($testCustomers as $c)<option value="{{ $c->id }}">{{ $c->name ?: $c->phone }}</option>@endforeach</select></label>
                    <button class="btn" type="submit" style="align-self:end">{{ __('Send a test') }}</button>
                @endif
            </form>
        @endif
        </details>
    @endforeach
@endsection
