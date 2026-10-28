@extends('admin.layout')
@section('title', $customer->name ?: 'Customer')
@section('content')
    <p><a href="{{ route('customers') }}">&larr; All customers</a></p>
    <h1>{{ $customer->name ?: '(no name)' }}</h1>
    <p class="mute">{{ $customer->phone }} · customer since {{ $customer->created_at->format('d M Y') }}
        @if ($customer->opted_out_at) · <span class="tag warn">opted out of promotions</span>@endif</p>

    @if (auth()->user()->canManage())
        <p>
            <a class="btn small" href="{{ url('/admin/customers/'.$customer->id.'/export') }}">Download their data</a>
            <form method="post" action="{{ url('/admin/customers/'.$customer->id.'/erase') }}" style="display:inline" onsubmit="return confirm('Delete this customer and all their chats for good? This cannot be undone.')">@csrf<button class="btn small danger" type="submit">Delete their data</button></form>
        </p>
    @endif
    <div class="cols">
        <form method="post" action="{{ url('/admin/customers/'.$customer->id) }}" class="card">
            @csrf
            <h3>Details</h3>
            <div class="grid2">
                <label class="f"><span>Name</span><input type="text" name="name" value="{{ old('name', $customer->name) }}"></label>
                <label class="f"><span>Phone</span><input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"></label>
                <label class="f"><span>Email</span><input type="email" name="email" value="{{ old('email', $customer->email) }}"></label>
                <label class="f"><span>Language</span>
                    <select name="language"><option value="">-</option>@foreach (config('aichat.languages') as $l)<option value="{{ $l }}" @selected($customer->language === $l)>{{ strtoupper($l) }}</option>@endforeach</select></label>
                <label class="f"><span>Lead level (Hot = ready to buy)</span>
                    <select name="score"><option value="">-</option>@foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}" @selected($customer->score === $s)>{{ ucfirst($s) }}</option>@endforeach</select></label>
            </div>
            <div class="f"><span>Tags</span>
                @forelse ($allTags as $t)
                    <label class="check"><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked($customer->tags->contains('id', $t->id))> <span class="chip" style="background:{{ $t->color }}">{{ $t->name }}</span></label>
                @empty
                    <small class="mute">No customer tags yet. @if (auth()->user()->canManage())<a href="{{ route('tags') }}">Create some</a>.@endif</small>
                @endforelse
            </div>
            <label class="f"><span>Notes (private)</span><textarea name="notes" rows="4">{{ old('notes', $customer->notes) }}</textarea></label>
            <p></p><button class="btn primary" type="submit">Save</button>
        </form>

        <div>
            <div class="card">
                <h3>Interest</h3>
                <p>{{ $customer->interest ?: '-' }}</p>
                @foreach ((array) $customer->details as $k => $v)<p class="mute">{{ str_replace('_', ' ', $k) }}: {{ $v }}</p>@endforeach
            </div>
            <div class="card">
                <h3>Conversations</h3>
                <ul>
                    @forelse ($customer->conversations as $c)
                        <li><a href="{{ route('inbox', ['c' => $c->id]) }}">{{ $c->channel }} chat #{{ $c->id }}</a><span class="mute">{{ $c->last_message_at?->diffForHumans() }} · {{ $c->status }}</span></li>
                    @empty<li class="mute">No chats linked yet.</li>@endforelse
                </ul>
            </div>
            <div class="card">
                <h3>Bookings</h3>
                <ul>
                    @forelse ($customer->bookings->sortByDesc('starts_at') as $b)
                        <li><span>{{ $b->starts_at->copy()->setTimezone($business->timezone)->format('d M Y H:i') }} · {{ $b->service->label('en') }}</span><span class="mute">{{ $b->status }}</span></li>
                    @empty<li class="mute">No bookings.</li>@endforelse
                </ul>
            </div>
            <form method="post" action="{{ url('/admin/tasks') }}" class="card">
                @csrf<input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <h3>Follow-up task</h3>
                @foreach ($tasks as $t)<p class="mute">Open: {{ $t->title }}</p>@endforeach
                <label class="f"><span>Task</span><input type="text" name="title" required></label>
                <label class="f"><span>Due</span><input type="datetime-local" name="due_at"></label>
                <p></p><button class="btn" type="submit">Add task</button>
            </form>
        </div>
    </div>
@endsection
