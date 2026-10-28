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

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $customer = (int) $request->query('customer', 0);

        $orders = Order::query()->with('customer')
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($customer > 0, fn ($q) => $q->where('customer_id', $customer))
            ->orderByDesc('ordered_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.orders', [
            'business' => $request->user()->business,
            'active' => 'orders',
            'orders' => $orders,
            'customers' => Customer::query()->orderBy('name')->limit(300)->get(['id', 'name', 'phone']),
            'statuses' => self::STATUSES,
            'status' => $status, 'customerId' => $customer,
            'paidTotal' => Order::query()->where('status', 'paid')->sum('amount'),
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
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)]]);
        Order::query()->findOrFail($id)->update(['status' => $data['status']]);

        return back()->with('status', 'Order updated.');
    }
}
