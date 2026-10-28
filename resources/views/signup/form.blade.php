<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create your account</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="center-page">
    <div class="card">
        <div class="brand-row"><span class="logo">A</span> AI Messaging Sales Assistant</div>
        <h1>Start your free trial</h1>
        <p class="mute" style="margin-bottom:14px">{{ $inviteOnly ? 'Private beta: you need an invitation code.' : config('plans.trial_days').' days free. No card needed.' }}</p>
        @foreach ($errors->all() as $error)<div class="note err">{{ $error }}</div>@endforeach
        <form method="post" action="{{ route('signup') }}">
            @csrf
            @if ($inviteOnly)
                <div class="field"><label for="invite">Invitation code</label><input id="invite" name="invite" value="{{ old('invite', $invite) }}" required></div>
            @endif
            <div class="field"><label for="business_name">Business name</label><input id="business_name" name="business_name" value="{{ old('business_name') }}" required autofocus></div>
            <div class="field"><label for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" required></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required></div>
            <div class="field"><label for="password">Password (8 or more characters)</label><input id="password" name="password" type="password" required></div>
            <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
            <div class="field"><label style="font-weight:400"><input type="checkbox" name="terms" value="1" required> I agree to the <a href="{{ route('terms') }}" target="_blank">terms</a> and the <a href="{{ route('privacy') }}" target="_blank">privacy notice</a>.</label></div>
            <button class="btn primary" type="submit">Create my account</button>
        </form>
        <p class="mute" style="margin-top:14px;font-size:12px">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</div>
</body>
</html>
