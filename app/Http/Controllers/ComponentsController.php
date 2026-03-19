<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ComponentThemeModel;

class ComponentsController extends Controller
{
    public function index()
    {
        $theme = ComponentThemeModel::first()?->styles ?? [];

        return Inertia::render('Components/Index', [
            'theme' => $theme,
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
            ComponentThemeModel::create(['styles' => $validated['styles']]);
        } else {
            $doc->update(['styles' => $validated['styles']]);
        }

        return back();
    }
}
