{{ __('Hi,') }}

{{ __(':name has reached its plan limit for: :what.', ['name' => $business->name, 'what' => __(config('plans.labels.'.$kind))]) }}

@if ($kind === 'ai')
{{ __("The AI has paused. Your customers' messages still arrive in your inbox and the menu bot keeps working.") }}
@endif
{{ __('Open "Plan & billing" in the staff area to upgrade.') }}
