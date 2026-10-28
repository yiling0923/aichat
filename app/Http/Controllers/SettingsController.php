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
            'timezones' => ['Asia/Kuala_Lumpur', 'Asia/Singapore', 'Asia/Hong_Kong', 'Asia/Shanghai', 'Asia/Bangkok', 'Asia/Jakarta', 'Australia/Sydney', 'Europe/London', 'America/New_York', 'UTC'],
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $business = $request->user()->business;
        $settings = (array) $business->settings;
        $settings['accent'] = $data['accent'] ?? null;
        $business->update(['name' => $data['name'], 'timezone' => $data['timezone'], 'settings' => $settings]);

        return back()->with('status', 'Settings saved.');
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
            return back()->withErrors(['email' => 'Your plan has reached its limit of team members. Upgrade under Plan & billing to add more.']);
        }

        User::create($data + ['business_id' => $request->user()->business_id, 'active' => true, 'email_verified_at' => now()]);

        return back()->with('status', 'Team member added.');
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
            return back()->withErrors(['role' => 'The business needs at least one active owner.']);
        }

        $user->role = $data['role'];
        $user->active = (bool) $data['active'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('status', 'Team member saved.');
    }
}
