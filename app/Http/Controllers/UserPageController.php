<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\User;
use MongoDB\BSON\ObjectId;
use Illuminate\Support\Facades\URL;

class UserPageController extends Controller
{
    private function normalizeMediaPath(string $path): string
    {
        $path = trim($path);
        $path = ltrim($path, '/');

        // Guardamos siempre relativo al disk 'public'
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, strlen('storage/'));
        }

        return $path;
    }

    private function mediaIndexForUser(User $user): array
    {
        $portfolio = is_array($user->portfolio ?? null) ? $user->portfolio : [];
        $media = $portfolio['media'] ?? [];
        if (!is_array($media)) {
            return [];
        }

        // Normalizar y deduplicar
        $paths = collect($media)
            ->filter(fn ($m) => is_array($m) || is_string($m))
            ->map(function ($m) {
                if (is_string($m)) {
                    return ['path' => $this->normalizeMediaPath($m)];
                }
                $path = isset($m['path']) ? (string) $m['path'] : '';
                $path = $this->normalizeMediaPath($path);
                $createdAt = isset($m['created_at']) ? (string) $m['created_at'] : null;
                return [
                    'path' => $path,
                    'created_at' => $createdAt,
                ];
            })
            ->filter(fn ($m) => !empty($m['path']))
            ->unique('path')
            ->values()
            ->toArray();

        return $paths;
    }

    private function defaultPortfolioForUser(User $user): array
    {
        $name = trim(($user->name ?? '').' '.($user->ape_pat ?? '').' '.($user->ape_mat ?? ''));
        $name = $name !== '' ? $name : 'Mi Portafolio';

        return [
            'slug' => null,
            'config' => [
                'primary_color' => '#ff5733',
                'font' => 'Inter',
            ],
            // Estado del Page Builder (paquete @myissue/vue-website-page-builder)
            'builder_state' => [
                'components' => [],
            ],
        ];
    }

    public function edit(Request $request)
    {
        $user = $request->user();
        $portfolio = is_array($user->portfolio ?? null) ? $user->portfolio : null;
        if (! $portfolio) {
            $portfolio = $this->defaultPortfolioForUser($user);
        }

        $slug = (string) ($portfolio['slug'] ?? '');
        $publicUrl = $slug !== '' ? URL::to('/'.$slug) : null;
        $cardUrl = $slug !== '' ? URL::to('/c/'.$slug) : null;

        return Inertia::render('UserPage/Edit', [
            'portfolio' => $portfolio,
            'publicUrl' => $publicUrl,
            'businessCardUrl' => $cardUrl,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'portfolio' => ['required', 'array'],
            'portfolio.slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/i',
            ],
            'portfolio.config' => ['nullable', 'array'],
            'portfolio.config.primary_color' => ['nullable', 'string', 'max:20'],
            'portfolio.config.font' => ['nullable', 'string', 'max:80'],
            'portfolio.builder_state' => ['nullable', 'array'],
            'portfolio.builder_state.components' => ['nullable', 'array'],
        ]);

        $slug = strtolower(trim((string) ($data['portfolio']['slug'] ?? '')));
        $data['portfolio']['slug'] = $slug;

        // Preservar media indexado por uploads aunque el frontend no lo envíe
        $existingPortfolio = is_array($user->portfolio ?? null) ? $user->portfolio : [];
        if (!array_key_exists('media', $data['portfolio']) && array_key_exists('media', $existingPortfolio)) {
            $data['portfolio']['media'] = $existingPortfolio['media'];
        }

        // Validar unicidad del slug en Mongo (excluyendo al usuario actual)
        $exists = User::query()
            ->where('portfolio.slug', $slug)
            ->where('_id', '!=', new ObjectId($user->getKey()))
            ->exists();
        if ($exists) {
            return back()->withErrors([
                'portfolio.slug' => 'Ese slug ya está en uso.',
            ]);
        }

        $user->portfolio = $data['portfolio'];
        $user->save();

        return back();
    }

    /**
     * Sube imágenes para la landing y devuelve URL pública
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('image')->store('portfolio', 'public');
        // Construir URL pública sin depender de métodos dinámicos (mejor compatibilidad con analyzers)
        $url = URL::to('/storage/'.ltrim($path, '/'));

        // Indexar en portfolio.media para poder listar sin escanear el filesystem
        $user = $request->user();
        $portfolio = is_array($user->portfolio ?? null) ? $user->portfolio : $this->defaultPortfolioForUser($user);
        $media = $this->mediaIndexForUser($user);
        $media[] = [
            'path' => $this->normalizeMediaPath($path),
            'created_at' => now()->toIso8601String(),
        ];
        $portfolio['media'] = collect($media)->unique('path')->values()->toArray();
        $user->portfolio = $portfolio;
        $user->save();

        return response()->json([
            'path' => $path,
            'url' => $url,
        ], 201);
    }

    /**
     * Lista imágenes ya subidas por el usuario (para media library)
     */
    public function media(Request $request)
    {
        $user = $request->user();
        $media = $this->mediaIndexForUser($user);

        $items = collect($media)
            ->map(function ($m) {
                $path = (string) ($m['path'] ?? '');
                return [
                    'path' => $path,
                    'url' => URL::to('/storage/'.ltrim($path, '/')),
                    'created_at' => $m['created_at'] ?? null,
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * Render público por slug: encuentra al usuario por `portfolio.slug`
     */
    public function publicShow(Request $request, string $slug)
    {
        $requested = trim($slug);
        $normalized = strtolower($requested);

        // Primero intentar el slug normalizado (forma canónica)
        $user = User::query()->where('portfolio.slug', $normalized)->first();

        // Fallback (legacy): búsqueda case-insensitive para slugs guardados con otro casing
        if (! $user) {
            $pattern = '^'.preg_quote($requested, '/').'$';
            $user = User::query()
                ->whereRaw([
                    'portfolio.slug' => ['$regex' => $pattern, '$options' => 'i'],
                ])
                ->first();
        }

        // Fallback (legacy string): `portfolio` guardado como JSON string
        if (! $user) {
            $pattern = '"slug"\\s*:\\s*"'.preg_quote($requested, '/').'"';
            $user = User::query()
                ->whereRaw([
                    'portfolio' => ['$regex' => $pattern, '$options' => 'i'],
                ])
                ->first();
        }

        if (! $user) {
            return Inertia::render('PublicLanding/NotFound', [
                'slug' => $requested,
            ])->toResponse($request)->setStatusCode(404);
        }

        $portfolio = is_array($user->portfolio ?? null) ? $user->portfolio : null;
        if (! $portfolio) {
            return Inertia::render('PublicLanding/NotFound', [
                'slug' => $requested,
            ])->toResponse($request)->setStatusCode(404);
        }

        $storedSlugLower = strtolower((string) ($portfolio['slug'] ?? ''));
        if ($storedSlugLower !== '' && $storedSlugLower !== $normalized) {
            return redirect('/'.$storedSlugLower, 301);
        }

        return Inertia::render('PublicLanding/Show', [
            'portfolio' => $portfolio,
            'owner' => [
                'name' => (string) ($user->name ?? ''),
                'ape_pat' => (string) ($user->ape_pat ?? ''),
                'ape_mat' => (string) ($user->ape_mat ?? ''),
            ],
        ]);
    }
}
