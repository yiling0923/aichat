<?php

namespace App\Http\Controllers;

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
                return back()->withErrors(['automation' => 'Add your review link before switching Review request on.']);
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

        return back()->with('status', 'Saved.');
    }

    /** For testing: run everything that is due right now instead of waiting for the scheduler. */
    public function runNow(Request $request, AutomationRunner $runner): RedirectResponse
    {
        $sent = $runner->runForBusiness($request->user()->business);
        $total = array_sum($sent);

        return back()->with('status', $total.' message(s) sent. See the Activity page for what happened to each customer.');
    }

    public function activity(Request $request): View
    {
        $key = (string) $request->query('key', '');
        $status = (string) $request->query('status', '');

        return view('admin.automation-activity', [
            'business' => $request->user()->business,
            'active' => 'automations',
            'logs' => AutomationLog::query()->with('customer')
                ->when(in_array($key, AutomationDefaults::KEYS, true), fn ($q) => $q->where('automation_key', $key))
                ->when(in_array($status, ['sent', 'skipped', 'failed'], true), fn ($q) => $q->where('status', $status))
                ->orderByDesc('id')->paginate(30)->withQueryString(),
            'surveys' => Survey::query()->with('customer')->whereNotNull('sent_at')->orderByDesc('id')->limit(30)->get(),
            'defs' => AutomationDefaults::all(),
            'key' => $key, 'status' => $status,
        ]);
    }
}
