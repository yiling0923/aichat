@extends('admin.layout')
@section('title', 'Today')
@section('content')
    <h1>{{ $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ auth()->user()->name }}</h1>
    <p class="mute">{{ now($business->timezone)->format('l, d F Y') }}</p>

    @if ($checklist)
        @php $done = collect($checklist)->where('done', true)->count(); @endphp
        <div class="card setup-card">
            <div class="bar spread"><h3>Get set up</h3><span class="mute">{{ $done }} of {{ count($checklist) }} done</span></div>
            <div class="progress"><span style="width: {{ round($done / count($checklist) * 100) }}%"></span></div>
            <ul>
                @foreach ($checklist as $step)
                    <li><span>@if ($step['done'])<span class="tick ok">&#10003;</span> <s class="mute">{{ $step['text'] }}</s>@else<span class="tick">&#9675;</span> {{ $step['text'] }}@endif</span>
                        @unless ($step['done'])<a class="btn small" href="{{ $step['url'] }}">Do it</a>@endunless</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid3">
        <div class="stat"><b>{{ $waiting->count() }}</b><span>chats waiting for staff</span></div>
        <div class="stat"><b>{{ $bookings->count() }}</b><span>bookings today</span></div>
        <div class="stat"><b>{{ $openChats }}</b><span>open chats</span></div>
        <div class="stat"><b>{{ $newLeads }}</b><span>new customers (7 days)</span></div>
    </div>

    <div class="cols">
        <div class="card">
            <h3>Needs a person</h3>
            <ul>
                @forelse ($waiting as $c)
                    <li><a href="{{ route('inbox', ['c' => $c->id]) }}">{{ $c->contact_name ?: 'Visitor '.$c->id }}</a>
                        <span class="mute">{{ $c->handover_reason }} · {{ $c->handover_at?->diffForHumans() }}</span></li>
                @empty
                    <li class="mute">Nothing waiting. The bot is handling everything.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <h3>Today's bookings</h3>
            <ul>
                @forelse ($bookings as $b)
                    <li><span>{{ $b->starts_at->copy()->setTimezone($business->timezone)->format('H:i') }} · {{ $b->service->label('en') }}
                        <span class="mute">with {{ $b->practitioner->name }}</span></span>
                        <span>{{ $b->customer?->name }}</span></li>
                @empty
                    <li class="mute">No bookings today. <a href="{{ route('schedule') }}">Open the schedule</a>.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <h3>Tasks for you</h3>
            <ul>
                @forelse ($tasks as $t)
                    <li><span>{{ $t->title }}</span>
                        <span class="mute">{{ $t->due_at ? $t->due_at->copy()->setTimezone($business->timezone)->format('d M H:i') : 'no date' }}</span></li>
                @empty
                    <li class="mute">No tasks due. <a href="{{ route('tasks') }}">Add one</a>.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
