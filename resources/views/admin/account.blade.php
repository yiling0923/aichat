@extends('admin.layout')
@section('title', 'My account')
@section('content')
    <div class="page-head"><div><h1>My account</h1><p class="mute">Your own sign-in details. Signed in as <b>{{ auth()->user()->email }}</b> ({{ auth()->user()->role }}).</p></div></div>
    <form method="post" action="{{ url('/admin/account') }}" class="card" style="max-width:560px">
        @csrf
        <label class="f"><span>Your name</span><input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="100"></label>
        <h3 style="margin-top:18px">Change password</h3>
        <p class="mute small-note" style="padding:0">Leave these empty to keep your password.</p>
        <label class="f" style="margin-top:8px"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password"></label>
        <label class="f"><span>New password (8 or more characters)</span><input type="password" name="password" autocomplete="new-password" minlength="8"></label>
        <label class="f"><span>Repeat the new password</span><input type="password" name="password_confirmation" autocomplete="new-password"></label>
        <div class="dlg-foot"><button class="btn primary" type="submit">Save</button></div>
    </form>
@endsection
