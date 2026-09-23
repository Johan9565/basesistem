<?php

namespace App\Services\Whatsapp\Contracts;

interface LlmChatClient
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>|null  $tools  Si se pasa, reemplaza las tools por defecto del cliente.
     * @return array<string, mixed>
     */
    public function chat(array $messages, bool $withTools = true, ?array $tools = null): array;
}
