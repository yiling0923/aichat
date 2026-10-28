@extends('admin.layout')
@section('title', 'Templates')
@section('content')
    @php($languages = ['en' => 'English', 'zh_CN' => '中文（简体）', 'zh_HK' => '中文（香港）', 'ms' => 'Malay'])
    <div class="bar spread">
        <div>
            <h1>WhatsApp templates</h1>
            <p class="mute">WhatsApp only lets you start a conversation, or write after 24 hours of silence, with a template Meta has approved.</p>
        </div>
        <form method="post" action="{{ url('/admin/templates/sync') }}">@csrf<button class="btn">Refresh status</button></form>
    </div>
    @error('template')<div class="note err">{{ $message }}</div>@enderror

    @if ($channels->isEmpty())
        <div class="card mute">Connect a WhatsApp number first (Channels page).</div>
    @else
        <table class="table">
            <thead><tr><th>Name</th><th>Language</th><th>Message</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($templates as $t)
                <tr>
                    <td><b>{{ $t->name }}</b><br><small class="mute">{{ $t->channel->name }} · {{ strtolower($t->category) }}</small></td>
                    <td>{{ $t->language }}</td>
                    <td style="max-width:420px"><small>{{ $t->body }}</small>
                        @if ($t->last_error)<br><small style="color:var(--red)">{{ $t->last_error }}</small>@endif</td>
                    <td><span class="tag {{ $t->isApproved() ? 'ok' : (in_array(strtoupper($t->status), ['REJECTED', 'DISABLED', 'PAUSED']) ? 'hot' : 'warn') }}">{{ strtolower($t->status) }}</span></td>
                    <td><form method="post" action="{{ url('/admin/templates/'.$t->id.'/delete') }}" onsubmit="return confirm('Remove from this list?')">@csrf<button class="btn small danger">Remove</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No templates yet. Add one below, or press Refresh status to load the ones already in Meta.</td></tr>
            @endforelse
            </tbody>
        </table>

        <h2>New template</h2>
        <form method="post" action="{{ url('/admin/templates') }}" class="card">
            @csrf
            <div class="grid2">
                <label class="f"><span>Number</span><select name="channel_id">@foreach ($channels as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
                <label class="f"><span>Name (lower case, numbers, underscores)</span><input type="text" name="name" value="{{ old('name') }}" placeholder="booking_reminder" required></label>
                <label class="f"><span>Language</span><select name="language">@foreach ($languages as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
                <label class="f"><span>Type</span><select name="category"><option value="UTILITY">Utility (reminders, confirmations)</option><option value="MARKETING">Marketing (offers)</option></select></label>
            </div>
            <label class="f"><span>Message. Use @{{1}}, @{{2}} for parts that change, for example: Hi @{{1}}, a reminder of your @{{2}} appointment on @{{3}}.</span>
                <textarea name="body" rows="3" required>{{ old('body') }}</textarea></label>
            <p></p><button class="btn primary" type="submit">Send to Meta for review</button>
        </form>
    @endif
@endsection
