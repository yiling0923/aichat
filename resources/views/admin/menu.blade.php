@extends('admin.layout')
@section('title', __('Menu builder'))
@section('content')
<div id="menu" data-base="{{ url('/admin/api/menu') }}">
    <div class="page-head">
        <div>
            <h1>{{ __('Menu builder') }}</h1>
            <p class="mute">{{ __('What the bot says and the buttons customers see. Change the draft, try it on the right, then publish.') }}</p>
        </div>
        <div class="bar">
            <span class="tag" id="state-tag"></span>
            <button class="btn" id="btn-reset" type="button">{{ __('Reset to sample') }}</button>
            <button class="btn" id="btn-save" type="button">{{ __('Save draft') }}</button>
            <button class="btn primary" id="btn-publish" type="button">{{ __('Publish') }}</button>
        </div>
    </div>

    <div id="problems"></div>
    <div class="stats" id="stats"></div>

    <div class="builder">
        <section class="steps">
            <div class="list-head">{{ __('Steps') }} <button class="btn small" id="btn-add" type="button">{{ __('+ Add step') }}</button></div>
            <div id="step-list"></div>
        </section>
        <section class="editor" id="editor"></section>
        <section class="preview">
            <div class="list-head">{{ __('Try it (draft)') }} <button class="btn small" id="btn-restart" type="button">{{ __('Restart') }}</button></div>
            <div class="thread-log" id="pv-log"></div>
            <form class="composer" id="pv-form"><input id="pv-input" placeholder="{{ __('Type like a customer') }}" autocomplete="off"></form>
            <p class="mute small-note">{{ __('Leads and handovers made here are not saved.') }}</p>
        </section>
    </div>

    <dialog id="add-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3>{{ __('Add a step') }}</h3>
        <p class="mute" style="margin-top:4px">{{ __('A step is one thing the bot says. Link to it from a button or from another step.') }}</p>
        <div class="f" style="margin-top:12px"><span>{{ __('Name (only you see it)') }}</span><input type="text" id="add-name" maxlength="40" placeholder="{{ __('Opening offer') }}"></div>
        <div class="f"><span>{{ __('What it does') }}</span><select id="add-type"></select></div>
        <p class="mute small-note" id="add-help" style="padding:0 0 10px"></p>
        <div class="dlg-foot"><button class="btn primary" id="add-ok" type="button">{{ __('Add step') }}</button></div>
    </dialog>
    <div class="toast" id="toast" hidden></div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/menu.js') }}"></script>
@endpush
