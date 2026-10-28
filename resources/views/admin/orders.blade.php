@extends('admin.layout')
@section('title', 'Orders')
@section('content')
    <h1>Orders</h1>
    <p class="mute">A simple record of orders or payments you enter by hand. Paid total shown: <b>{{ number_format($paidTotal, 2) }}</b></p>

    <form method="post" action="{{ url('/admin/orders') }}" class="card">
        @csrf
        <div class="grid2">
            <label class="f"><span>Customer</span>
                <select name="customer_id"><option value="">(none)</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name ?: $c->phone }}</option>@endforeach</select></label>
            <label class="f"><span>Reference (optional)</span><input type="text" name="reference"></label>
            <label class="f"><span>What was ordered</span><input type="text" name="items" placeholder="3 facials package"></label>
            <label class="f"><span>Amount</span><input type="number" step="0.01" name="amount" required></label>
            <label class="f"><span>Status</span><select name="status">@foreach ($statuses as $s)<option value="{{ $s }}" @selected($s === 'paid')>{{ ucfirst($s) }}</option>@endforeach</select></label>
            <label class="f"><span>Date</span><input type="datetime-local" name="ordered_at"></label>
        </div>
        <label class="f"><span>Notes</span><textarea name="notes" rows="2"></textarea></label>
        <p></p><button class="btn primary" type="submit">Add order</button>
    </form>

    <form method="get" class="filters">
        <label>Status
            <select name="status" onchange="this.form.submit()"><option value="">All</option>@foreach ($statuses as $s)<option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></label>
        @if ($status)<a class="btn" href="{{ route('orders') }}">Reset</a>@endif
        <span class="grow"></span>
        <span class="mute"><b>{{ $orders->total() }}</b> {{ \Illuminate\Support\Str::plural('order', $orders->total()) }}</span>
    </form>
    <table class="table">
        <thead><tr><th>Date</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                <td>{{ $o->ordered_at?->copy()->setTimezone($business->timezone)->format('d M Y') }}<br><small class="mute">{{ $o->reference }}</small></td>
                <td>@if ($o->customer)<a href="{{ route('customers.show', $o->customer_id) }}">{{ $o->customer->name ?: $o->customer->phone }}</a>@else - @endif</td>
                <td>{{ $o->items }}</td>
                <td>{{ number_format($o->amount, 2) }}</td>
                <td>
                    <form method="post" action="{{ url('/admin/orders/'.$o->id) }}" class="inline">@csrf
                        <select name="status" class="small" onchange="this.form.submit()">@foreach ($statuses as $s)<option value="{{ $s }}" @selected($o->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No orders yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $orders->links('pagination::simple-default') }}</div>
@endsection
