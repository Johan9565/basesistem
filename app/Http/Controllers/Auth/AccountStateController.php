<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountStateController extends Controller
{
    /**
     * Usuario con status 0: solo puede ver este mensaje y cerrar sesión.
     */
    public function deactivated(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }
        if ((int) ($user->status ?? 1) !== 0) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/AccountDeactivated');
    }

    public function passwordRequiredCreate(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }
        if ((int) ($user->status ?? 1) === 0) {
            return redirect()->route('account.deactivated');
        }
        if (! $this->mustSetPassword($user)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/PasswordRequired');
    }

    public function passwordRequiredStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }
        if ((int) ($user->status ?? 1) === 0) {
            return redirect()->route('account.deactivated');
        }
        if (! $this->mustSetPassword($user)) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'active' => true,
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * @param  \App\Models\User  $user
     */
    private function mustSetPassword($user): bool
    {
        $active = $user->getAttribute('active');
        if ($active === null) {
            return false;
        }

        return $active === false || $active === 0 || $active === '0';
    }
}
