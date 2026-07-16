<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ComponentThemeModel;
use App\Support\LandingPalette;

class ComponentsController extends Controller
{
    private function normalizeBrandingValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Guardar rutas de storage como path relativo (branding/archivo.ext)
        $path = parse_url($value, PHP_URL_PATH);
        if (is_string($path) && strpos($path, '/storage/') === 0) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        if (strpos($value, '/storage/') === 0) {
            return ltrim(substr($value, strlen('/storage/')), '/');
        }

        // Si no es storage, mantenerlo como fue ingresado (URL externa o ruta custom)
        return $value;
    }

    public function index()
    {
        $doc = ComponentThemeModel::first();
        $theme = $doc?->styles ?? [];
        $activeTheme = $doc?->active_theme ?? 'dark';

        return Inertia::render('Components/Index', [
            'theme' => $theme,
            'activeTheme' => $activeTheme,
            'landingPalette' => LandingPalette::resolve($doc?->landing_palette),
            'landingPalettePreset' => $doc?->landing_palette_preset ?? 'azul',
            'landingPresets' => collect(LandingPalette::presets())
                ->map(fn ($preset, $id) => [
                    'id' => $id,
                    'label' => $preset['label'],
                    'colors' => $preset['colors'],
                ])
                ->values()
                ->all(),
            'branding' => [
                'logo_url' => $doc?->logo_url,
                'auth_side_image_url' => $doc?->auth_side_image_url,
                'auth_side_image_pos_x' => (float) ($doc?->auth_side_image_pos_x ?? 50),
                'auth_side_image_pos_y' => (float) ($doc?->auth_side_image_pos_y ?? 50),
            ],
        ]);
    }

    public function updateLandingPalette(Request $request)
    {
        $validated = $request->validate([
            'landing_palette' => 'required|array',
            'landing_palette.*' => 'nullable|string|max:32',
            'landing_palette_preset' => 'nullable|string|max:50',
        ]);

        $palette = LandingPalette::resolve($validated['landing_palette']);
        $preset = $validated['landing_palette_preset'] ?? 'custom';

        $doc = ComponentThemeModel::first();
        if (! $doc) {
            ComponentThemeModel::create([
                'styles' => [],
                'active_theme' => 'dark',
                'landing_palette' => $palette,
                'landing_palette_preset' => $preset,
            ]);
        } else {
            $doc->update([
                'landing_palette' => $palette,
                'landing_palette_preset' => $preset,
            ]);
        }

        return back();
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

    public function updateBranding(Request $request)
    {
        $validated = $request->validate([
            'logo_url' => 'nullable|string|max:2048',
            'auth_side_image_url' => 'nullable|string|max:2048',
            'auth_side_image_pos_x' => 'nullable|numeric|min:0|max:100',
            'auth_side_image_pos_y' => 'nullable|numeric|min:0|max:100',
        ]);

        $doc = ComponentThemeModel::first();
        $payload = [
            'logo_url' => $this->normalizeBrandingValue($validated['logo_url'] ?? null),
            'auth_side_image_url' => $this->normalizeBrandingValue($validated['auth_side_image_url'] ?? null),
            'auth_side_image_pos_x' => isset($validated['auth_side_image_pos_x']) ? (float) $validated['auth_side_image_pos_x'] : 50.0,
            'auth_side_image_pos_y' => isset($validated['auth_side_image_pos_y']) ? (float) $validated['auth_side_image_pos_y'] : 50.0,
        ];

        if (!$doc) {
            ComponentThemeModel::create([
                'styles' => [],
                'active_theme' => 'dark',
                ...$payload,
            ]);
        } else {
            $doc->update($payload);
        }

        return redirect()->route('components');
    }

    public function uploadBrandingAsset(Request $request)
    {
        $validated = $request->validate([
            'asset' => 'required|in:logo,auth_side',
            'upload' => 'required|image|max:5120',
        ]);

        $path = $request->file('upload')->store('branding', 'public');

        $field = $validated['asset'] === 'logo' ? 'logo_url' : 'auth_side_image_url';

        $doc = ComponentThemeModel::first();
        if (!$doc) {
            ComponentThemeModel::create([
                'styles' => [],
                'active_theme' => 'dark',
                'auth_side_image_pos_x' => 50.0,
                'auth_side_image_pos_y' => 50.0,
                $field => $path,
            ]);
        } else {
            $doc->update([$field => $path]);
        }

        return redirect()->route('components');
    }
}
