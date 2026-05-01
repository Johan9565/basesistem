<?php

namespace App\Support;

/**
 * Normaliza enlaces de notificación a { label, href }.
 *
 * Cada entrada puede ser:
 * - href: URL ya resuelta
 * - url: path o URL absoluta
 * - route + params: nombre de ruta Ziggy/Laravel y parámetros asociativos (p. ej. ['user' => $id])
 */
class NotificationLinkResolver
{
    /**
     * @param  array<int, array<string, mixed>|string>  $links
     * @return list<array{label: string, href: string}>
     */
    public static function resolve(array $links): array
    {
        $out = [];
        foreach ($links as $i => $link) {
            if (is_string($link)) {
                $link = ['url' => $link];
            }
            if (! is_array($link)) {
                continue;
            }
            $label = isset($link['label']) ? (string) $link['label'] : ('Enlace '.($i + 1));
            $href = self::resolveHref($link);
            if ($href !== null && $href !== '') {
                $out[] = ['label' => $label, 'href' => $href];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $link
     */
    private static function resolveHref(array $link): ?string
    {
        if (! empty($link['href'])) {
            return (string) $link['href'];
        }
        if (! empty($link['url'])) {
            return (string) $link['url'];
        }
        if (empty($link['route'])) {
            return null;
        }
        $params = $link['params'] ?? [];
        if ($params !== [] && ! is_array($params)) {
            return null;
        }
        /** @var array<string, mixed> $params */
        $params = is_array($params) ? $params : [];

        try {
            return route((string) $link['route'], $params);
        } catch (\Throwable) {
            return null;
        }
    }
}
