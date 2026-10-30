<link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
<meta name="theme-color" content="#0f766e">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit($business->name ?? 'Inbox', 12, '') }}">
