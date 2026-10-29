{{ __('Good morning,') }}

{{ __('Here is :name for :day.', ['name' => $business->name, 'day' => $s['label']]) }}

{{ __('New chats') }}: {{ $s['new_chats'] }}
{{ __('New customers') }}: {{ $s['new_customers'] }}
{{ __('Bookings made') }}: {{ $s['bookings_made'] }} ({{ __(':n cancelled', ['n' => $s['cancelled']]) }})
{{ __('Visits that day') }}: {{ $s['visits'] }}
{{ __('Paid') }}: {{ $s['currency'] }} {{ number_format($s['revenue'], 0) }} ({{ __(':n orders', ['n' => $s['orders_paid']]) }})
@if ($s['topics'])

{{ __('What customers asked about') }}: {{ collect($s['topics'])->map(fn ($t) => __($t['name']).' ('.$t['count'].')')->implode(', ') }}
@endif

{{ __('Needs attention now') }}:
- {{ __('Chats waiting for a person') }}: {{ $s['waiting_now'] }}
- {{ __('Overdue tasks') }}: {{ $s['overdue_tasks'] }}
- {{ __('Bookings tomorrow') }}: {{ $s['tomorrow'] }}

{{ __('Open your Today page:') }}
{{ $url }}
