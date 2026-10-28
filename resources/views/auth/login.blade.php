<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="center-page">
    <div class="card">
        <div class="brand-row"><span class="logo">A</span> Staff sign in</div>
        @if (session('status'))<div class="note ok">{{ session('status') }}</div>@endif
        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn primary" type="submit">Sign in</button>
            <a href="{{ route('password.request') }}" style="margin-left:10px;font-size:12px">Forgot password?</a>
        </form>
        <p class="mute" style="margin-top:14px;font-size:12px">New here? <a href="{{ route('signup') }}">Start a free trial</a></p>
        <p class="mute" style="margin-top:6px;font-size:12px">Sample login after seeding: owner@example.com / password</p>
    </div>
</div>
</body>
</html>
