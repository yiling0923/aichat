@extends('admin.layout')
@section('title', 'Settings')
@section('content')
    @php
        $icons = [
            'settings' => '<path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-6h6v6"/>',
            'billing' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
            'setup' => '<path d="M5 3v18"/><path d="M5 4h12l-2 4 2 4H5"/>',
            'backup' => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
            'tags' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
            'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
            'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
        ];
    @endphp
    <div class="page-head"><div><h1>Settings</h1><p class="mute">Everything you set up once and rarely change.</p></div></div>
    @foreach ($groups as $title => $items)
        <h2 class="set-group">{{ $title }}</h2>
        <div class="tiles set-tiles">
            @foreach ($items as $item)
                @php [$name, $route, $text] = $item; $hash = $item[3] ?? ''; @endphp
                <a class="tile set-tile" href="{{ route($route) }}{{ $hash }}">
                    <span class="set-ico"><svg viewBox="0 0 24 24">{!! $icons[$name === 'Phone alerts' ? 'bell' : $route] ?? '' !!}</svg></span>
                    <span class="set-txt"><b>{{ $name }}</b><span>{{ $text }}</span></span>
                    <svg class="set-go" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
                </a>
            @endforeach
        </div>
    @endforeach
@endsection
