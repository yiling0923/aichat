Good morning,

Here is {{ $business->name }} for {{ $s['label'] }}.

New chats: {{ $s['new_chats'] }}
New customers: {{ $s['new_customers'] }}
Bookings made: {{ $s['bookings_made'] }} ({{ $s['cancelled'] }} cancelled)
Visits that day: {{ $s['visits'] }}
Paid: {{ $s['currency'] }} {{ number_format($s['revenue'], 0) }} ({{ $s['orders_paid'] }} orders)
@if ($s['topics'])

What customers asked about: {{ collect($s['topics'])->map(fn ($t) => $t['name'].' ('.$t['count'].')')->implode(', ') }}
@endif

Needs attention now:
- Chats waiting for a person: {{ $s['waiting_now'] }}
- Overdue tasks: {{ $s['overdue_tasks'] }}
- Bookings tomorrow: {{ $s['tomorrow'] }}

Open your Today page:
{{ $url }}
