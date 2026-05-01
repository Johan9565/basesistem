<?php

namespace App\Http\Controllers;

use App\Models\NotificationsModel;
use App\Support\NotificationLinkResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class NotificationsController extends Controller
{
    /**
     * Listado paginado del mes en curso (desde inicio de mes hasta ahora).
     */
    public function feed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'filter' => ['nullable', 'in:all,unread'],
        ]);

        $offset = (int) ($validated['offset'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 25);
        $filter = $validated['filter'] ?? 'all';

        $userId = (string) $request->user()->getKey();

        $base = NotificationsModel::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('created_at', '<=', now());

        if ($filter === 'unread') {
            $base->where('is_read', false);
        }

        $total = (int) $base->count();

        $rows = NotificationsModel::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('created_at', '<=', now())
            ->when($filter === 'unread', fn ($q) => $q->where('is_read', false))
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'total' => $total,
            'offset' => $offset,
            'items' => $rows->map(fn (NotificationsModel $n) => $this->serializeNotification($n)),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $userId = (string) $request->user()->getKey();

        try {
            $id = new ObjectId($notification);
        } catch (\Throwable $e) {
            abort(404);
        }

        $doc = NotificationsModel::query()
            ->where('user_id', $userId)
            ->where('_id', $id)
            ->firstOrFail();

        $doc->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->getKey();

        $updated = NotificationsModel::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('created_at', '<=', now())
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(NotificationsModel $n): array
    {
        return [
            'id' => (string) $n->getKey(),
            'message' => (string) ($n->message ?? ''),
            'item_ids' => $this->normalizeItemIdsFromModel($n),
            'is_read' => (bool) ($n->is_read ?? false),
            'read_at' => $n->read_at?->toIso8601String(),
            'links' => $this->normalizeLinksFromModel($n),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeItemIdsFromModel(NotificationsModel $n): array
    {
        $out = [];
        $raw = $n->item_ids ?? null;
        if (is_array($raw)) {
            foreach ($raw as $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $s = is_scalar($v) ? (string) $v : '';
                if ($s !== '' && ! in_array($s, $out, true)) {
                    $out[] = $s;
                }
            }
        }
        $legacy = $n->item_id ?? null;
        if ($legacy !== null && $legacy !== '') {
            if (is_array($legacy)) {
                foreach ($legacy as $v) {
                    $s = is_scalar($v) ? (string) $v : '';
                    if ($s !== '' && ! in_array($s, $out, true)) {
                        $out[] = $s;
                    }
                }
            } else {
                $s = (string) $legacy;
                if ($s !== '' && ! in_array($s, $out, true)) {
                    array_unshift($out, $s);
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function normalizeLinksFromModel(NotificationsModel $n): array
    {
        $links = $n->links ?? null;
        if (is_array($links) && $links !== []) {
            $resolved = NotificationLinkResolver::resolve($links);
            if ($resolved !== []) {
                return $resolved;
            }
            $manual = [];
            foreach ($links as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $href = (string) ($row['href'] ?? $row['url'] ?? '');
                if ($href === '') {
                    continue;
                }
                $manual[] = [
                    'label' => (string) ($row['label'] ?? ('Enlace '.($i + 1))),
                    'href' => $href,
                ];
            }

            return $manual;
        }

        $legacyHrefs = $this->normalizeRoutes($n);

        return array_map(
            fn (string $href, int $i) => [
                'label' => 'Enlace '.($i + 1),
                'href' => $href,
            ],
            $legacyHrefs,
            array_keys($legacyHrefs)
        );
    }

    private function normalizeRoutes(NotificationsModel $n): array
    {
        $routes = $n->routes ?? null;
        if (is_array($routes) && $routes !== []) {
            return array_values(array_filter(array_map('strval', $routes)));
        }
        $legacy = $n->route ?? null;
        if ($legacy === null || $legacy === '') {
            return [];
        }

        return is_array($legacy)
            ? array_values(array_filter(array_map('strval', $legacy)))
            : [(string) $legacy];
    }
}
