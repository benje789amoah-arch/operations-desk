<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Services\AuditLogger;
use App\Support\Ability;
use App\Support\OpsClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'abilities' => collect(Ability::matrix())->filter(fn ($ability, $key) => $user->can($key)),
            'recentUpdates' => $user->activityUpdates()->with('instance.definition')->latest('id')->limit(8)->get(),
            // The month starts at local midnight in the operational timezone.
            'updatesThisMonth' => $user->activityUpdates()->where('created_at', '>=', OpsClock::today()->startOfMonth()->utc())->count(),
        ]);
    }

    public function editPassword(): View
    {
        return view('profile.password');
    }

    /**
     * Changing your password signs out your other sessions.
     */
    public function updatePassword(UpdatePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'password_changed_at' => now(),
        ])->save();

        Auth::logoutOtherDevices($request->validated('password'));
        $request->session()->regenerate();

        $audit->record('auth.password_changed', 'Changed own password', $user, $user->email);

        return redirect()->route('profile.show')->with('toast', 'Password updated. Other sessions have been signed out.');
    }
}
