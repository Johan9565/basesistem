<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistantAiModel;
use App\Services\Assistant\ModelRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantModelAdminController extends Controller
{
    protected ModelRouterService $modelRouter;

    public function __construct(ModelRouterService $modelRouter)
    {
        $this->modelRouter = $modelRouter;
    }

    /**
     * List all configured AI models/providers.
     */
    public function index(): JsonResponse
    {
        $models = AssistantAiModel::all()->map(function (AssistantAiModel $m) {
            return [
                'id'             => (string) $m->_id,
                'alias'          => $m->alias,
                'role'           => $m->role,
                'base_url'       => $m->base_url,
                'model'          => $m->model,
                'masked_api_key' => $m->getMaskedApiKey(),
                'has_api_key'    => !empty($m->api_key),
                'temperature'    => (float) ($m->temperature ?? 0.2),
                'max_tokens'     => $m->max_tokens ? (int) $m->max_tokens : null,
                'timeout_ms'     => (int) ($m->timeout_ms ?? 30000),
                'headers'        => $m->headers ?? [],
                'status'         => $m->status,
                'is_default'     => (bool) $m->is_default,
                'updated_at'     => $m->updated_at ? $m->updated_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $models,
            'message' => 'Modelos obtenidos exitosamente.',
        ]);
    }

    /**
     * Register a new AI provider/model.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alias'       => 'required|string|max:100',
            'role'        => 'required|in:write_intent,read_intent,reasoning_fallback',
            'base_url'    => 'required|string|max:255',
            'api_key'     => 'required|string|min:4',
            'model'       => 'required|string|max:100',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens'  => 'nullable|integer|min:1',
            'timeout_ms'  => 'nullable|integer|min:1000',
            'headers'     => 'nullable|array',
            'status'      => 'required|in:active,inactive,standby',
            'is_default'  => 'nullable|boolean',
        ]);

        if (($validated['status'] ?? '') === 'active') {
            // Demote other models in the same role to standby
            AssistantAiModel::where('role', $validated['role'])
                ->where('status', 'active')
                ->update(['status' => 'standby']);
        }

        $created = AssistantAiModel::create($validated);
        $this->modelRouter->invalidateCache();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => (string) $created->_id,
                'alias'          => $created->alias,
                'role'           => $created->role,
                'base_url'       => $created->base_url,
                'model'          => $created->model,
                'masked_api_key' => $created->getMaskedApiKey(),
                'status'         => $created->status,
            ],
            'message' => 'Modelo de IA registrado exitosamente.',
        ], 201);
    }

    /**
     * Update an existing model.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $model = AssistantAiModel::find($id);
        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Modelo no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'alias'       => 'sometimes|string|max:100',
            'role'        => 'sometimes|in:write_intent,read_intent,reasoning_fallback',
            'base_url'    => 'sometimes|string|max:255',
            'api_key'     => 'nullable|string|min:4',
            'model'       => 'sometimes|string|max:100',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens'  => 'nullable|integer|min:1',
            'timeout_ms'  => 'nullable|integer|min:1000',
            'headers'     => 'nullable|array',
            'status'      => 'sometimes|in:active,inactive,standby',
            'is_default'  => 'nullable|boolean',
        ]);

        // If api_key is empty/null, don'''t overwrite existing encrypted key
        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        $role = $validated['role'] ?? $model->role;
        $status = $validated['status'] ?? $model->status;

        if ($status === 'active') {
            AssistantAiModel::where('role', $role)
                ->where('status', 'active')
                ->where('_id', '!=', $model->_id)
                ->update(['status' => 'standby']);
        }

        $model->update($validated);
        $this->modelRouter->invalidateCache();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => (string) $model->_id,
                'alias'          => $model->alias,
                'role'           => $model->role,
                'base_url'       => $model->base_url,
                'model'          => $model->model,
                'masked_api_key' => $model->getMaskedApiKey(),
                'status'         => $model->status,
            ],
            'message' => 'Modelo de IA actualizado exitosamente.',
        ]);
    }

    /**
     * Delete an existing model.
     */
    public function destroy(string $id): JsonResponse
    {
        $model = AssistantAiModel::find($id);
        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Modelo no encontrado.',
            ], 404);
        }

        $model->delete();
        $this->modelRouter->invalidateCache();

        return response()->json([
            'success' => true,
            'message' => 'Modelo eliminado exitosamente.',
        ]);
    }

    /**
     * Activate a model for its role.
     */
    public function activate(string $id): JsonResponse
    {
        $model = AssistantAiModel::find($id);
        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Modelo no encontrado.',
            ], 404);
        }

        // Set others to standby
        AssistantAiModel::where('role', $model->role)
            ->where('_id', '!=', $model->_id)
            ->update(['status' => 'standby']);

        $model->status = 'active';
        $model->save();

        $this->modelRouter->invalidateCache();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'     => (string) $model->_id,
                'alias'  => $model->alias,
                'role'   => $model->role,
                'status' => $model->status,
            ],
            'message' => "Modelo '{$model->alias}' activado exitosamente para el rol {$model->role}.",
        ]);
    }

    /**
     * Test provider connectivity and measure real latency in ms.
     */
    public function test(Request $request): JsonResponse
    {
        if ($request->has('model_id') && !empty($request->input('model_id'))) {
            $model = AssistantAiModel::find($request->input('model_id'));
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Modelo no encontrado.',
                ], 404);
            }
        } else {
            $validated = $request->validate([
                'base_url'   => 'required|string',
                'api_key'    => 'required|string',
                'model'      => 'required|string',
                'timeout_ms' => 'nullable|integer',
                'headers'    => 'nullable|array',
            ]);

            $model = new AssistantAiModel($validated);
        }

        $testResult = $this->modelRouter->testConnection($model);

        return response()->json([
            'success' => $testResult['success'],
            'data'    => $testResult,
            'message' => $testResult['message'],
        ]);
    }
}
