<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ComponentThemeModel;

class ComponentsController extends Controller
{
    public function index()
    {
        $doc = ComponentThemeModel::first();
        $theme = $doc?->styles ?? [];
        $activeTheme = $doc?->active_theme ?? 'dark';

        return Inertia::render('Components/Index', [
            'theme' => $theme,
            'activeTheme' => $activeTheme,
        ]);
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'styles' => 'required|array',
            'styles.*' => 'nullable|string',
        ]);

        $doc = ComponentThemeModel::first();
        if (!$doc) {
            ComponentThemeModel::create([
                'styles' => $validated['styles'],
                'active_theme' => 'custom',
            ]);
        } else {
            $doc->update([
                'styles' => $validated['styles'],
                'active_theme' => 'custom',
            ]);
        }

        return back();
    }

    public function updateActiveTheme(Request $request)
    {
        $validated = $request->validate([
            'active_theme' => 'required|string|max:50',
        ]);

        $doc = ComponentThemeModel::first();
        if (!$doc) {
            ComponentThemeModel::create([
                'styles' => [],
                'active_theme' => $validated['active_theme'],
            ]);
        } else {
            $doc->update(['active_theme' => $validated['active_theme']]);
        }

        return back();
    }
}
