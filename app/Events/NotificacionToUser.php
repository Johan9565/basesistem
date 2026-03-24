<?php

namespace App\Events;

use App\Support\NotificationLinkResolver;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificacionToUser implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string|int|float>|null  $itemIds  IDs relacionados (cada URL puede tener su contexto; se envían todos)
     * @param  list<array<string, mixed>|string>|null  $links  Definiciones: string URL, o array con href|url|route+params+label
     * @param  list<string>|null  $urls  Compatibilidad: solo URLs sin etiqueta
     */
    public function __construct(
        public string $message,
        public string $userId,
        public ?string $itemId = null,
        public ?array $itemIds = null,
        public ?string $url = null,
        public ?array $urls = null,
        public ?array $links = null,
        public ?array $currentPaths = null,
        public ?array $meta = null,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications_create_office.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notificacion.to.user';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $resolvedLinks = $this->resolvedLinkObjects();
        $resolvedHrefs = array_column($resolvedLinks, 'href');

        return [
            'message' => $this->message,
            'itemId' => $this->itemId,
            'itemIds' => $this->normalizedItemIds(),
            'url' => $resolvedHrefs[0] ?? null,
            'urls' => $resolvedHrefs,
            'links' => $resolvedLinks,
            'currentPaths' => $this->currentPaths ?? [],
            'meta' => array_merge($this->meta ?? [], [
                'recipientId' => $this->userId,
            ]),
        ];
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function resolvedLinkObjects(): array
    {
        $raw = $this->links ?? [];
        if ($raw !== []) {
            return NotificationLinkResolver::resolve($raw);
        }

        $fromUrls = $this->urls ?? [];
        $fromUrls = array_values(array_filter(array_map('strval', $fromUrls), fn (string $u) => $u !== ''));
        if ($fromUrls !== []) {
            return NotificationLinkResolver::resolve($fromUrls);
        }

        if ($this->url !== null && $this->url !== '') {
            return NotificationLinkResolver::resolve([(string) $this->url]);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function normalizedItemIds(): array
    {
        $ids = $this->itemIds ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }
        $out = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $s = is_scalar($id) ? (string) $id : '';
            if ($s !== '' && ! in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        if ($this->itemId !== null && $this->itemId !== '' && ! in_array((string) $this->itemId, $out, true)) {
            array_unshift($out, (string) $this->itemId);
        }

        return $out;
    }
}
