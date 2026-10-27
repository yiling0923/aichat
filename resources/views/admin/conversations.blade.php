@extends('admin.layout')
@section('title', 'All conversations')
@section('content')
    <h1>All conversations</h1>
    <p class="mute">Every chat, including resolved ones. Search looks inside the messages too.</p>
    <form method="get" class="bar">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search messages, name or phone">
        <select name="status" class="small">
            @foreach (['all' => 'Any status', 'open' => 'Open', 'resolved' => 'Resolved'] as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach
        </select>
        <select name="mode" class="small">
            @foreach (['all' => 'Bot or staff', 'bot' => 'Bot answering', 'human' => 'Staff handling'] as $v => $l)<option value="{{ $v }}" @selected($mode === $v)>{{ $l }}</option>@endforeach
        </select>
        <select name="tag" class="small">
            <option value="0">Any tag</option>
            @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($tag === $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <button class="btn" type="submit">Search</button>
    </form>
    <table class="table">
        <thead><tr><th>Customer</th><th>Channel</th><th>Last message</th><th>Handled by</th><th>Tags</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($conversations as $c)
            <tr>
                <td><a href="{{ route('inbox', ['c' => $c->id]) }}">{{ $c->customer?->name ?: ($c->contact_name ?: 'Visitor '.$c->id) }}</a>
                    <br><small class="mute">{{ $c->customer?->phone }}</small></td>
                <td>{{ $c->channel }}</td>
                <td>
                    @if (isset($snippets[$c->id]))
                        <small>{!! preg_replace('/('.preg_quote(e($q), '/').')/iu', '<mark>$1</mark>', e(\Illuminate\Support\Str::limit($snippets[$c->id], 120))) !!}</small>
                    @endif
                    <br><small class="mute">{{ $c->last_message_at?->diffForHumans() }}</small>
                </td>
                <td>{{ $c->mode === 'human' ? ($c->assignee?->name ?: 'Staff') : 'Bot' }}</td>
                <td>@foreach ($c->tags as $t)<span class="chip" style="background:{{ $t->color }}">{{ $t->name }}</span>@endforeach</td>
                <td><span class="tag {{ $c->status === 'open' ? 'ok' : '' }}">{{ $c->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No conversations match.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $conversations->links('pagination::simple-default') }}</div>
@endsection
