@extends('admin.layout')
@section('title', 'Customers')
@section('content')
    <h1>Customers</h1>
    <p class="mute">One record per person (matched by phone number). Open a customer to see their chats, bookings and notes. Lead level: Ready to book = booked or ready to buy, Interested = left their details, Just browsing = low interest.</p>
    @php
        $levels = config('aichat.lead_levels');
        $chips = array_filter([
            $search !== '' ? ['Search: '.$search, 'q'] : null,
            $score !== '' ? ['Lead level: '.($levels[$score] ?? $score), 'score'] : null,
            $language !== '' ? ['Language: '.strtoupper($language), 'language'] : null,
            $tag ? ['Tag: '.optional($tags->firstWhere('id', $tag))->name, 'tag'] : null,
        ]);
    @endphp
    <form method="get" class="filters">
        <label class="grow">Search<input type="search" name="q" value="{{ $search }}" placeholder="Name, phone or what they asked about"></label>
        <label>Lead level
            <select name="score" onchange="this.form.submit()">
                <option value="">All</option>
                @foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}" @selected($score === $s)>{{ $levels[$s] }}</option>@endforeach
            </select></label>
        <label>Language
            <select name="language" onchange="this.form.submit()">
                <option value="">All</option>
                @foreach (config('aichat.languages') as $l)<option value="{{ $l }}" @selected($language === $l)>{{ strtoupper($l) }}</option>@endforeach
            </select></label>
        <label>Tag
            <select name="tag" onchange="this.form.submit()">
                <option value="0">All</option>
                @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($tag === $t->id)>{{ $t->name }}</option>@endforeach
            </select></label>
        <button class="btn primary" type="submit">Search</button>
        @if ($chips)<a class="btn" href="{{ route('customers') }}">Reset</a>@endif
    </form>
    <div class="result-line">
        <span><b>{{ $customers->total() }}</b> {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }}</span>
        @foreach ($chips as [$text, $param])
            <span class="fchip">{{ $text }} <a href="{{ request()->fullUrlWithQuery([$param => null, 'page' => null]) }}" aria-label="Remove filter">&times;</a></span>
        @endforeach
    </div>

    <form method="post" action="{{ url('/admin/customers/bulk') }}">
        @csrf
        @if ($tags->isNotEmpty())
            <div class="bulkbar" id="bulkbar" hidden>
                <b><span id="bulk-count">0</span> selected</b>
                <span class="mute">Tag them:</span>
                <select name="tag_id" class="small">@foreach ($tags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                <button class="btn small primary" name="action" value="add">Add tag</button>
                <button class="btn small" name="action" value="remove">Remove tag</button>
            </div>
        @endif
        <table class="table">
            <thead><tr><th><input type="checkbox" id="pick-all"></th><th>Name</th><th>Phone</th><th>Interested in</th><th>Tags</th><th>Lang</th><th>Lead level</th><th>Promo</th><th>Added</th></tr></thead>
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
                    <td>{{ $c->opted_out_at ? 'Opted out' : 'OK' }}</td>
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
@push('scripts')
<script>
    (function () {
        var bar = document.getElementById('bulkbar'), all = document.getElementById('pick-all');
        function update() {
            var n = document.querySelectorAll('.pick:checked').length;
            if (bar) { bar.hidden = n === 0; document.getElementById('bulk-count').textContent = n; }
        }
        if (all) { all.addEventListener('change', function () { document.querySelectorAll('.pick').forEach(function (c) { c.checked = all.checked; }); update(); }); }
        document.querySelectorAll('.pick').forEach(function (c) { c.addEventListener('change', update); });
    })();
</script>
@endpush
