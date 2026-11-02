@if (session('support'))
<div class="support-bar" role="status">
    <span><b>{{ __('Support view') }}</b> · {{ session('support.business') }} · {{ session('support.mode') === 'edit' ? __('you are acting as the owner') : __('read-only') }}</span>
    <form method="post" action="{{ route('support.stop') }}">@csrf<button type="submit" class="btn small">{{ __('Return to the platform console') }}</button></form>
</div>
@endif
