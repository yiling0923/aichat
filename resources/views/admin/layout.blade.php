<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title'): {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => $active])
    <main class="page">
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        @foreach ($errors->all() as $error)<div class="note err">{{ $error }}</div>@endforeach
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
