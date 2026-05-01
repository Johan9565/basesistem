<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DependenciesModel;
use Inertia\Inertia;
use MongoDB\BSON\ObjectId;


class DependenciesController extends Controller
{
    public function index()
    {
        $nodes = DependenciesModel::query()
            ->get()
            ->map(function ($d) {
                return [
                    // MongoDB\Laravel suele exponer el ObjectId como `id` (y el campo real como `_id`).
                    // Convertimos a string para que Vue/Inertia lo pueda usar como `key`.
                    'id' => (string) ($d->id ?? $d->_id ?? ''),
                    'name' => (string) ($d->name ?? ''),
                    'status' => (int) ($d->status ?? 0),
                    'parent_id' => ($d->parent_id ?? null) !== null && ($d->parent_id ?? null) !== '' ? (string) $d->parent_id : null,
                ];
            })
            ->filter(fn ($n) => !empty($n['id']))
            ->values()
            ->toArray();

        // Agrupar por parent_id para construir un árbol recursivo (dependencies con `children`).
        $ROOT_KEY = '__root__';
        $byParent = [];
        foreach ($nodes as $n) {
            $key = $n['parent_id'] ?? $ROOT_KEY;
            $byParent[$key][] = $n;
        }

        $buildTree = function ($parentKey) use (&$buildTree, &$byParent) {
            $items = $byParent[$parentKey] ?? [];

            // Orden estable para que la UI no “salte” entre renders.
            usort($items, function ($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });

            foreach ($items as &$item) {
                $item['children'] = $buildTree($item['id']);
            }

            return $items;
        };

        $dependencies = $buildTree($ROOT_KEY);

        return Inertia::render('Dependencies/Index', [
            'dependencies' => $dependencies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
            'parent_id' => 'nullable|string',
        ]);

        $parentId = isset($validated['parent_id']) && $validated['parent_id'] !== '' ? (string) $validated['parent_id'] : null;

        DependenciesModel::create([
            'name' => $validated['name'],
            'status' => (int) $validated['status'],
            'parent_id' => $parentId,
        ]);

        return redirect()->route('dependencies');
    }

    public function update(Request $request, string $dependency)
    {
        $dependencyModel = DependenciesModel::findOrFail($this->normalizeId($dependency));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
            'parent_id' => 'nullable|string',
        ]);

        $parentId = isset($validated['parent_id']) && $validated['parent_id'] !== '' ? (string) $validated['parent_id'] : null;

        $dependencyModel->update([
            'name' => $validated['name'],
            'status' => (int) $validated['status'],
            'parent_id' => $parentId,
        ]);

        return redirect()->route('dependencies');
    }

    public function destroy(string $dependency)
    {
        // Borramos el nodo y todo su subárbol para evitar huérfanos.
        $dependencyModel = DependenciesModel::findOrFail($this->normalizeId($dependency));
        $dependencyId = (string) ($dependencyModel->id ?? $dependencyModel->_id ?? $dependency);

        $toDelete = array_merge([$dependencyId], $this->collectDescendantsIds($dependencyId));
        $toDelete = array_values(array_unique($toDelete));

        foreach ($toDelete as $id) {
            DependenciesModel::where('_id', $this->normalizeId($id))->delete();
        }

        return redirect()->route('dependencies');
    }

    private function looksLikeObjectId(string $id): bool
    {
        return strlen($id) === 24 && ctype_xdigit($id);
    }

    /**
     * MongoDB\Laravel normalmente espera un ObjectId real para `_id`.
     * Para tu `parent_id` (clave foránea) guardas string, pero para `_id` sí conviene normalizar.
     */
    private function normalizeId(string $id): mixed
    {
        return $this->looksLikeObjectId($id) ? new ObjectId($id) : $id;
    }

    private function collectDescendantsIds(string $parentId): array
    {
        $ids = [];
        $queue = [$parentId];
        $seen = [$parentId => true];

        while (!empty($queue)) {
            $current = array_shift($queue);

            // `parent_id` está guardado como string (hex), NO como ObjectId.
            $children = DependenciesModel::where('parent_id', $current)->get();

            foreach ($children as $child) {
                $childId = (string) ($child->id ?? $child->_id ?? '');
                if ($childId === '' || isset($seen[$childId])) continue;

                $seen[$childId] = true;
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }
}
