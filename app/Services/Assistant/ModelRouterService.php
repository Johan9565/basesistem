<?php

namespace App\Services\Assistant;

use App\Models\AssistantAiModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModelRouterService
{
    const CACHE_PREFIX = 'assistant:model_router:';
    const CACHE_TTL_SECONDS = 3600;

    /**
     * Invalidate all model router cache entries for hot-reloading without service restarts.
     */
    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'write_intent');
        Cache::forget(self::CACHE_PREFIX . 'read_intent');
        Cache::forget(self::CACHE_PREFIX . 'reasoning_fallback');
        Cache::forget(self::CACHE_PREFIX . 'all_models');
    }

    /**
     * Returns the active AssistantAiModel instance for a specific role.
     */
    public function getActiveModel(string $role): ?AssistantAiModel
    {
        $cacheKey = self::CACHE_PREFIX . $role;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($role) {
            // Find active model configured strictly for this role
            $model = AssistantAiModel::where('role', $role)
                ->where('status', 'active')
                ->first();

            if (!$model && $role !== 'write_intent') {
                // Standby / fallback to write_intent active model if read model not defined
                $model = AssistantAiModel::where('role', 'write_intent')
                    ->where('status', 'active')
                    ->first();
            }

            return $model;
        });
    }

    /**
     * Resolves active configuration for an LLM provider with seamless .env fallback.
     *
     * @param string $role 'write_intent' | 'read_intent' | 'reasoning_fallback'
     * @return array
     */
    public function getActiveModelConfig(string $role = 'write_intent'): array
    {
        $model = $this->getActiveModel($role);

        if ($model) {
            $baseUrl = rtrim($model->base_url ?? 'https://api.deepseek.com', '/');
            return [
                'id'          => (string) ($model->_id ?? $model->id),
                'alias'       => $model->alias,
                'role'        => $model->role,
                'base_url'    => $baseUrl,
                'api_key'     => $model->api_key,
                'model'       => $model->model,
                'temperature' => (float) ($model->temperature ?? ($role === 'write_intent' ? 0.2 : 0.6)),
                'max_tokens'  => $model->max_tokens ? (int) $model->max_tokens : null,
                'timeout_ms'  => (int) ($model->timeout_ms ?? 30000),
                'headers'     => (array) ($model->headers ?? []),
                'source'      => 'database',
            ];
        }

        // Fallback to config('services.assistant') / .env variables
        $defaultUrl = (string) config('services.assistant.url', env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'));
        $defaultKey = (string) config('services.assistant.key', env('DEEPSEEK_API_KEY', ''));
        $defaultMdl = (string) config('services.assistant.model', env('DEEPSEEK_MODEL', 'deepseek-chat'));

        return [
            'id'          => 'env_fallback',
            'alias'       => 'Default .env Provider',
            'role'        => $role,
            'base_url'    => rtrim($defaultUrl, '/'),
            'api_key'     => $defaultKey,
            'model'       => $defaultMdl,
            'temperature' => $role === 'write_intent' ? 0.2 : 0.6,
            'max_tokens'  => null,
            'timeout_ms'  => 30000,
            'headers'     => [],
            'source'      => 'env_fallback',
        ];
    }

    /**
     * Expressive alias for getActiveModelConfig.
     */
    public function resolve(string $role = 'write_intent'): array
    {
        return $this->getActiveModelConfig($role);
    }

    /**
     * Tests connectivity, auth, and measures real latency in milliseconds for a provider.
     */
    public function testConnection(AssistantAiModel $model): array
    {
        $startTime = microtime(true);
        $endpoint = rtrim($model->base_url, '/');
        if (!str_ends_with($endpoint, '/chat/completions')) {
            $endpoint .= '/chat/completions';
        }

        $headers = (array) ($model->headers ?? []);
        $headers['Authorization'] = 'Bearer ' . $model->api_key;
        $headers['Content-Type'] = 'application/json';

        $timeoutSec = max(5, intval(($model->timeout_ms ?? 15000) / 1000));

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeoutSec)
                ->post($endpoint, [
                    'model'       => $model->model,
                    'messages'    => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                    'max_tokens'  => 5,
                    'temperature' => 0.1,
                ]);

            $latencyMs = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $reply = $response->json('choices.0.message.content', 'OK');
                return [
                    'success'     => true,
                    'latency_ms'  => $latencyMs,
                    'message'     => "Conexión exitosa ({$latencyMs} ms).",
                    'model_reply' => trim($reply),
                ];
            }

            return [
                'success'    => false,
                'latency_ms' => $latencyMs,
                'message'    => "Fallo al conectar con el proveedor (HTTP {$response->status()}): " . substr($response->body(), 0, 200),
            ];
        } catch (\Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000);
            return [
                'success'    => false,
                'latency_ms' => $latencyMs,
                'message'    => "Excepción de red o timeout ({$latencyMs} ms): " . $e->getMessage(),
            ];
        }
    }
}
