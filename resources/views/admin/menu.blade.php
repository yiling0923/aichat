@extends('admin.layout')
@section('title', 'Menu builder')
@section('content')
<div id="menu" data-base="{{ url('/admin/api/menu') }}">
    <div class="page-head">
        <div>
            <h1>Menu builder</h1>
            <p class="mute">What the bot says and the buttons customers see. Change the draft, try it on the right, then publish.</p>
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

    <dialog id="add-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3>Add a step</h3>
        <p class="mute" style="margin-top:4px">A step is one thing the bot says. Link to it from a button or from another step.</p>
        <div class="f" style="margin-top:12px"><span>Name (only you see it)</span><input type="text" id="add-name" maxlength="40" placeholder="Opening offer"></div>
        <div class="f"><span>What it does</span><select id="add-type"></select></div>
        <p class="mute small-note" id="add-help" style="padding:0 0 10px"></p>
        <div class="dlg-foot"><button class="btn primary" id="add-ok" type="button">Add step</button></div>
    </dialog>
    <div class="toast" id="toast" hidden></div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/menu.js') }}"></script>
@endpush
