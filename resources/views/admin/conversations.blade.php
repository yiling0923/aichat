@extends('admin.layout')
@section('title', __('All conversations'))
@section('content')
    <h1>{{ __('All conversations') }}</h1>
    <p class="mute">{{ __('Every chat, including resolved ones. Search looks inside the messages too.') }}</p>
    <form method="get" class="fbar">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search messages, name or phone') }}">
        <select name="status" class="small">
            @foreach (['all' => 'Any status', 'open' => 'Open', 'resolved' => 'Resolved'] as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ __($l) }}</option>@endforeach
        </select>
        <select name="mode" class="small">
            @foreach (['all' => 'Bot or staff', 'bot' => 'Bot answering', 'human' => 'Staff handling'] as $v => $l)<option value="{{ $v }}" @selected($mode === $v)>{{ __($l) }}</option>@endforeach
        </select>
        <select name="tag" class="small">
            <option value="0">{{ __('Any tag') }}</option>
            @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($tag === $t->id)>{{ __($t->name) }}</option>@endforeach
        </select>
        <button class="btn" type="submit">{{ __('Search') }}</button>
        @if ($q !== '' || $status !== 'all' || $mode !== 'all' || $tag)<a class="btn" href="{{ route('conversations') }}">{{ __('Reset') }}</a>@endif
    </form>
    <table class="table">
        <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Last message') }}</th><th>{{ __('Handled by') }}</th><th>{{ __('Tags') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse ($conversations as $c)
            <tr>
                <td><a href="{{ route('inbox', ['c' => $c->id]) }}">{{ $c->customer?->name ?: ($c->contact_name ?: __('Visitor :n', ['n' => $c->id])) }}</a>
                    <br><small class="mute">{{ $c->customer?->phone }}</small></td>
                <td>{{ ['web' => __('Website')][$c->channel] ?? ucfirst($c->channel) }}</td>
                <td>
                    @if (isset($snippets[$c->id]))
                        <small>{!! preg_replace('/('.preg_quote(e($q), '/').')/iu', '<mark>$1</mark>', e(\Illuminate\Support\Str::limit($snippets[$c->id], 120))) !!}</small>
                    @endif
                    <br><small class="mute">{{ $c->last_message_at?->diffForHumans() }}</small>
                </td>
                <td>{{ $c->mode === 'human' ? ($c->assignee?->name ?: __('Staff')) : __('Bot') }}</td>
                <td>@foreach ($c->tags as $t)<span class="chip" style="background:{{ $t->color }}">{{ __($t->name) }}</span>@endforeach</td>
                <td><span class="tag {{ $c->status === 'open' ? 'ok' : '' }}">{{ __($c->status) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">{{ __('No conversations match.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $conversations->links('pagination.bar') }}</div>
@endsection
