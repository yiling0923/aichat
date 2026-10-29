@extends('admin.layout')
@section('title', __('Automations'))
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'automations'])
    <div class="bar spread">
        <div>
            <h1>{{ __('Automations') }}</h1>
            <p class="mute">{{ __('Messages the app sends by itself. They only go to customers who agreed to be contacted and never sent STOP. Everything starts switched off.') }}</p>
        </div>
        <div class="bar">
            <a class="btn" href="{{ route('automations.activity') }}">{{ __('Activity') }}</a>
            <form method="post" action="{{ url('/admin/automations/run') }}">@csrf<button class="btn primary" onclick="return confirm({{ json_encode(__('Send every message that is due right now?'), JSON_UNESCAPED_UNICODE) }})">{{ __('Run now (test)') }}</button></form>
        </div>
    </div>
    @error('automation')<div class="note err">{{ $message }}</div>@enderror
    <p class="help-line">{{ __('Checked every 5 minutes. On WhatsApp, Messenger and Instagram a message can only go out within 24 hours of the customer\'s last message, unless you give an approved WhatsApp template name.') }}</p>

    @foreach ($automations as $a)
        @php $d = $defs[$a->key]; @endphp
        <details class="card fold" @if ($a->enabled) open @endif>
        <summary><span><b>{{ __($d['title']) }}</b> <span class="tag {{ $a->enabled ? 'ok' : '' }}">{{ $a->enabled ? __('On') : __('Off') }}</span><small class="mute fold-help">{{ __($d['help']) }}</small></span></summary>
        <form method="post" action="{{ url('/admin/automations/'.$a->key) }}">
            @csrf
            <div class="bar spread" style="margin-top:12px">
                <span></span>
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
