<?php

namespace App\Http\Controllers;

use App\Billing\Plans;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Owner only: business profile, look, and team accounts with roles.
 */
class SettingsController extends Controller
{
    public const ROLES = ['owner', 'manager', 'staff'];

    public function index(Request $request): View
    {
        $business = $request->user()->business;

        return view('admin.settings', [
            'business' => $business,
            'active' => 'settings',
            'team' => User::query()->where('business_id', $business->id)->orderBy('name')->get(),
            'timezones' => $this->timezones($business->timezone),
            'currencies' => ['RM' => 'RM (Malaysian ringgit)', 'S$' => 'S$ (Singapore dollar)', 'HK$' => 'HK$ (Hong Kong dollar)', '$' => '$ (dollar)', '€' => '€ (euro)', '£' => '£ (pound)', '¥' => '¥ (yuan or yen)', 'Rp' => 'Rp (rupiah)', '฿' => '฿ (baht)'],
            'palette' => ['#0f766e', '#2563eb', '#7c3aed', '#db2777', '#b91c1c', '#c2410c', '#15803d', '#475569'],
            'teamUsed' => app(Plans::class)->used($business, 'staff'),
            'teamLimit' => app(Plans::class)->limit($business, 'staff'),
            'chatUrl' => route('chat.show', $business->slug),
            'tab' => $request->query('tab') === 'team' ? 'team' : 'business',
        ]);
    }

    /** Popular time zones first, then everything else by region. @return array<string, list<string>> */
    private function timezones(?string $current): array
    {
        $popular = ['Asia/Kuala_Lumpur', 'Asia/Singapore', 'Asia/Hong_Kong', 'Asia/Shanghai', 'Asia/Bangkok', 'Asia/Jakarta', 'Australia/Sydney', 'Europe/London', 'America/New_York', 'UTC'];
        if ($current && ! in_array($current, $popular, true)) {
            array_unshift($popular, $current);
        }

        $rest = [];
        foreach (\DateTimeZone::listIdentifiers() as $id) {
            if (! in_array($id, $popular, true)) {
                $rest[explode('/', $id)[0]][] = $id;
            }
        }

        return ['Common' => $popular] + $rest;
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'address' => ['nullable', 'string', 'max:200'],
            'currency' => ['nullable', 'string', 'max:6'],
        ]);

        $business = $request->user()->business;
        $settings = (array) $business->settings;
        $settings['accent'] = $data['accent'] ?? null;
        if (array_key_exists('address', $data)) {
            $settings['address'] = $data['address'];
        }
        if (! empty($data['currency'])) {
            $settings['currency'] = $data['currency'];
        }
        $business->update(['name' => $data['name'], 'timezone' => $data['timezone'], 'settings' => $settings]);

        return back()->with('status', __('Settings saved.'));
    }

    public function saveAlerts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_minutes' => ['required', 'integer', 'in:0,15,30,60,120,240'],
            'alert_email' => ['nullable', 'boolean'],
            'alert_whatsapp' => ['nullable', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
            'alert_whatsapp_template' => ['nullable', 'regex:/^[a-z0-9_]{0,80}$/'],
        ], ['alert_whatsapp.regex' => 'Write the WhatsApp number with its country code, for example 60123456789.']);

        $business = $request->user()->business;
        $settings = (array) $business->settings;
        $settings['handover_return_minutes'] = (int) $data['return_minutes'];
        $settings['alert_email'] = (bool) ($data['alert_email'] ?? false);
        $settings['alert_whatsapp'] = ! empty($data['alert_whatsapp']) ? preg_replace('/\D+/', '', $data['alert_whatsapp']) : null;
        $settings['alert_whatsapp_template'] = $data['alert_whatsapp_template'] ?: null;
        $business->update(['settings' => $settings]);

        return back()->with('status', __('Waiting-chat settings saved.'));
    }

    public function saveSmart(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        $settings = (array) $business->settings;
        $settings['auto_tag'] = $request->boolean('auto_tag');
        $settings['daily_email'] = $request->boolean('daily_email');
        $business->update(['settings' => $settings]);

        return back()->with('status', __('Saved.'));
    }

    public function testAlerts(Request $request, \App\Services\StaffAlerts $alerts): RedirectResponse
    {
        $result = $alerts->test($request->user()->business, $request->user());

        $parts = [$result['email'] ? __('An email was sent to :email.', ['email' => $request->user()->email]) : __('The email could not be sent.')];
        if ($result['whatsapp'] !== null) {
            $parts[] = $result['whatsapp'] === 'sent' ? __('A WhatsApp message was sent.') : __('WhatsApp failed: :why', ['why' => $result['whatsapp']]);
        }

        return back()->with('status', __('Test alert:').' '.implode(' ', $parts));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        $plans = app(Plans::class);
        if (! $plans->canAdd($request->user()->business, 'staff')) {
            return back()->withErrors(['email' => __('Your plan has reached its limit of team members. Upgrade under Plan & billing to add more.')]);
        }

        User::create($data + ['business_id' => $request->user()->business_id, 'active' => true, 'email_verified_at' => now()]);

        return back()->with('status', __('Team member added.'));
    }

    public function updateUser(Request $request, int $id): RedirectResponse
    {
        $user = User::query()->where('business_id', $request->user()->business_id)->findOrFail($id);
        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        // Keep at least one active owner, and never lock yourself out by accident.
        $demoting = $user->role === 'owner' && ($data['role'] !== 'owner' || ! $data['active']);
        if ($demoting && User::query()->where('business_id', $user->business_id)->where('role', 'owner')->where('active', true)->count() < 2) {
            return back()->withErrors(['role' => __('The business needs at least one active owner.')]);
        }

        $user->role = $data['role'];
        $user->active = (bool) $data['active'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('status', __('Team member saved.'));
    }
}
