<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Customers the bot has collected. Limited to the signed-in business by the BusinessScope.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')
                ->orWhere('interest', 'like', '%'.$search.'%')))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('admin.customers', [
            'business' => $request->user()->business,
            'customers' => $customers,
            'search' => $search,
        ]);
    }
}
