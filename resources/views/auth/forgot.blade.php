<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="center-page">
    <div class="card">
        <div class="brand-row"><span class="logo">A</span> Forgot password</div>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        <form method="post" action="{{ route('password.email') }}">@csrf
            <div class="field"><label for="email">Your email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>@error('email') <div class="error">{{ $message }}</div> @enderror</div>
            <button class="btn primary" type="submit">Email me a link</button>
        </form>
        <p class="mute" style="margin-top:14px;font-size:12px"><a href="{{ route('login') }}">Back to sign in</a></p>
    </div>
</div>
</body>
</html>
