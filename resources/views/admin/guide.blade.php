@extends('admin.layout')
@section('title', __('Setup guide'))
@section('content')
    @php
        $next = collect($steps)->first(fn ($s) => ! $s['done'] && ! $s['optional']);
        $required = collect($steps)->reject(fn ($s) => $s['optional']);
        $optional = collect($steps)->filter(fn ($s) => $s['optional']);
        $pct = $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 100;
    @endphp
    <div class="page-head">
        <div><h1>{{ __('Setup guide') }}</h1><p class="mute">{{ __('Get ready for real customers in a few short steps. Steps tick themselves when the app can see they are done. You can come back any time.') }}</p></div>
        <a class="btn" href="{{ route('help') }}">{{ __('Read the Help page') }}</a>
    </div>

    <section class="card gd-head {{ $progress['complete'] ? 'done' : '' }}">
        <div class="gd-ring" style="--p: {{ $pct }}"><b>{{ $progress['done'] }}/{{ $progress['total'] }}</b></div>
        <div class="gd-txt">
            @if ($progress['complete'])
                <h3>{{ __('You are ready for customers') }}</h3>
                <p class="mute">{{ __('All the essential steps are done. The extra steps below are optional but worth a look.') }}</p>
            @else
                <h3>{{ $progress['done'] === 0 ? __('Let us get started') : __('Nice, keep going') }}</h3>
                <p class="mute">{{ $progress['total'] - $progress['done'] === 1 ? __('1 step left, about :m minutes.', ['m' => $progress['minutes']]) : __(':n steps left, about :m minutes.', ['n' => $progress['total'] - $progress['done'], 'm' => $progress['minutes']]) }}@if ($next) {!! __('Next: :title.', ['title' => '<b>'.e(__($next['title'])).'</b>']) !!}@endif</p>
            @endif
        </div>
        @if ($next)<a class="btn primary" href="{{ $next['url'] }}" @if ($next['key'] === 'chat') target="_blank" rel="noopener" @endif>{{ __($next['cta']) }}</a>@endif
    </section>

    <h2 class="set-group">{{ __('Essential') }}</h2>
    <div class="gd-steps">
        @foreach ($required as $s)
            @include('admin._guide_step', ['s' => $s, 'n' => $loop->iteration, 'isNext' => $next && $next['key'] === $s['key']])
        @endforeach
    </div>

    <h2 class="set-group">{{ __('Nice to have') }}</h2>
    <div class="gd-steps">
        @foreach ($optional as $s)
            @include('admin._guide_step', ['s' => $s, 'n' => $required->count() + $loop->iteration, 'isNext' => false])
        @endforeach
    </div>
@endsection
