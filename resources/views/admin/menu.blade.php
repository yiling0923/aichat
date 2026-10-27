<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Menu builder: {{ $business->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/aichat.css') }}">
</head>
<body>
<div class="shell">
    @include('admin._side', ['active' => 'menu'])
    <main class="page" id="menu" data-base="{{ url('/admin/api/menu') }}">
        <div class="bar spread">
            <div>
                <h1>Menu builder</h1>
                <p class="mute">The questions the bot asks and the answers it gives. Edit the draft, try it on the right, then publish.</p>
            </div>
            <div class="bar">
                <span class="tag" id="state-tag"></span>
                <button class="btn" id="btn-reset" type="button">Reset to sample</button>
                <button class="btn" id="btn-save" type="button">Save draft</button>
                <button class="btn primary" id="btn-publish" type="button">Publish</button>
            </div>
        </div>

        <div id="problems"></div>
        <div class="stats" id="stats"></div>

        <div class="builder">
            <section class="steps">
                <div class="list-head">Steps <button class="btn small" id="btn-add" type="button">+ Add step</button></div>
                <div id="step-list"></div>
            </section>
            <section class="editor" id="editor"></section>
            <section class="preview">
                <div class="list-head">Try it (draft) <button class="btn small" id="btn-restart" type="button">Restart</button></div>
                <div class="thread-log" id="pv-log"></div>
                <form class="composer" id="pv-form"><input id="pv-input" placeholder="Type like a customer" autocomplete="off"></form>
                <p class="mute small-note">Leads and handovers made here are not saved.</p>
            </section>
        </div>
    </main>
</div>
<script src="{{ asset('js/menu.js') }}"></script>
</body>
</html>
