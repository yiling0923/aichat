@extends('admin.layout')
@section('title', 'Settings')
@section('content')
    <div class="page-head"><div><h1>Settings</h1><p class="mute">Everything you set up once and rarely change.</p></div></div>
    @foreach ($groups as $title => $items)
        <h2>{{ $title }}</h2>
        <div class="tiles">
            @foreach ($items as [$name, $route, $text])
                <a class="tile" href="{{ route($route) }}"><b>{{ $name }}</b><span>{{ $text }}</span></a>
            @endforeach
        </div>
    @endforeach
@endsection
