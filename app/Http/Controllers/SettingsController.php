<?php

namespace App\Http\Controllers;

use App\Billing\Plans;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
            'currencyGroups' => \App\Support\Currencies::GROUPS,
            'currencies' => \App\Support\Currencies::all(),
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
            'currency' => ['nullable', 'string', 'max:8'],      // up to 6 for a real currency, and "__other" for the type-your-own choice
            'currency_custom' => ['nullable', 'string', 'max:12'],
        ]);

        $business = $request->user()->business;
        $settings = (array) $business->settings;
        $settings['accent'] = $data['accent'] ?? null;
        if (array_key_exists('address', $data)) {
            $settings['address'] = $data['address'];
        }
        if (! empty($data['currency'])) {
            // "Other" means the owner typed their own; anything else must be a listed currency or the one already saved.
            $picked = $data['currency'] === '__other' ? \App\Support\Currencies::clean($data['currency_custom'] ?? null) : (\App\Support\Currencies::isListed($data['currency']) || $data['currency'] === ($settings['currency'] ?? null) ? $data['currency'] : null);

            if ($picked === null) {
                return back()->withErrors(['currency_custom' => __('Type a short currency, up to 6 letters or signs, for example "BND" or "Fr".')])->withInput();
            }
            $settings['currency'] = $picked;
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
        $settings['alert_new_chat'] = $request->boolean('alert_new_chat');
        $settings['away_notice'] = $request->boolean('away_notice');
        $settings['auto_assign'] = $request->boolean('auto_assign');
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
            'custom' => ['nullable', 'boolean'],
            'areas' => ['nullable', 'array'],
        ]);

        $plans = app(Plans::class);
        if (! $plans->canAdd($request->user()->business, 'staff')) {
            return back()->withErrors(['email' => __('Your plan has reached its limit of team members. Upgrade under Plan & billing to add more.')]);
        }

        unset($data['custom'], $data['areas']);
        // The owner vouches for this person, so their email counts as confirmed (it is not a fillable field, so it is set directly).
        $member = new User($data + ['business_id' => $request->user()->business_id, 'active' => true, 'permissions' => $this->chosenAreas($request, $data['role'])]);
        $member->email_verified_at = now();
        $member->save();

        return back()->with('status', __('Team member added.'));
    }

    /** What the owner ticked for this person: a list of areas, or null for the role's usual access (owners always have everything). */
    private function chosenAreas(Request $request, string $role): ?array
    {
        if ($role === 'owner' || ! $request->boolean('custom')) {
            return null;
        }

        return \App\Support\Access::clean((array) $request->input('areas', []));
    }

    public function updateUser(Request $request, int $id): RedirectResponse
    {
        $user = User::query()->where('business_id', $request->user()->business_id)->findOrFail($id);
        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'custom' => ['nullable', 'boolean'],
            'areas' => ['nullable', 'array'],
        ]);

        // Keep at least one active owner, and never lock yourself out by accident.
        $demoting = $user->role === 'owner' && ($data['role'] !== 'owner' || ! $data['active']);
        if ($demoting && User::query()->where('business_id', $user->business_id)->where('role', 'owner')->where('active', true)->count() < 2) {
            return back()->withErrors(['role' => __('The business needs at least one active owner.')]);
        }

        $user->role = $data['role'];
        $user->permissions = $this->chosenAreas($request, $data['role']);
        $user->active = (bool) $data['active'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('status', __('Team member saved.'));
    }

    /** Why this person cannot be switched on or off right now, or null when it is fine. */
    private function activeProblem(User $me, User $user, bool $on): ?string
    {
        if ($user->active === $on) {
            return null;
        }

        if (! $on) {
            if ($user->id === $me->id) {
                return __('You cannot switch yourself off.');
            }
            if ($user->role === 'owner' && User::query()->where('business_id', $user->business_id)->where('role', 'owner')->where('active', true)->count() < 2) {
                return __('The business needs at least one active owner.');
            }

            return null;
        }

        return app(Plans::class)->canAdd($user->business, 'staff') ? null : __('Your plan has reached its limit of team members. Upgrade under Plan & billing to add more.');
    }

    /** The On/Off switch in the team list. */
    public function toggleUser(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $user = User::query()->where('business_id', $request->user()->business_id)->findOrFail($id);
        $on = $request->boolean('active');

        if (($problem = $this->activeProblem($request->user(), $user, $on)) !== null) {
            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $problem], 422) : back()->withErrors(['role' => $problem]);
        }

        $user->active = $on;
        $user->save();

        return $request->expectsJson() ? response()->json(['ok' => true, 'active' => $user->active]) : back()->with('status', __('Team member saved.'));
    }

    /** The key button: set a new password for someone without opening the full editor. */
    public function setPassword(Request $request, int $id): RedirectResponse
    {
        $user = User::query()->where('business_id', $request->user()->business_id)->findOrFail($id);
        $data = $request->validate(['new_password' => ['required', 'string', 'min:8', 'max:100']]);

        $user->password = $data['new_password'];
        $user->save();

        return back()->with('status', __('Password changed for :name.', ['name' => $user->name]));
    }

    /** Change several people at once: their role, switch them on or off, or return them to the role's usual access. */
    public function bulkUsers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['role', 'activate', 'deactivate', 'reset_access'])],
            'role' => ['nullable', Rule::in(self::ROLES)],
        ]);

        if ($data['action'] === 'role' && empty($data['role'])) {
            return back()->withErrors(['bulk' => __('Choose a role first.')]);
        }

        $me = $request->user();
        $people = User::query()->where('business_id', $me->business_id)->whereIn('id', $data['ids'])->get();
        $done = 0;
        $skipped = [];

        foreach ($people as $user) {
            if ($data['action'] === 'role') {
                if ($user->id === $me->id) {
                    $skipped[] = __('you cannot change your own role');
                    continue;
                }
                if ($user->role === 'owner' && $data['role'] !== 'owner' && User::query()->where('business_id', $user->business_id)->where('role', 'owner')->where('active', true)->count() < 2) {
                    $skipped[] = __('the business needs at least one active owner');
                    continue;
                }
                $user->role = $data['role'];
                $user->permissions = $data['role'] === 'owner' ? null : $user->permissions;
            } elseif ($data['action'] === 'reset_access') {
                $user->permissions = null;
            } else {
                $on = $data['action'] === 'activate';
                if (($problem = $this->activeProblem($me, $user, $on)) !== null) {
                    $skipped[] = mb_strtolower($problem);
                    continue;
                }
                $user->active = $on;
            }

            $user->save();
            $done++;
        }

        $note = $skipped === [] ? '' : ' '.__(':n skipped (:why).', ['n' => count($skipped), 'why' => implode('; ', array_unique($skipped))]);

        return back()->with('status', __(':n updated.', ['n' => $done]).$note);
    }
}
