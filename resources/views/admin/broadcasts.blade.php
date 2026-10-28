@extends('admin.layout')
@section('title', 'Broadcasts')
@section('content')
    @include('admin._marketing_tabs', ['tab' => 'broadcasts'])
    <h1>Broadcasts</h1>
    <p class="mute">Send one message to many customers. Only customers who agreed to be contacted and never sent STOP can receive it. At most {{ $limit }} people per broadcast.</p>

    <h2>New broadcast</h2>
    <form method="post" action="{{ url('/admin/broadcasts') }}" class="card">
        @csrf
        <div class="grid2">
            <label class="f"><span>Name (for you)</span><input type="text" name="name" value="{{ old('name') }}" required></label>
            <label class="f"><span>WhatsApp template name for after 24 hours (optional)</span><input type="text" name="template_name" value="{{ old('template_name') }}" placeholder="approved_marketing_template"></label>
        </div>
        <div class="grid2">
            <label class="f"><span>Message (English). You can use {name} {business}</span><textarea name="message_en" rows="3" required>{{ old('message_en') }}</textarea></label>
            <label class="f"><span>Message (中文)</span><textarea name="message_zh" rows="3">{{ old('message_zh') }}</textarea></label>
        </div>
        <div class="bar">
            <span class="mute">Send to:</span>
            <select name="tag_id" class="small"><option value="">Any tag</option>@foreach ($tags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
            <select name="score" class="small"><option value="">Any lead level</option>@foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select>
            <select name="language" class="small"><option value="">Any language</option>@foreach (config('aichat.languages') as $l)<option value="{{ $l }}">{{ strtoupper($l) }}</option>@endforeach</select>
            <button class="btn primary" type="submit">Save draft</button>
        </div>
    </form>

    <h2>Your broadcasts</h2>
    <table class="table">
        <thead><tr><th>Name</th><th>Status</th><th>People</th><th>Sent / skipped / failed</th><th></th></tr></thead>
        <tbody>
        @forelse ($broadcasts as $b)
            <tr>
                <td><a href="{{ route('broadcasts.show', $b->id) }}">{{ $b->name }}</a><br><small class="mute">{{ \Illuminate\Support\Str::limit($b->message['en'] ?? '', 80) }}</small></td>
                <td><span class="tag {{ $b->status === 'sent' ? 'ok' : 'warn' }}">{{ $b->status }}</span></td>
                <td>{{ $b->status === 'draft' ? ($audienceCounts[$b->id] ?? 0).' can receive it' : '-' }}</td>
                <td>{{ $b->status === 'sent' ? $b->sent_count.' / '.$b->skipped_count.' / '.$b->failed_count : '-' }}</td>
                <td class="actions">
                    @if ($b->status === 'draft')
                        <form method="post" action="{{ url('/admin/broadcasts/'.$b->id.'/send') }}" onsubmit="return confirm('Send this to {{ $audienceCounts[$b->id] ?? 0 }} customer(s) now? This cannot be undone.')">@csrf<button class="btn small primary">Send now</button></form>
                        <form method="post" action="{{ url('/admin/broadcasts/'.$b->id.'/delete') }}" onsubmit="return confirm('Delete this draft?')">@csrf<button class="btn small danger">Delete</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No broadcasts yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
