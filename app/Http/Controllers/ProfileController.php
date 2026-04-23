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
        /** @var \Illuminate\Filesystem\FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');
        if (! empty($user->profile_photo_path)) {
            $avatarUrl = $publicDisk->url($user->profile_photo_path);
        }
        if (! empty($user->profile_banner_path)) {
            $bannerUrl = $publicDisk->url($user->profile_banner_path);
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profileDisplay' => [
                'name' => $user->name ?? '',
                'ape_pat' => $user->ape_pat ?? '',
                'ape_mat' => $user->ape_mat ?? '',
                'email' => $user->email ?? '',
                'status' => ((int) ($user->status ?? 1)) === 1 ? 'Activo' : 'Inactivo',
                'avatar_url' => $avatarUrl,
                'banner_url' => $bannerUrl,
                'settings' => $user->settings ?? null,
            ],
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
}
