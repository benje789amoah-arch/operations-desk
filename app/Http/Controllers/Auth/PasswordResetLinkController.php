<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always responds the same way whether or not the address exists, so the
     * form cannot be used to discover which emails have accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $user = User::query()->where('email', $request->input('email'))->first();

        if ($user?->is_active) {
            Password::broker()->sendResetLink($request->only('email'));
        }

        return back()->with('status', 'If an active account uses that address, a reset link is on its way. The link expires in '.config('auth.passwords.users.expire').' minutes.');
    }
}
