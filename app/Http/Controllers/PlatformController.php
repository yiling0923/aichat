<?php

namespace App\Http\Controllers;

use App\Billing\Plans;
use App\Models\AiUsage;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Invite;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformAudit;
use App\Models\Practitioner;
use App\Models\User;
use App\Services\BusinessProvisioner;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The platform owner console: every business side by side (plan, usage, activity, sales), a page for each business
 * where its plan and limits are set, support access, an activity log, and a pause switch.
 * Business data stays private: the numbers here are counts and money totals, and support access is shown and logged.
 */
class PlatformController extends Controller
{
    private const PERIODS = [7, 30, 90];

    /** @return array{days: int, rows: \Illuminate\Support\Collection<int, array<string, mixed>>, series: array<string, list<int>>, before: array<string, int>} */
    private function overview(Request $request, Plans $plans): array
    {
        $days = in_array((int) $request->query('days'), self::PERIODS, true) ? (int) $request->query('days') : 30;
        $since = now()->subDays($days);

        $businesses = Business::query()->orderByDesc('id')->get();
        $count = fn (string $model, string $dateColumn = 'created_at', ?callable $tweak = null) => $model::query()->withoutGlobalScopes()
            ->where($dateColumn, '>=', $since)->when($tweak, fn ($q) => $tweak($q))->selectRaw('business_id, count(*) as n')->groupBy('business_id')->pluck('n', 'business_id');

        $users = User::query()->selectRaw('business_id, count(*) as n')->groupBy('business_id')->pluck('n', 'business_id');
        $ai = AiUsage::query()->withoutGlobalScopes()->where('created_at', '>=', now()->startOfMonth())->selectRaw('business_id, count(*) as n')->groupBy('business_id')->pluck('n', 'business_id');
        $chats = $count(Conversation::class, 'created_at', fn ($q) => $q->where('channel', '!=', 'preview'));
        $customers = $count(Customer::class);
        $bookings = $count(Booking::class, 'created_at', fn ($q) => $q->where('status', '!=', 'cancelled'));
        $orders = Order::query()->withoutGlobalScopes()->where('status', 'paid')->where('ordered_at', '>=', $since)->selectRaw('business_id, count(*) as n, sum(amount) as total')->groupBy('business_id')->get()->keyBy('business_id');
        $revenue = Payment::query()->where('status', 'paid')->selectRaw('business_id, sum(amount_cents) as c')->groupBy('business_id')->pluck('c', 'business_id');

        $rows = $businesses->map(fn (Business $b) => [
            'business' => $b,
            'state' => $plans->state($b),
            'users' => (int) ($users[$b->id] ?? 0),
            'ai' => (int) ($ai[$b->id] ?? 0),
            'chats' => (int) ($chats[$b->id] ?? 0),
            'customers' => (int) ($customers[$b->id] ?? 0),
            'bookings' => (int) ($bookings[$b->id] ?? 0),
            'orders' => (int) ($orders[$b->id]->n ?? 0),
            'sales' => (float) ($orders[$b->id]->total ?? 0),
            'currency' => (string) (((array) $b->settings)['currency'] ?? 'RM'),
            'revenue' => (int) ($revenue[$b->id] ?? 0),
        ]);

        // One number per day for the little trend lines in the top cards, and the period before for the "up/down" arrows.
        $series = function (string $model, ?callable $tweak = null) use ($days, $since): array {
            $perDay = $model::query()->withoutGlobalScopes()->where('created_at', '>=', $since)->when($tweak, fn ($q) => $tweak($q))
                ->selectRaw('date(created_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');
            $out = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $out[] = (int) ($perDay[now()->subDays($i)->format('Y-m-d')] ?? 0);
            }

            return $out;
        };
        $before = fn (string $model, ?callable $tweak = null) => $model::query()->withoutGlobalScopes()->whereBetween('created_at', [now()->subDays($days * 2), $since])->when($tweak, fn ($q) => $tweak($q))->count();
        $notPreview = fn ($q) => $q->where('channel', '!=', 'preview');
        $notCancelled = fn ($q) => $q->where('status', '!=', 'cancelled');

        return [
            'days' => $days,
            'rows' => $rows,
            'series' => ['chats' => $series(Conversation::class, $notPreview), 'bookings' => $series(Booking::class, $notCancelled)],
            'before' => ['chats' => $before(Conversation::class, $notPreview), 'bookings' => $before(Booking::class, $notCancelled)],
        ];
    }

    public function index(Request $request, Plans $plans): View
    {
        ['days' => $days, 'rows' => $rows, 'series' => $series, 'before' => $before] = $this->overview($request, $plans);
        $businesses = $rows->pluck('business');
        $paying = $businesses->filter(fn (Business $b) => in_array($plans->state($b), ['active'], true) && $b->plan !== 'trial');

        // The customers' own money, per currency (businesses may use different currencies).
        $sales = $rows->groupBy('currency')->map(fn ($g) => $g->sum('sales'))->filter(fn ($v) => $v > 0)->map(fn ($v, $c) => $c.' '.number_format($v, 0))->implode(' · ');

        return view('platform.index', [
            'rows' => $rows,
            'days' => $days,
            'series' => $series,
            'before' => $before,
            'totals' => [
                'businesses' => $businesses->count(),
                'trials' => $businesses->filter(fn (Business $b) => $plans->state($b) === 'trial')->count(),
                'paying' => $paying->count(),
                'mrr' => $paying->sum(fn (Business $b) => $plans->priceCents($b->plan)),
                'revenue30' => (int) Payment::query()->where('status', 'paid')->where('created_at', '>=', now()->subDays(30))->sum('amount_cents'),
                'ai' => (int) $rows->sum('ai'),
                'chats' => (int) $rows->sum('chats'),
                'bookings' => (int) $rows->sum('bookings'),
                'sales' => $sales !== '' ? $sales : '-',
            ],
            'currency' => config('plans.currency'),
            'plans' => (array) config('plans.plans'),
            'inviteOnly' => config('plans.signup_mode') === 'invite',
            'invites' => Invite::query()->orderByDesc('id')->limit(30)->get(),
            'audits' => PlatformAudit::query()->with(['business', 'user'])->orderByDesc('id')->limit(100)->get(),
        ]);
    }

    /** All businesses side by side, as a spreadsheet. */
    public function export(Request $request, Plans $plans): StreamedResponse
    {
        ['days' => $days, 'rows' => $rows] = $this->overview($request, $plans);

        return response()->streamDownload(function () use ($rows, $days): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Business', 'Address name', 'Status', 'Plan', 'State', 'Trial ends', 'People', 'Chats ('.$days.' d)', 'New customers ('.$days.' d)', 'Bookings ('.$days.' d)', 'Paid orders ('.$days.' d)', 'Customer money ('.$days.' d)', 'Currency', 'AI calls (month)', 'Paid to you (all time)']);
            foreach ($rows as $r) {
                $b = $r['business'];
                fputcsv($out, array_map([ReportsController::class, 'safe'], [$b->name, $b->slug, $b->status, $b->plan ?? 'internal', $r['state'], $b->trial_ends_at?->format('Y-m-d') ?? '', $r['users'], $r['chats'], $r['customers'], $r['bookings'], $r['orders'], number_format($r['sales'], 2, '.', ''), $r['currency'], $r['ai'], number_format($r['revenue'] / 100, 2, '.', '')]));
            }
            fclose($out);
        }, 'businesses-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** One business: status, plan, usage against limits, the last 30 days, team, channels, payments and what you did to it. */
    public function show(int $id, Plans $plans): View
    {
        $business = Business::query()->findOrFail($id);
        $since = now()->subDays(30);
        $scoped = fn (string $model) => $model::query()->withoutGlobalScopes()->where('business_id', $id);

        $kinds = collect(Plans::KINDS)->mapWithKeys(fn ($k) => [$k => ['used' => $plans->used($business, $k), 'limit' => $plans->limit($business, $k), 'plan' => (($plans->definition($business)['limits'] ?? [])[$k] ?? null), 'custom' => (((array) $business->settings)['limit_overrides'] ?? [])[$k] ?? null]]);
        $paid30 = $scoped(Order::class)->where('status', 'paid')->where('ordered_at', '>=', $since);

        return view('platform.show', [
            'b' => $business,
            'state' => $plans->state($business),
            'plans' => (array) config('plans.plans'),
            'kinds' => $kinds,
            'labels' => (array) config('plans.labels'),
            'currency' => (string) (((array) $business->settings)['currency'] ?? 'RM'),
            'stats' => [
                'chats' => $scoped(Conversation::class)->where('channel', '!=', 'preview')->where('created_at', '>=', $since)->count(),
                'customers' => $scoped(Customer::class)->count(),
                'newCustomers' => $scoped(Customer::class)->where('created_at', '>=', $since)->count(),
                'bookings' => $scoped(Booking::class)->where('created_at', '>=', $since)->where('status', '!=', 'cancelled')->count(),
                'upcoming' => $scoped(Booking::class)->where('status', 'confirmed')->where('starts_at', '>=', now())->count(),
                'orders' => (clone $paid30)->count(),
                'sales' => (float) (clone $paid30)->sum('amount'),
                'lastChat' => $scoped(Conversation::class)->max('last_message_at'),
            ],
            'team' => User::query()->where('business_id', $id)->orderBy('role')->orderBy('name')->get(),
            'channels' => $scoped(Channel::class)->orderBy('type')->get(['id', 'type', 'name', 'active', 'external_id', 'last_inbound_at', 'last_error']),
            'practitioners' => $scoped(Practitioner::class)->where('active', true)->count(),
            'payments' => Payment::query()->where('business_id', $id)->orderByDesc('id')->limit(12)->get(),
            'audits' => PlatformAudit::query()->with('user')->where('business_id', $id)->orderByDesc('id')->limit(30)->get(),
            'hasOwner' => User::query()->where('business_id', $id)->where('role', 'owner')->where('active', true)->exists(),
        ]);
    }

    /** Make a new business and its owner from the console. */
    public function store(Request $request, BusinessProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        [$business] = $provisioner->create($data['business_name'], $data['name'], $data['email'], $data['password'], true);
        PlatformAudit::record('business_created', $business->id, 'Owner: '.$data['email']);

        return redirect()->route('platform.show', $business->id)->with('status', __('Business created. Give the owner the sign-in details.'));
    }

    /** Move a business to another plan, or change its trial end or paid-until date. */
    public function plan(Request $request, int $id): RedirectResponse
    {
        $business = Business::query()->findOrFail($id);
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_merge(array_keys((array) config('plans.plans')), ['internal']))],
            'date' => ['nullable', 'date'],
            'extend_days' => ['nullable', 'integer', 'between:1,365'],
        ]);
        $this->applyPlan($business, $data['plan'], $data['date'] ?? null, isset($data['extend_days']) ? (int) $data['extend_days'] : null);

        return back()->with('status', __('Plan saved.'));
    }

    /** Move one business to a plan (or change its trial end / paid-until date) and write it in the activity log. */
    private function applyPlan(Business $business, string $plan, ?string $date, ?int $extendDays, string $note = ''): void
    {
        $before = ($business->plan ?? 'internal').($business->trial_ends_at ? ' (trial to '.$business->trial_ends_at->format('Y-m-d').')' : '');

        if ($plan === 'internal') {
            $business->forceFill(['plan' => null, 'subscription_status' => null, 'trial_ends_at' => null, 'current_period_ends_at' => null]);
        } elseif ($plan === 'trial') {
            $end = $extendDays
                ? max($business->trial_ends_at ?? now(), now())->copy()->addDays($extendDays)
                : ($date ? Carbon::parse($date)->endOfDay() : ($business->trial_ends_at ?? now()->addDays((int) config('plans.trial_days'))));
            $business->forceFill(['plan' => 'trial', 'subscription_status' => 'trialing', 'trial_ends_at' => $end, 'current_period_ends_at' => null]);
        } else {
            $business->forceFill(['plan' => $plan, 'subscription_status' => 'active', 'trial_ends_at' => null, 'current_period_ends_at' => $date ? Carbon::parse($date)->endOfDay() : null]);
        }
        $business->save();

        PlatformAudit::record('plan_changed', $business->id, 'From '.$before.' to '.($business->plan ?? 'internal').($business->plan === 'trial' ? ' (trial to '.$business->trial_ends_at?->format('Y-m-d').')' : ($business->current_period_ends_at ? ' (paid until '.$business->current_period_ends_at->format('Y-m-d').')' : '')).$note);
    }

    /** Change several businesses at once: pause, resume, or move them to a plan (or extend their trial). */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['pause', 'resume', 'plan'])],
            'reason' => ['nullable', 'string', 'max:150'],
            'plan' => ['nullable', Rule::in(array_merge(array_keys((array) config('plans.plans')), ['internal']))],
            'date' => ['nullable', 'date'],
            'extend_days' => ['nullable', 'integer', 'between:1,365'],
        ]);

        if ($data['action'] === 'plan' && empty($data['plan'])) {
            return back()->withErrors(['bulk' => __('Choose a plan first.')]);
        }

        $done = 0;
        $skipped = 0;

        foreach (Business::query()->whereIn('id', $data['ids'])->get() as $business) {
            if ($data['action'] === 'pause') {
                if ($business->status === 'paused') { $skipped++; continue; }
                $business->forceFill(['status' => 'paused', 'paused_reason' => $data['reason'] ?? null])->save();
                PlatformAudit::record('paused', $business->id, trim(($data['reason'] ?? '').' (bulk)'));
            } elseif ($data['action'] === 'resume') {
                if ($business->status !== 'paused') { $skipped++; continue; }
                $business->forceFill(['status' => 'active', 'paused_reason' => null])->save();
                PlatformAudit::record('resumed', $business->id, 'bulk');
            } else {
                // Extending a trial only makes sense for a business that is on a trial.
                if (! empty($data['extend_days']) && $data['plan'] === 'trial' && $business->plan !== 'trial') { $skipped++; continue; }
                $this->applyPlan($business, $data['plan'], $data['date'] ?? null, isset($data['extend_days']) ? (int) $data['extend_days'] : null, ' (bulk)');
            }
            $done++;
        }

        return back()->with('status', __(':n updated.', ['n' => $done]).($skipped > 0 ? ' '.__(':n skipped (nothing to change).', ['n' => $skipped]) : ''));
    }

    /** Give one business its own limits instead of the plan's. */
    public function limits(Request $request, int $id): RedirectResponse
    {
        $business = Business::query()->findOrFail($id);
        $overrides = [];

        foreach (Plans::KINDS as $kind) {
            $mode = (string) $request->input('mode.'.$kind, 'default');
            if ($mode === 'unlimited') {
                $overrides[$kind] = 'unlimited';
            } elseif ($mode === 'number') {
                $value = $request->input('number.'.$kind);
                if (! is_numeric($value) || (int) $value < 0 || (int) $value > 1000000) {
                    return back()->withErrors(['limits' => __('Type a number from 0 to 1,000,000 for each limit you set.')]);
                }
                $overrides[$kind] = (int) $value;
            }
        }

        $settings = (array) $business->settings;
        if ($overrides === []) {
            unset($settings['limit_overrides']);
        } else {
            $settings['limit_overrides'] = $overrides;
        }
        $business->forceFill(['settings' => $settings])->save();

        PlatformAudit::record('limits_changed', $business->id, $overrides === [] ? 'Back to the plan limits' : collect($overrides)->map(fn ($v, $k) => $k.': '.$v)->implode(', '));

        return back()->with('status', __('Limits saved.'));
    }

    /** Set a new password for someone in that business (when they are locked out). */
    public function password(Request $request, int $id): RedirectResponse
    {
        $business = Business::query()->findOrFail($id);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'new_password' => ['required', 'string', 'min:8', 'max:100']]);
        $user = User::query()->where('business_id', $business->id)->findOrFail($data['user_id']);

        $user->password = $data['new_password'];
        $user->save();
        PlatformAudit::record('password_set', $business->id, $user->email);

        return back()->with('status', __('Password changed for :name.', ['name' => $user->name]));
    }

    /** Open the business's staff area (read-only, or as its owner). The session says so on every page and the start and stop are logged. */
    public function support(Request $request, int $id): RedirectResponse
    {
        $business = Business::query()->findOrFail($id);
        $mode = $request->input('mode') === 'edit' ? 'edit' : 'read';
        $owner = User::query()->where('business_id', $business->id)->where('role', 'owner')->where('active', true)->orderBy('id')->first();

        if ($owner === null) {
            return back()->withErrors(['support' => __('This business has no active owner to open.')]);
        }

        $platformId = $request->user()->id;
        PlatformAudit::record('support_start', $business->id, $mode === 'edit' ? 'Acting as the owner ('.$owner->email.')' : 'Read-only view');

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('support', ['by' => $platformId, 'business_id' => $business->id, 'business' => $business->name, 'mode' => $mode]);

        return redirect()->route('inbox');
    }

    /** Back to the platform console. */
    public function stopSupport(Request $request): RedirectResponse
    {
        $support = (array) $request->session()->get('support');
        abort_if($support === [], 404);

        $platform = User::query()->find($support['by'] ?? 0);
        abort_unless($platform !== null && $platform->isPlatformOwner() && $platform->active !== false, 403);

        Auth::login($platform);
        $request->session()->forget('support');
        $request->session()->regenerate();
        PlatformAudit::record('support_stop', (int) $support['business_id'], $support['mode'] === 'edit' ? 'Acting as the owner' : 'Read-only view');

        return redirect()->route('platform.show', (int) $support['business_id']);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['nullable', 'email', 'max:150'], 'note' => ['nullable', 'string', 'max:120']]);
        $invite = Invite::generate($data['email'] ?? null, $data['note'] ?? null);
        PlatformAudit::record('invite_made', null, trim(($data['email'] ?? '').' '.($data['note'] ?? '')) ?: 'Open code');

        return back()->with('status', __('Invitation code: :code (send them :url)', ['code' => $invite->code, 'url' => url('/signup?invite='.$invite->code)]));
    }

    public function pause(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:150']]);
        Business::query()->findOrFail($id)->forceFill(['status' => 'paused', 'paused_reason' => $data['reason'] ?? null])->save();
        PlatformAudit::record('paused', $id, $data['reason'] ?? null);

        return back()->with('status', __('Business paused. Its chat and staff area are switched off.'));
    }

    public function resume(int $id): RedirectResponse
    {
        Business::query()->findOrFail($id)->forceFill(['status' => 'active', 'paused_reason' => null])->save();
        PlatformAudit::record('resumed', $id);

        return back()->with('status', __('Business is active again.'));
    }
}
