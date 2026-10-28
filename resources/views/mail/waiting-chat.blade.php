@php $back = (int) (((array) $business->settings)['handover_return_minutes'] ?? 0); @endphp
Hi,

{{ $name }} is waiting for a reply from your team at {{ $business->name }}. Nobody has answered for {{ $minutes }} minutes.
@if ($reason)

What they need: {{ $reason }}
@endif

Open the chat and reply here:
{{ $url }}
@if ($urgent)

This is the last reminder.
@if ($back > 0)
If nobody answers, the bot takes the chat back after {{ $back }} minutes.
@else
The chat stays in "Needs a person" until someone replies.
@endif
@endif
