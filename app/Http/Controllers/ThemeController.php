<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ComponentThemeModel;

class ThemeController extends Controller
{
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
