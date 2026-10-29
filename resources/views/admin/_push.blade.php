@if (\App\Services\PushSender::configured())<meta name="push-key" content="{{ config('aichat.push.public') }}">@endif
<meta name="push-url" content="{{ url('/admin/api/push') }}">
<meta name="push-sw" content="{{ asset('sw.js') }}">
