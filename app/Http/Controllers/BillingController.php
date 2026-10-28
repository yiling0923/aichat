<?php

namespace App\Http\Controllers;

use App\Billing\Billing;
use App\Billing\Plans;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Owner only: the current plan, usage against the limits, choosing a plan and the payment history. */
class BillingController extends Controller
{
    public function index(Request $request, Plans $plans, Billing $billing): View
    {
        $business = $request->user()->business;

        $usage = [];
        foreach (Plans::KINDS as $kind) {
            $usage[$kind] = ['used' => $plans->used($business, $kind), 'limit' => $plans->limit($business, $kind)];
        }

        return view('admin.billing', [
            'business' => $business,
            'active' => 'billing',
            'state' => $plans->state($business),
            'daysLeft' => $plans->daysLeftInTrial($business),
            'usage' => $usage,
            'catalog' => config('plans.plans'),
            'testMode' => $billing->provider()->name() === 'local',
            'payments' => Payment::query()->where('business_id', $business->id)->orderByDesc('id')->limit(12)->get(),
        ]);
    }

    public function subscribe(Request $request, Billing $billing): RedirectResponse
    {
        $data = $request->validate(['plan' => ['required', 'in:starter,growth']]);
        $result = $billing->subscribe($request->user()->business, $data['plan']);

        return $result['ok']
            ? back()->with('status', 'Your plan is now '.config('plans.plans.'.$data['plan'].'.name').'.')
            : back()->withErrors(['plan' => 'Payment failed: '.$result['error']]);
    }

    public function cancel(Request $request, Billing $billing): RedirectResponse
    {
        $billing->cancel($request->user()->business);

        return back()->with('status', 'Your plan will not renew. It keeps working until the paid month ends.');
    }
}
