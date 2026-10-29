@php $back = (int) (((array) $business->settings)['handover_return_minutes'] ?? 0); @endphp
{{ __('Hi,') }}

{{ __(':who is waiting for a reply from your team at :name. Nobody has answered for :n minutes.', ['who' => $name, 'name' => $business->name, 'n' => $minutes]) }}
@if ($reason)

{{ __('What they need') }}: {{ $reason }}
@endif

{{ __('Open the chat and reply here:') }}
{{ $url }}
@if ($urgent)

{{ __('This is the last reminder.') }}
@if ($back > 0)
{{ __('If nobody answers, the bot takes the chat back after :n minutes.', ['n' => $back]) }}
@else
{{ __('The chat stays in "Needs a person" until someone replies.') }}
@endif
@endif
