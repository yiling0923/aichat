Hi,

{{ $business->name }} has reached its plan limit for: {{ config('plans.labels.'.$kind) }}.

@if ($kind === 'ai')
The AI has paused. Your customers' messages still arrive in your inbox and the menu bot keeps working.
@endif
Open "Plan & billing" in the staff area to upgrade.
