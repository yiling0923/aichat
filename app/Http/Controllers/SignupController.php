<?php

namespace App\Http\Controllers;

use App\Mail\VerifyEmailMail;
use App\Models\BotFlow;
use App\Models\Invite;
use App\Models\Business;
use App\Models\User;
use App\Setup\SetupApplier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Self-service sign-up: a new business, its owner, a free trial, and an email to confirm. */
class SignupController extends Controller
{
    public function show(Request $request): View
    {
        return view('signup.form', ['inviteOnly' => $this->inviteOnly(), 'invite' => $request->query('invite')]);
    }

    private function inviteOnly(): bool
    {
        return config('plans.signup_mode') === 'invite';
    }

    public function store(Request $request): RedirectResponse
    {
        // A hidden field only bots fill in: pretend it worked.
        if ($request->filled('website')) {
            return redirect()->route('verification.notice');
        }

        $invite = null;
        if ($this->inviteOnly()) {
            $code = Str::upper(trim((string) $request->input('invite')));
            $invite = $code === '' ? null : Invite::query()->where('code', $code)->whereNull('used_at')->first();

            if ($invite === null || ($invite->email !== null && $invite->email !== Str::lower((string) $request->input('email')))) {
                return back()->withErrors(['invite' => __('This sign-up is by invitation. Check your invitation code and use the email it was made for.')])->withInput();
            }
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Please accept the terms and the privacy notice.']);

        [$business, $user] = app(\App\Services\BusinessProvisioner::class)->create($data['business_name'], $data['name'], $data['email'], $data['password']);
        $invite?->forceFill(['used_at' => now(), 'business_id' => $business->id])->save();

        Mail::to($user->email)->send(new VerifyEmailMail($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()?->email_verified_at !== null) {
            return redirect()->route('setup');
        }

        return view('signup.verify', ['email' => $request->user()?->email]);
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'This link has expired. Sign in and ask for a new one.');

        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);

        return redirect()->route('setup')->with('status', __('Email confirmed. Welcome!'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->email_verified_at === null) {
            Mail::to($user->email)->send(new VerifyEmailMail($user));
        }

        return back()->with('status', __('We sent the email again.'));
    }
}
