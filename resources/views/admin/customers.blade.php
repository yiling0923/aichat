@extends('admin.layout')
@section('title', 'Customers')
@section('content')
    <h1>Customers</h1>
    <p class="mute">One record per person (matched by phone number). Open a customer to see their chats, bookings and notes. Lead level: Ready to book = booked or ready to buy, Interested = left their details, Just browsing = low interest.</p>
    <form method="get" class="bar">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search name, phone or interest">
        <select name="score" class="small">
            <option value="">Any lead level</option>
            @foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}" @selected($score === $s)>{{ config('aichat.lead_levels')[$s] }}</option>@endforeach
        </select>
        <select name="language" class="small">
            <option value="">Any language</option>
            @foreach (config('aichat.languages') as $l)<option value="{{ $l }}" @selected($language === $l)>{{ strtoupper($l) }}</option>@endforeach
        </select>
        <select name="tag" class="small">
            <option value="0">Any tag</option>
            @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($tag === $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>

    <form method="post" action="{{ url('/admin/customers/bulk') }}">
        @csrf
        @if ($tags->isNotEmpty())
            <div class="bar">
                <span class="mute">With the ticked customers:</span>
                <select name="tag_id" class="small">@foreach ($tags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                <button class="btn small" name="action" value="add">Add tag</button>
                <button class="btn small" name="action" value="remove">Remove tag</button>
            </div>
        @endif
        <table class="table">
            <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"></th><th>Name</th><th>Phone</th><th>Interested in</th><th>Tags</th><th>Lang</th><th>Lead level</th><th title="Can we send promotional messages?">Promotions</th><th>Added</th></tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td><input class="pick" type="checkbox" name="ids[]" value="{{ $c->id }}"></td>
                    <td><a href="{{ route('customers.show', $c->id) }}">{{ $c->name ?: '(no name)' }}</a></td>
                    <td>{{ $c->phone ?: '-' }}</td>
                    <td>{{ $c->interest ?: '-' }}</td>
                    <td>@foreach ($c->tags as $t)<span class="chip" style="background:{{ $t->color }}">{{ $t->name }}</span>@endforeach</td>
                    <td>{{ strtoupper($c->language ?: '-') }}</td>
                    <td><span title="{{ ['hot' => 'Booked or ready to buy', 'warm' => 'Interested, left their details', 'cold' => 'Low interest'][$c->score] ?? 'Not set' }}" class="tag {{ $c->score === 'hot' ? 'hot' : ($c->score === 'warm' ? 'warn' : '') }}">{{ $c->score ? config('aichat.lead_levels')[$c->score] : '-' }}</span></td>
                    <td>@if ($c->opted_out_at)<span class="tag warn">Opted out</span>@elseif ($c->consented_at)<span class="tag ok">Allowed</span>@else<span class="mute">Not asked</span>@endif</td>
                    <td>{{ $c->created_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No customers match. Finish a booking in the test chat to add one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </form>
    <div class="pager">{{ $customers->links('pagination::simple-default') }}</div>
@endsection
