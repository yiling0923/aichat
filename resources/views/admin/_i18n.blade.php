@php($__js = \App\Support\Lang::scripts(app()->getLocale()))
<script>window.I18N = {!! json_encode($__js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}; window.LOCALE = {!! json_encode(app()->getLocale()) !!};</script>
<script src="{{ asset('js/i18n.js') }}"></script>
