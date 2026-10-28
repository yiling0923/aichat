<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a new password</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="center-page">
    <div class="card">
        <div class="brand-row"><span class="logo">A</span> Choose a new password</div>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        <form method="post" action="{{ route('password.update') }}">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" required>@error('email') <div class="error">{{ $message }}</div> @enderror</div>
            <div class="field"><label for="password">New password (8 or more characters)</label><input id="password" name="password" type="password" required>@error('password') <div class="error">{{ $message }}</div> @enderror</div>
            <div class="field"><label for="password_confirmation">Repeat it</label><input id="password_confirmation" name="password_confirmation" type="password" required></div>
            <button class="btn primary" type="submit">Save password</button>
        </form>
        <p class="mute" style="margin-top:14px;font-size:12px"><a href="{{ route('login') }}">Back to sign in</a></p>
    </div>
</div>
</body>
</html>
