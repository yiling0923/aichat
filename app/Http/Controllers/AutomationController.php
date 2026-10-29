<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Marketing\AutomationDefaults;
use App\Marketing\AutomationRunner;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Survey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Owner or manager: switch the automatic messages on or off and edit their wording. Also shows what was sent.
 */
class AutomationController extends Controller
{
    public function index(Request $request): View
    {
        AutomationDefaults::ensure();

        return view('admin.automations', [
            'business' => $request->user()->business,
            'active' => 'automations',
            'automations' => Automation::query()->orderByRaw("case `key` when 'survey' then 1 when 'review_request' then 2 when 'rebooking' then 3 when 'no_show' then 4 else 5 end")->get(),
            'defs' => AutomationDefaults::all(),
            // People a test message can reach: they agreed to be contacted, never sent STOP, and have a chat.
            'testCustomers' => Customer::query()->whereNotNull('consented_at')->whereNull('opted_out_at')
                ->whereIn('id', \App\Models\Conversation::query()->where('channel', '!=', 'preview')->whereNotNull('customer_id')->select('customer_id'))
                ->orderByDesc('id')->limit(100)->get(['id', 'name', 'phone']),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(in_array($key, AutomationDefaults::KEYS, true), 404);
        AutomationDefaults::ensure();

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'delay_hours' => ['nullable', 'integer', 'between:0,720'],
            'message_en' => ['required', 'string', 'max:1000'],
            'message_zh' => ['nullable', 'string', 'max:1000'],
            'template_name' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]*$/'],
            'review_url' => ['nullable', 'url', 'max:500'],
            'min_rating' => ['nullable', 'integer', 'between:1,5'],
            'rebook_days' => ['nullable', 'integer', 'between:1,365'],
        ]);

        $automation = Automation::query()->where('key', $key)->firstOrFail();
        $settings = (array) $automation->settings;

        if ($key === 'review_request') {
            $settings['review_url'] = $data['review_url'] ?? '';
            $settings['min_rating'] = (int) ($data['min_rating'] ?? 4);

            if ((bool) $data['enabled'] && $settings['review_url'] === '') {
                return back()->withErrors(['automation' => __('Add your review link before switching Review request on.')]);
            }
        }
        if ($key === 'rebooking') {
            $settings['rebook_days'] = (int) ($data['rebook_days'] ?? 30);
        }

        $automation->update([
            'enabled' => (bool) $data['enabled'],
            'delay_hours' => (int) ($data['delay_hours'] ?? $automation->delay_hours),
            'message' => array_filter(['en' => $data['message_en'], 'zh' => $data['message_zh'] ?? null]),
            'template_name' => ($data['template_name'] ?? null) ?: null,
            'settings' => $settings,
        ]);

        return back()->with('status', __('Saved.'));
    }

    /** For testing: run everything that is due right now instead of waiting for the scheduler. */
    public function runNow(Request $request, AutomationRunner $runner): RedirectResponse
    {
        $on = Automation::query()->where('enabled', true)->get();
        $sent = $runner->runForBusiness($request->user()->business);
        $total = array_sum($sent);

        if ($total > 0) {
            return back()->with('status', __(':n message(s) sent. See the Activity log for what happened to each customer.', ['n' => $total]));
        }

        // Nothing went out: say why, so it does not look broken.
        if ($on->isEmpty()) {
            return back()->with('status', __('0 messages sent: no automation is switched on. Open a card below, set Status to On and press Save. Then run again.'));
        }

        $skipped = AutomationLog::query()->where('created_at', '>=', now()->subMinute())->where('status', 'skipped')->count();
        $hint = $skipped > 0
            ? __(':n customer(s) were skipped, for example because they never agreed to be contacted or sent STOP. The Activity log shows the reason for each.', ['n' => $skipped])
            : __('Nobody is due yet. The satisfaction survey needs a visit that ended at least :n hours ago (and within the last 7 days). Use "Send a test" on the survey card to try it right now.', ['n' => $on->firstWhere('key', 'survey')?->delay_hours ?? 2]);

        return back()->with('status', __('0 messages sent.').' '.$hint);
    }

    public function testSurvey(Request $request, AutomationRunner $runner): RedirectResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer']]);
        $customer = Customer::query()->findOrFail($data['customer_id']);
        AutomationDefaults::ensure();
        $automation = Automation::query()->where('key', 'survey')->firstOrFail();

        $result = $runner->testSurvey($automation, $request->user()->business, $customer);

        return back()->with('status', $result['status'] === 'sent'
            ? __('Test survey sent to :who. Open their chat to rate it: tap a number, then check the Activity log and the survey answers.', ['who' => $customer->name ?: __('the customer')])
            : 'The test was not sent: '.($result['reason'] ?? $result['status']).'.');
    }

    public function activity(Request $request): View
    {
        $tab = $request->query('tab') === 'surveys' ? 'surveys' : 'messages';
        $key = (string) $request->query('key', '');
        $status = (string) $request->query('status', '');
        $rating = (string) $request->query('rating', '');

        $surveys = Survey::query()->with(['customer', 'booking.service'])->whereNotNull('sent_at')
            ->when($rating === 'waiting', fn ($q) => $q->whereNull('rating'))
            ->when(in_array($rating, ['1', '2', '3', '4', '5'], true), fn ($q) => $q->where('rating', (int) $rating))
            ->when($rating === 'low', fn ($q) => $q->whereIn('rating', [1, 2]))
            ->orderByDesc('id')->paginate(25, ['*'], 'spage')->withQueryString();

        $answered = Survey::query()->whereNotNull('sent_at')->whereNotNull('rating');

        return view('admin.automation-activity', [
            'business' => $request->user()->business,
            'active' => 'automations',
            'tab' => $tab,
            'logs' => AutomationLog::query()->with('customer')
                ->when(in_array($key, AutomationDefaults::KEYS, true), fn ($q) => $q->where('automation_key', $key))
                ->when(in_array($status, ['sent', 'skipped', 'failed'], true), fn ($q) => $q->where('status', $status))
                ->orderByDesc('id')->paginate(30)->withQueryString(),
            'surveys' => $surveys,
            'surveyStats' => [
                'sent' => Survey::query()->whereNotNull('sent_at')->count(),
                'answered' => (clone $answered)->count(),
                'average' => (clone $answered)->avg('rating'),
                'low' => (clone $answered)->whereIn('rating', [1, 2])->count(),
                'reviews' => Survey::query()->whereNotNull('review_sent_at')->count(),
            ],
            'defs' => AutomationDefaults::all(),
            'key' => $key, 'status' => $status, 'rating' => $rating,
        ]);
    }
}
