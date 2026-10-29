<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Everyone's own account: name and password. */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.account', ['business' => $request->user()->business, 'active' => 'account']);
    }

    /** Each person chooses the language of the staff screens. */
    public function language(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:'.implode(',', array_keys(\App\Support\Lang::LOCALES))]]);
        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return back();
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
                return back()->withErrors(['current_password' => 'Your current password is not right.']);
            }
            $user->password = $data['password'];
        }

        $user->name = $data['name'];
        $user->save();

        return back()->with('status', empty($data['password']) ? 'Saved.' : 'Saved. Your password is changed.');
    }
}
