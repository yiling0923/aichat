@extends('admin.layout')
@section('title', 'Automations')
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'automations'])
    <div class="bar spread">
        <div>
            <h1>Automations</h1>
            <p class="mute">Messages the app sends by itself. They only go to customers who agreed to be contacted and never sent STOP. Everything starts switched off.</p>
        </div>
        <div class="bar">
            <a class="btn" href="{{ route('automations.activity') }}">Activity</a>
            <form method="post" action="{{ url('/admin/automations/run') }}">@csrf<button class="btn primary" onclick="return confirm('Send every message that is due right now?')">Run now (test)</button></form>
        </div>
    </div>
    @error('automation')<div class="note err">{{ $message }}</div>@enderror
    <div class="note warn">Runs automatically every 5 minutes if the Windows Task Scheduler job is set up (README, "Staff reminders"). On WhatsApp, Messenger and Instagram a message can only be sent within 24 hours of the customer's last message, unless you give an approved WhatsApp template name.</div>

    @foreach ($automations as $a)
        @php $d = $defs[$a->key]; @endphp
        <form method="post" action="{{ url('/admin/automations/'.$a->key) }}" class="card">
            @csrf
            <div class="bar spread">
                <div><h3>{{ $d['title'] }}</h3><p class="mute small-note" style="padding:0">{{ $d['help'] }}</p></div>
                <label class="f"><span>Status</span>
                    <select name="enabled"><option value="0" @selected(! $a->enabled)>Off</option><option value="1" @selected($a->enabled)>On</option></select></label>
            </div>
            <div class="grid2">
                @if (in_array($a->key, ['survey', 'no_show', 'hot_lead']))
                    <label class="f"><span>Wait (hours) {{ $a->key === 'survey' ? 'after the visit ends' : ($a->key === 'no_show' ? 'after it is marked No-show' : 'after the customer was added') }}</span>
                        <input type="number" name="delay_hours" value="{{ $a->delay_hours }}"></label>
                @endif
                @if ($a->key === 'rebooking')
                    <label class="f"><span>Days since the last visit</span><input type="number" name="rebook_days" value="{{ $a->settings['rebook_days'] ?? 30 }}"></label>
                @endif
                @if ($a->key === 'review_request')
                    <label class="f"><span>Your review link (https://...)</span><input type="text" name="review_url" value="{{ $a->settings['review_url'] ?? '' }}"></label>
                    <label class="f"><span>Send it from this rating up (1 to 5)</span><input type="number" name="min_rating" min="1" max="5" value="{{ $a->settings['min_rating'] ?? 4 }}"></label>
                @endif
                <label class="f"><span>WhatsApp template name for after 24 hours (optional)</span><input type="text" name="template_name" value="{{ $a->template_name }}" placeholder="approved_template_name"></label>
            </div>
            <div class="grid2">
                <label class="f"><span>Message (English). You can use {name} {service} {business}{{ $a->key === 'review_request' ? ' {review_url}' : '' }}</span>
                    <textarea name="message_en" rows="3" required>{{ $a->message['en'] ?? '' }}</textarea></label>
                <label class="f"><span>Message (中文)</span><textarea name="message_zh" rows="3">{{ $a->message['zh'] ?? '' }}</textarea></label>
            </div>
            <button class="btn primary" type="submit">Save</button>
        </form>
    @endforeach
@endsection
