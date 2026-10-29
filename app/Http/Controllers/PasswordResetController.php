<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Forgot password: an email with a one-hour link. The answer never reveals whether an email has an account. */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($data);

        return back()->with('status', __('If that email has an account, we sent a link to choose a new password.'));
    }

    public function form(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        $status = Password::reset($data, function ($user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', __('Password changed. Sign in with the new one.'))
            : back()->withErrors(['email' => __('This link is not valid any more. Ask for a new one.')])->withInput($request->only('email'));
    }
}
