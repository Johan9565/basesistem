<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role_data()->first();

        $avatarUrl = null;
        $bannerUrl = null;
        if (! empty($user->profile_photo_path)) {
            $avatarUrl = Storage::disk('public')->url($user->profile_photo_path);
        }
        if (! empty($user->profile_banner_path)) {
            $bannerUrl = Storage::disk('public')->url($user->profile_banner_path);
        }

        $tokens = $user->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                $lastUsed = null;
                if (! empty($token->last_used_at)) {
                    $lastUsed = $token->last_used_at instanceof \Carbon\CarbonInterface
                        ? $token->last_used_at->locale('es')->diffForHumans()
                        : \Carbon\Carbon::parse($token->last_used_at)->locale('es')->diffForHumans();
                }

                $createdAt = null;
                if (! empty($token->created_at)) {
                    $createdAt = $token->created_at instanceof \Carbon\CarbonInterface
                        ? $token->created_at->locale('es')->isoFormat('D MMM YYYY, HH:mm')
                        : \Carbon\Carbon::parse($token->created_at)->locale('es')->isoFormat('D MMM YYYY, HH:mm');
                }

                $expiresAtFormatted = 'Nunca';
                $isExpired = false;
                if (! empty($token->expires_at)) {
                    $expCarbon = $token->expires_at instanceof \Carbon\CarbonInterface
                        ? $token->expires_at
                        : \Carbon\Carbon::parse($token->expires_at);
                    $expiresAtFormatted = $expCarbon->locale('es')->isoFormat('D MMM YYYY, HH:mm');
                    $isExpired = $expCarbon->isPast();
                }

                return [
                    'id' => (string) $token->_id,
                    'name' => $token->name ?? 'Token',
                    'abilities' => $token->abilities ?? ['*'],
                    'last_used_at' => $lastUsed ?? 'Nunca',
                    'created_at' => $createdAt,
                    'expires_at' => $expiresAtFormatted,
                    'is_expired' => $isExpired,
                ];
            });

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profileDisplay' => [
                'name' => $user->name ?? '',
                'ape_pat' => $user->ape_pat ?? '',
                'ape_mat' => $user->ape_mat ?? '',
                'email' => $user->email ?? '',
                'role' => $role ? $role->role : '—',
                'status' => ((int) ($user->status ?? 1)) === 1 ? 'Activo' : 'Inactivo',
                'avatar_url' => $avatarUrl,
                'banner_url' => $bannerUrl,
            ],
            'tokens' => $tokens,
            'newToken' => session('newToken'),
        ]);
    }

    /**
     * Store profile photo (public disk).
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();
        if (! empty($user->profile_photo_path)) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $request->file('avatar')->store('profile-photos', 'public');
        $user->profile_photo_path = $path;
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Foto de perfil actualizada.');
    }

    /**
     * Store profile banner (public disk).
     */
    public function updateBanner(Request $request): RedirectResponse
    {
        $request->validate([
            'banner' => ['required', 'image', 'max:10240'],
        ]);

        $user = $request->user();
        if (! empty($user->profile_banner_path)) {
            Storage::disk('public')->delete($user->profile_banner_path);
        }

        $path = $request->file('banner')->store('profile-banners', 'public');
        $user->profile_banner_path = $path;
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Portada actualizada.');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Generate a new personal access token for the user.
     */
    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expiration' => ['nullable', 'string', 'in:never,7,30,60,90,365'],
        ], [
            'name.required' => 'El nombre del token es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
        ]);

        $expiresAt = match ($validated['expiration'] ?? 'never') {
            '7' => now()->addDays(7),
            '30' => now()->addDays(30),
            '60' => now()->addDays(60),
            '90' => now()->addDays(90),
            '365' => now()->addYear(),
            default => null,
        };

        $user = $request->user();
        $token = $user->createToken(
            name: $validated['name'],
            abilities: ['*'],
            expiresAt: $expiresAt,
        );

        return Redirect::route('profile.edit')->with([
            'newToken' => [
                'name' => $token->accessToken->name,
                'plainTextToken' => $token->plainTextToken,
                'expires_at' => $expiresAt ? $expiresAt->locale('es')->isoFormat('D MMM YYYY, HH:mm') : 'Nunca',
            ],
            'status' => 'Token de API generado exitosamente.',
        ]);
    }

    /**
     * Revoke a specific personal access token.
     */
    public function destroyToken(Request $request, string $id): RedirectResponse
    {
        $request->user()->tokens()->where('_id', $id)->delete();

        return Redirect::route('profile.edit')->with('status', 'Token de API revocado exitosamente.');
    }

    /**
     * Revoke all personal access tokens for the user.
     */
    public function destroyAllTokens(Request $request): RedirectResponse
    {
        $request->user()->tokens()->delete();

        return Redirect::route('profile.edit')->with('status', 'Todos los tokens de API han sido revocados.');
    }
}

