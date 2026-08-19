<?php

namespace App\Http\Controllers;

use App\Models\ExamAttemptModel;
use App\Models\ExamQuestionModel;
use App\Services\AiQuotaService;
use App\Services\EvaluarRespuestaAbiertaService;
use App\Support\ExamQuestionNormalizer;
use App\Support\MongoModelFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EvaluarRespuestaController extends Controller
{
    public function __construct(
        private AiQuotaService $quota,
        private EvaluarRespuestaAbiertaService $evaluador,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pregunta_id' => ['required', 'string'],
            'respuesta' => ['required', 'string', 'max:4000'],
            'attempt_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $question = MongoModelFinder::findOrFail(ExamQuestionModel::class, $validated['pregunta_id']);
        $normalized = $question->toNormalizedArray();

        if (($normalized['type'] ?? '') !== ExamQuestionNormalizer::TYPE_OPEN) {
            return response()->json([
                'code' => 'PREGUNTA_NO_ABIERTA',
                'message' => 'Solo se pueden evaluar preguntas abiertas con IA.',
            ], 422);
        }

        $attempt = null;
        if (! empty($validated['attempt_id'])) {
            $attempt = MongoModelFinder::findOrFail(ExamAttemptModel::class, $validated['attempt_id']);
            abort_unless(
                (string) $attempt->user_id === (string) $user->getKey(),
                403
            );
        }

        $user = $this->quota->descontarUnoOFallar($user);

        try {
            $evaluacion = $this->evaluador->evaluar($normalized, $validated['respuesta']);
        } catch (Throwable $e) {
            $this->quota->reembolsar($user, 1);

            return response()->json([
                'code' => 'IA_NO_DISPONIBLE',
                'message' => 'No se pudo evaluar la respuesta. Inténtalo de nuevo.',
            ], 502);
        }

        if ($attempt) {
            $attempt->applyAiEvaluation((string) $question->getKey(), $evaluacion);
        }

        $user->refresh();

        return response()->json([
            'evaluacion' => $evaluacion,
            'intentos_restantes' => (int) ($user->intentos_ia_restantes ?? 0),
            'limite_ia_resetea_el' => optional($user->limite_ia_resetea_el)?->toIso8601String(),
        ]);
    }
}
