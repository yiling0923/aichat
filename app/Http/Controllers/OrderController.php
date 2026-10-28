<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A simple list of orders or payments, entered by hand. Not a shop checkout.
 */
class OrderController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'refunded', 'cancelled'];

    public const PERIODS = ['all' => 'All time', 'month' => 'This month', 'last_month' => 'Last month', '30' => 'Last 30 days', '90' => 'Last 90 days', 'year' => 'This year'];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $customer = (int) $request->query('customer', 0);
        $q = trim((string) $request->query('q', ''));
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : 'all';
        $business = $request->user()->business;
        $tz = $business->timezone ?: 'UTC';
        $now = \Carbon\Carbon::now($tz);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        [$from, $to] = match ($period) {
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            '30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            '90' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [null, null],
        };

        // Everything except the status filter, so the status buttons can show their counts.
        $base = fn () => Order::query()
            ->when($from, fn ($w) => $w->whereBetween('ordered_at', [$from->copy()->utc(), $to->copy()->utc()]))
            ->when($customer > 0, fn ($w) => $w->where('customer_id', $customer))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('reference', 'like', $like)->orWhere('items', 'like', $like)->orWhere('notes', 'like', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like))));

        $totals = $base()->selectRaw('status, count(*) as n, sum(amount) as total')->groupBy('status')->get()->keyBy('status');

        $orders = $base()->with('customer')
            ->when(in_array($status, self::STATUSES, true), fn ($w) => $w->where('status', $status))
            ->orderByDesc('ordered_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.orders', [
            'business' => $business,
            'active' => 'orders',
            'orders' => $orders,
            'customers' => Customer::query()->orderBy('name')->limit(300)->get(['id', 'name', 'phone']),
            'statuses' => self::STATUSES,
            'status' => $status, 'customerId' => $customer, 'q' => $q, 'period' => $period, 'periods' => self::PERIODS,
            'paidTotal' => (float) ($totals['paid']->total ?? 0),
            'sums' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => ['n' => (int) ($totals[$s]->n ?? 0), 'total' => (float) ($totals[$s]->total ?? 0)]])->all(),
            'filterCustomer' => $customer > 0 ? Customer::query()->find($customer) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:60'],
            'items' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'between:0,10000000'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'ordered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerId = ! empty($data['customer_id']) ? Customer::query()->findOrFail($data['customer_id'])->id : null;
        $tz = $request->user()->business->timezone ?: 'UTC';

        Order::create([
            'customer_id' => $customerId,
            'reference' => $data['reference'] ?? null,
            'items' => $data['items'] ?? null,
            'amount' => $data['amount'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'ordered_at' => ! empty($data['ordered_at']) ? \Carbon\Carbon::parse($data['ordered_at'], $tz)->utc() : now(),
        ]);

        return back()->with('status', 'Order added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'customer_id' => ['sometimes', 'nullable', 'integer'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:60'],
            'items' => ['sometimes', 'nullable', 'string', 'max:500'],
            'amount' => ['sometimes', 'numeric', 'between:0,10000000'],
            'ordered_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()->findOrFail($id);
        $tz = $request->user()->business->timezone ?: 'UTC';

        if (array_key_exists('customer_id', $data)) {
            $data['customer_id'] = ! empty($data['customer_id']) ? Customer::query()->findOrFail($data['customer_id'])->id : null;
        }
        if (array_key_exists('ordered_at', $data)) {
            $data['ordered_at'] = ! empty($data['ordered_at']) ? \Carbon\Carbon::parse($data['ordered_at'], $tz)->utc() : $order->ordered_at;
        }

        $order->update($data);

        return back()->with('status', 'Order updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Order::query()->findOrFail($id)->delete();

        return back()->with('status', 'Order deleted.');
    }
}
