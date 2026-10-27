<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customers: {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => 'customers'])
    <main class="page">
        <h1>Customers</h1>
        <p class="mute">People who gave their details to the bot. Open a chat from the inbox to talk to them.</p>
        <form method="get" class="bar">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, phone or interest">
            <button class="btn" type="submit">Search</button>
        </form>
        <table class="table">
            <thead><tr><th>Name</th><th>Phone</th><th>Interest</th><th>Language</th><th>Score</th><th>Promo messages</th><th>Added</th></tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td>{{ $c->name ?: '-' }}</td>
                    <td>{{ $c->phone ?: '-' }}</td>
                    <td>{{ $c->interest ?: '-' }}</td>
                    <td>{{ strtoupper($c->language ?: '-') }}</td>
                    <td><span class="tag {{ $c->score === 'hot' ? 'warn' : 'ok' }}">{{ $c->score ?: '-' }}</span></td>
                    <td>{{ $c->opted_out_at ? 'Opted out' : 'OK' }}</td>
                    <td>{{ $c->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No customers yet. Finish a booking request in the test chat to add one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </main>
</div>
</body>
</html>
