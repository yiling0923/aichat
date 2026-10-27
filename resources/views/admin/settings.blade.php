@extends('admin.layout')
@section('title', 'Settings')
@section('content')
    <h1>Settings</h1>
    <p class="mute">Your business profile, look and team. Prices and services are in Booking setup. Bot answers are in the Menu builder.</p>

    <h2>Business</h2>
    <form method="post" action="{{ url('/admin/settings/profile') }}" class="card row">
        @csrf
        <label class="f"><span>Business name</span><input type="text" name="name" value="{{ old('name', $business->name) }}" required></label>
        <label class="f"><span>Time zone</span>
            <select name="timezone">@foreach ($timezones as $z)<option value="{{ $z }}" @selected($business->timezone === $z)>{{ $z }}</option>@endforeach</select></label>
        <label class="f" style="flex:0 0 90px"><span>Accent colour</span><input type="color" name="accent" value="{{ $business->settings['accent'] ?? '#0f766e' }}"></label>
        <button class="btn primary" type="submit">Save</button>
    </form>

    <h2>Team</h2>
    <p class="mute">Owner: everything. Manager: library and tags too. Staff: inbox, schedule, customers and tasks.</p>
    @foreach ($team as $u)
        <form method="post" action="{{ url('/admin/settings/team/'.$u->id) }}" class="card row">
            @csrf
            <div class="f"><span>{{ $u->name }}</span>{{ $u->email }}</div>
            <label class="f"><span>Role</span>
                <select name="role">@foreach (['owner', 'manager', 'staff'] as $r)<option value="{{ $r }}" @selected($u->role === $r)>{{ $r }}</option>@endforeach</select></label>
            <label class="f"><span>Account</span>
                <select name="active"><option value="1" @selected($u->active)>Active</option><option value="0" @selected(! $u->active)>Switched off</option></select></label>
            <label class="f"><span>New password</span><input type="password" name="password" placeholder="leave empty to keep"></label>
            <button class="btn" type="submit">Save</button>
        </form>
    @endforeach
    <form method="post" action="{{ url('/admin/settings/team') }}" class="card row">
        @csrf
        <label class="f"><span>New member: name</span><input type="text" name="name" required></label>
        <label class="f"><span>Email</span><input type="email" name="email" required></label>
        <label class="f"><span>Password (8+)</span><input type="password" name="password" required></label>
        <label class="f"><span>Role</span><select name="role"><option>staff</option><option>manager</option><option>owner</option></select></label>
        <button class="btn primary" type="submit">Add</button>
    </form>
@endsection
