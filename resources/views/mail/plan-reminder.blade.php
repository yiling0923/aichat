{{ __('Hi,') }}

@if ($state === 'trial_expired')
{{ __('The free trial of :name has ended.', ['name' => $business->name]) }}
@elseif ($state === 'past_due')
{{ __('The last payment for :name did not go through.', ['name' => $business->name]) }}
@else
{{ __('The plan of :name is no longer active.', ['name' => $business->name]) }}
@endif

{{ __("The AI replies are paused until the plan is active again. Your customers' messages still arrive in your inbox and the menu bot keeps working.") }}
{{ __('Open "Plan & billing" in the staff area to choose a plan or update your payment.') }}
