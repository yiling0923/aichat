@extends('admin.layout')
@section('title', __('Orders'))
@section('content')
    @php
        $cur = $business->settings['currency'] ?? 'RM';
        $money = fn ($v) => $cur.' '.number_format($v, 2);
        $tone = ['paid' => 'ok', 'pending' => 'warn', 'refunded' => '', 'cancelled' => ''];
        $label = ['paid' => __('Paid'), 'pending' => __('Waiting for payment'), 'refunded' => __('Refunded'), 'cancelled' => __('Cancelled')];
        $keep = array_filter(['q' => $q, 'period' => $period === 'all' ? null : $period, 'customer' => $customerId ?: null]);
        $totalCount = collect($sums)->sum('n');
        $exportPeriod = ['month' => 'this_month', 'last_month' => 'last_month', '30' => '30', '90' => '90'][$period] ?? 'custom';
        $export = route('reports.export', array_filter(['section' => 'orders', 'period' => $exportPeriod, 'from' => $exportPeriod === 'custom' ? ($period === 'year' ? now($business->timezone)->startOfYear()->format('Y-m-d') : '2000-01-01') : null, 'to' => $exportPeriod === 'custom' ? now($business->timezone)->format('Y-m-d') : null]));
        $chips = array_filter([
            $q !== '' ? [__('Search:').' '.$q, 'q'] : null,
            $period !== 'all' ? [__('Period:').' '.__($periods[$period]), 'period'] : null,
            $filterCustomer ? [__('Customer:').' '.($filterCustomer->name ?: $filterCustomer->phone), 'customer'] : null,
        ]);
    @endphp
    <div class="page-head">
        <div><h1>{{ __('Orders') }}</h1><p class="mute">{{ __('A simple record of sales and payments. A finished visit adds an unpaid order for you; press Mark paid when the customer pays at the shop. Not an online shop checkout.') }}</p></div>
        <div class="bar">
            <a class="btn" href="{{ $export }}">{{ __('Export CSV') }}</a>
            <button type="button" class="btn primary" id="new-order">{{ __('New order') }}</button>
        </div>
    </div>

    <div class="kpis main">
        <div class="kpi"><span class="kpi-l">{{ __('Paid') }}</span><b>{{ $money($sums['paid']['total']) }}</b><span class="kpi-s">{{ $sums['paid']['n'] === 1 ? __('1 order') : __(':n orders', ['n' => $sums['paid']['n']]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Waiting for payment') }}</span><b>{{ $money($sums['pending']['total']) }}</b><span class="kpi-s">{{ $sums['pending']['n'] === 1 ? __('1 order') : __(':n orders', ['n' => $sums['pending']['n']]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Refunded') }}</span><b>{{ $money($sums['refunded']['total']) }}</b><span class="kpi-s">{{ $sums['refunded']['n'] === 1 ? __('1 order') : __(':n orders', ['n' => $sums['refunded']['n']]) }}</span></div>
        <div class="kpi"><span class="kpi-l">{{ __('Average paid order') }}</span><b>{{ $sums['paid']['n'] ? $money($sums['paid']['total'] / $sums['paid']['n']) : '-' }}</b><span class="kpi-s">{{ __(':n in total', ['n' => $totalCount]) }}</span></div>
    </div>

    <form method="get" class="fbar">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        @if ($customerId)<input type="hidden" name="customer" value="{{ $customerId }}">@endif
        <div class="seg">
            <a href="{{ route('orders', $keep) }}" @class(['on' => $status === ''])>{{ __('All') }} ({{ $totalCount }})</a>
            @foreach ($statuses as $s)<a href="{{ route('orders', $keep + ['status' => $s]) }}" @class(['on' => $status === $s])>{{ $label[$s] }} ({{ $sums[$s]['n'] }})</a>@endforeach
        </div>
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search orders') }}" aria-label="{{ __('Search orders: customer, item, reference or note') }}">
        <select name="period" aria-label="{{ __('Period') }}">@foreach ($periods as $k => $name)<option value="{{ $k }}" @selected($period === $k)>{{ __($name) }}</option>@endforeach</select>
        <button class="btn primary" type="submit">{{ __('Search') }}</button>
        @if ($chips || $status)<a class="btn" href="{{ route('orders') }}">{{ __('Reset') }}</a>@endif
    </form>
    @if ($chips)
        <div class="result-line">@foreach ($chips as [$text, $param])<span class="fchip">{{ $text }} <a href="{{ request()->fullUrlWithQuery([$param => null, 'page' => null]) }}" aria-label="{{ __('Remove filter') }}">&times;</a></span>@endforeach</div>
    @endif

    <div class="table-wrap" style="max-height:none">
    <table class="table">
        <thead><tr>{!! \App\Support\SortLink::th('date', __('Date'), $sort, $dir) !!}{!! \App\Support\SortLink::th('customer', __('Customer'), $sort, $dir) !!}<th>{{ __('What') }}</th>{!! \App\Support\SortLink::th('amount', __('Amount'), $sort, $dir, 'num') !!}{!! \App\Support\SortLink::th('status', __('Status'), $sort, $dir) !!}<th></th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            @php $local = $o->ordered_at?->copy()->setTimezone($business->timezone); @endphp
            <tr>
                <td>{{ \App\Support\Lang::fmt($local, 'd M Y', 'Y年n月j日') }}<br><small class="mute">{{ $o->reference }}</small></td>
                <td>@if ($o->customer)<a href="{{ route('customers.show', $o->customer_id) }}">{{ $o->customer->name ?: $o->customer->phone }}</a>@else <span class="mute">{{ __('No customer') }}</span> @endif</td>
                <td>@if (filled($o->items)){{ $o->items }}@else<span class="mute">-</span>@endif @if ($o->notes)<br><small class="mute">{{ \Illuminate\Support\Str::limit($o->notes, 70) }}</small>@endif</td>
                <td style="text-align:right;white-space:nowrap"><b>{{ number_format($o->amount, 2) }}</b></td>
                <td><span class="tag {{ $tone[$o->status] ?? '' }}">{{ $label[$o->status] ?? $o->status }}</span></td>
                <td style="text-align:right;white-space:nowrap">
                    @if ($o->status === 'pending')<form method="post" action="{{ url('/admin/orders/'.$o->id) }}" style="display:inline">@csrf<input type="hidden" name="status" value="paid"><button type="submit" class="btn small primary" title="{{ __('The customer has paid') }}">{{ __('Mark paid') }}</button></form>@endif
                    <button type="button" class="btn small" data-edit
                    data-action="{{ url('/admin/orders/'.$o->id) }}" data-delete="{{ url('/admin/orders/'.$o->id.'/delete') }}"
                    data-customer="{{ $o->customer_id }}" data-reference="{{ $o->reference }}" data-items="{{ $o->items }}" data-amount="{{ $o->amount }}"
                    data-status="{{ $o->status }}" data-date="{{ $local?->format('Y-m-d\TH:i') }}" data-notes="{{ $o->notes }}">{{ __('Edit') }}</button></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">{{ $chips || $status ? __('No orders match these filters.') : __('No orders yet. Press "New order" when you make a sale.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pager">{{ $orders->links('pagination.bar') }}</div>

    <dialog id="order-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3 id="od-title">{{ __('New order') }}</h3>
        <form method="post" id="od-form" action="{{ url('/admin/orders') }}">
            @csrf
            <div class="grid2" style="margin-top:12px">
                <p id="od-summary" class="mute" hidden style="margin:2px 0 4px"></p>
            <label class="f" data-paying-hide><span>{{ __('Customer') }}</span>
                    <select name="customer_id" id="od-customer"><option value="">{{ __('No customer') }}</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name ?: $c->phone }}</option>@endforeach</select></label>
                <label class="f"><span>{{ __('Amount') }} ({{ $cur }})</span><input type="number" step="0.01" min="0" name="amount" id="od-amount" required></label>
                <label class="f" data-paying-hide><span>{{ __('What was ordered') }}</span><input type="text" name="items" id="od-items" maxlength="500" placeholder="{{ __('3 facials package') }}"></label>
                <label class="f"><span>{{ __('Status') }}</span><select name="status" id="od-status">@foreach ($statuses as $s)<option value="{{ $s }}">{{ $label[$s] }}</option>@endforeach</select></label>
                <label class="f" data-paying-hide><span>{{ __('Date (empty means now)') }}</span><input type="datetime-local" name="ordered_at" id="od-date"></label>
                <label class="f" data-paying-hide><span>{{ __('Reference (optional)') }}</span><input type="text" name="reference" id="od-ref" maxlength="60"></label>
            </div>
            <label class="f" id="od-method-wrap" hidden><span>{{ __('How did they pay?') }}</span>
                <select name="method" id="od-method"><option value="">{{ __('Not recorded') }}</option>@foreach ($methods as $k => $m)<option value="{{ $k }}">{{ __($m) }}</option>@endforeach</select></label>
            <label class="f"><span>{{ __('Notes') }}</span><textarea name="notes" id="od-notes" rows="2" maxlength="2000"></textarea></label>
            <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Save') }}</button><button class="btn danger push" type="submit" id="od-delete" hidden onclick="return confirm({{ json_encode(__('Delete this order? This cannot be undone.'), JSON_UNESCAPED_UNICODE) }})">{{ __('Delete') }}</button></div>
        </form>
    </dialog>
@endsection
@push('scripts')
<script>
(function () {
  var dlg = document.getElementById('order-dialog'), form = document.getElementById('od-form'), del = document.getElementById('od-delete');
  var f = { c: 'od-customer', a: 'od-amount', i: 'od-items', s: 'od-status', d: 'od-date', r: 'od-ref', n: 'od-notes' };
  Object.keys(f).forEach(function (k) { f[k] = document.getElementById(f[k]); });
  var methodWrap = document.getElementById('od-method-wrap'), methodSel = document.getElementById('od-method');
  function showMethod() { methodWrap.hidden = f.s.value !== 'paid' || f.s.dataset.was === 'paid'; if (methodWrap.hidden) { methodSel.value = ''; } }
  f.s.addEventListener('change', showMethod);
  function open(d, pay) {
    var edit = d && d.action;
    document.getElementById('od-title').textContent = pay ? {!! json_encode(__('Collect payment'), JSON_UNESCAPED_UNICODE) !!} : (edit ? {!! json_encode(__('Edit order'), JSON_UNESCAPED_UNICODE) !!} : {!! json_encode(__('New order'), JSON_UNESCAPED_UNICODE) !!});
    form.action = edit ? d.action : '{{ url('/admin/orders') }}';
    f.c.value = edit ? (d.customer || '') : ''; f.a.value = edit ? d.amount : ''; f.i.value = edit ? (d.items || '') : '';
    f.s.value = edit ? d.status : 'paid'; f.d.value = edit ? (d.date || '') : ''; f.r.value = edit ? (d.reference || '') : ''; f.n.value = edit ? (d.notes || '') : '';
    document.querySelectorAll('[data-paying-hide]').forEach(function (el) { el.hidden = !!pay; });
    var sum = document.getElementById('od-summary'); sum.hidden = !pay;
    if (pay) { sum.textContent = [f.c.options[f.c.selectedIndex] ? f.c.options[f.c.selectedIndex].text : '', d.items].filter(Boolean).join(' · '); }
    del.hidden = !edit || pay; if (edit) { del.setAttribute('formaction', d.delete); }
    f.s.dataset.was = edit ? d.status : '';
    if (pay) { f.s.value = 'paid'; }
    showMethod();
    dlg.showModal(); (pay ? methodSel : (edit ? f.a : f.i)).focus();
  }
  document.getElementById('new-order').addEventListener('click', function () { open(null); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset); }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
  @if ($payOrder)
  // Arrived from "Mark done": open the payment details for that visit straight away.
  open({ action: @json(url('/admin/orders/'.$payOrder->id)), delete: '', customer: @json($payOrder->customer_id), reference: @json($payOrder->reference), items: @json($payOrder->items), amount: @json((string) $payOrder->amount), status: 'pending', date: '', notes: @json($payOrder->notes) }, true);
  @endif
})();
</script>
@endpush
