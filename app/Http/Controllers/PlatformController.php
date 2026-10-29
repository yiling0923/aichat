<?php

namespace App\Http\Controllers;

use App\Billing\Plans;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Invite;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The platform owner console: every business, its plan, usage and revenue, and a pause switch. */
class PlatformController extends Controller
{
    public function index(Plans $plans): View
    {
        $businesses = Business::query()->orderByDesc('id')->get();
        $users = User::query()->selectRaw('business_id, count(*) as n')->groupBy('business_id')->pluck('n', 'business_id');
        $ai = AiUsage::query()->withoutGlobalScopes()->where('created_at', '>=', now()->startOfMonth())->selectRaw('business_id, count(*) as n')->groupBy('business_id')->pluck('n', 'business_id');
        $chats = Conversation::query()->withoutGlobalScopes()->where('created_at', '>=', now()->subDays(30))->selectRaw('business_id, count(*) as n')->groupBy('business_id')->pluck('n', 'business_id');
        $revenue = Payment::query()->where('status', 'paid')->selectRaw('business_id, sum(amount_cents) as c')->groupBy('business_id')->pluck('c', 'business_id');

        $rows = $businesses->map(fn (Business $b) => [
            'business' => $b,
            'state' => $plans->state($b),
            'users' => (int) ($users[$b->id] ?? 0),
            'ai' => (int) ($ai[$b->id] ?? 0),
            'chats' => (int) ($chats[$b->id] ?? 0),
            'revenue' => (int) ($revenue[$b->id] ?? 0),
        ]);

        $paying = $businesses->filter(fn (Business $b) => in_array($plans->state($b), ['active'], true) && $b->plan !== 'trial');

        return view('platform.index', [
            'rows' => $rows,
            'totals' => [
                'businesses' => $businesses->count(),
                'trials' => $businesses->filter(fn (Business $b) => $plans->state($b) === 'trial')->count(),
                'paying' => $paying->count(),
                'mrr' => $paying->sum(fn (Business $b) => $plans->priceCents($b->plan)),
                'revenue30' => (int) Payment::query()->where('status', 'paid')->where('created_at', '>=', now()->subDays(30))->sum('amount_cents'),
                'ai' => (int) $ai->sum(),
            ],
            'currency' => config('plans.currency'),
            'inviteOnly' => config('plans.signup_mode') === 'invite',
            'invites' => Invite::query()->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['nullable', 'email', 'max:150'], 'note' => ['nullable', 'string', 'max:120']]);
        $invite = Invite::generate($data['email'] ?? null, $data['note'] ?? null);

        return back()->with('status', __('Invitation code: :code (send them :url)', ['code' => $invite->code, 'url' => url('/signup?invite='.$invite->code)]));
    }

    public function pause(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:150']]);
        Business::query()->findOrFail($id)->forceFill(['status' => 'paused', 'paused_reason' => $data['reason'] ?? null])->save();

        return back()->with('status', __('Business paused. Its chat and staff area are switched off.'));
    }

    public function resume(int $id): RedirectResponse
    {
        Business::query()->findOrFail($id)->forceFill(['status' => 'active', 'paused_reason' => null])->save();

        return back()->with('status', __('Business is active again.'));
    }
}
