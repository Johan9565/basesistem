<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;

class BusinessCardController extends Controller
{
    private function defaultPortfolioForUser(User $user): array
    {
        $name = trim(($user->name ?? '') . ' ' . ($user->ape_pat ?? '') . ' ' . ($user->ape_mat ?? ''));
        $name = $name !== '' ? $name : 'Mi Portafolio';

        return [
            'slug' => null,
            'config' => [
                'primary_color' => '#ff5733',
                'font' => 'Inter',
            ],
            'builder_state' => [
                'components' => [],
            ],
            'business_card_builder_state' => [
                'components' => [],
            ]
        ];
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $portfolio = is_array($user->portfolio ?? null) ? $user->portfolio : null;
        if (! $portfolio) {
            $portfolio = $this->defaultPortfolioForUser($user);
        }

        $slug = (string) ($portfolio['slug'] ?? '');

        $publicUrl = $slug !== '' ? url('/'.$slug) : null;
        $cardUrl = $slug !== '' ? url('/c/'.$slug) : null;

        return Inertia::render('BusinessCard/Show', [
            'slug' => $slug,
            'publicUrl' => $publicUrl,
            'businessCardUrl' => $cardUrl,
            'portfolio' => $portfolio,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'portfolio' => ['required', 'array'],
            'portfolio.business_card_builder_state' => ['nullable', 'array'],
            'portfolio.business_card_builder_state.components' => ['nullable', 'array'],
        ]);

        $portfolio = is_array($user->portfolio ?? null) ? $user->portfolio : [];
        $portfolio['business_card_builder_state'] = $data['portfolio']['business_card_builder_state'] ?? ['components' => []];

        $user->portfolio = $portfolio;
        $user->save();

        return back();
    }

    /**
     * Ruta corta para tarjeta física: /c/{slug} -> /{slug}
     */
    public function redirect(Request $request, string $slug)
    {
        return redirect('/'.strtolower($slug), 301);
    }
}
