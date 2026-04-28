<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BusinessCardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $portfolio = is_array($user->portfolio ?? null) ? $user->portfolio : null;
        $slug = (string) ($portfolio['slug'] ?? '');

        $publicUrl = $slug !== '' ? url('/'.$slug) : null;
        $cardUrl = $slug !== '' ? url('/c/'.$slug) : null;

        return Inertia::render('BusinessCard/Show', [
            'slug' => $slug,
            'publicUrl' => $publicUrl,
            'businessCardUrl' => $cardUrl,
        ]);
    }

    /**
     * Ruta corta para tarjeta física: /c/{slug} -> /{slug}
     */
    public function redirect(Request $request, string $slug)
    {
        return redirect('/'.strtolower($slug), 301);
    }
}
