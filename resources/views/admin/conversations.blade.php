@extends('admin.layout')
@section('title', 'All conversations')
@section('content')
    <h1>All conversations</h1>
    <p class="mute">Every chat, including resolved ones. Search looks inside the messages too.</p>
    @php
        $chips = array_filter([
            $q !== '' ? ['Search: '.$q, 'q'] : null,
            $status !== 'all' ? ['Status: '.ucfirst($status), 'status'] : null,
            $mode !== 'all' ? ['Handled by: '.($mode === 'bot' ? 'Bot' : 'Staff'), 'mode'] : null,
            $tag ? ['Tag: '.optional($tags->firstWhere('id', $tag))->name, 'tag'] : null,
        ]);
    @endphp
    <form method="get" class="filters">
        <label class="grow">Search<input type="search" name="q" value="{{ $q }}" placeholder="Words in a message, a name or a phone number"></label>
        <label>Status
            <select name="status" onchange="this.form.submit()">
                @foreach (['all' => 'All', 'open' => 'Open', 'resolved' => 'Resolved'] as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach
            </select></label>
        <label>Handled by
            <select name="mode" onchange="this.form.submit()">
                @foreach (['all' => 'Bot or staff', 'bot' => 'Bot', 'human' => 'Staff'] as $v => $l)<option value="{{ $v }}" @selected($mode === $v)>{{ $l }}</option>@endforeach
            </select></label>
        <label>Tag
            <select name="tag" onchange="this.form.submit()">
                <option value="0">All</option>
                @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($tag === $t->id)>{{ $t->name }}</option>@endforeach
            </select></label>
        <button class="btn primary" type="submit">Search</button>
        @if ($chips)<a class="btn" href="{{ route('conversations') }}">Reset</a>@endif
    </form>
    <div class="result-line">
        <span><b>{{ $conversations->total() }}</b> {{ \Illuminate\Support\Str::plural('conversation', $conversations->total()) }}</span>
        @foreach ($chips as [$text, $param])
            <span class="fchip">{{ $text }} <a href="{{ request()->fullUrlWithQuery([$param => null, 'page' => null]) }}" aria-label="Remove filter">&times;</a></span>
        @endforeach
    </div>
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
