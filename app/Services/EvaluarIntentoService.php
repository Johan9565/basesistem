<?php

namespace App\Services;

use App\Models\User;
use App\Support\ExamQuestionNormalizer;
use Throwable;

class EvaluarIntentoService
{
    public function __construct(
        private AiQuotaService $quota,
        private EvaluarRespuestaAbiertaService $evaluador,
    ) {}

    /**
     * @param  iterable<int, array<string, mixed>>  $questions
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $already
     * @return array<string, array<string, mixed>>
     */
    public function evaluarAbiertas(User $user, iterable $questions, array $answers, array $already = []): array
    {
        $pending = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $id = (string) ($question['id'] ?? '');
            $type = (string) ($question['type'] ?? $question['tipo'] ?? '');

            if ($id === '' || $type !== ExamQuestionNormalizer::TYPE_OPEN) {
                continue;
            }

            if (isset($already[$id]['estado'])) {
                continue;
            }

            $text = is_string($answers[$id] ?? null) ? trim((string) $answers[$id]) : '';
            if ($text === '') {
                continue;
            }

            $pending[] = $question;
        }

        if ($pending === [] || ! $user->esUsuarioPremium()) {
            return $already;
        }

        $reservados = $this->quota->descontarAtomicamente($user, count($pending));
        $toEval = array_slice($pending, 0, $reservados);
        $reviews = $already;

        foreach ($toEval as $question) {
            $id = (string) $question['id'];

            try {
                $reviews[$id] = $this->evaluador->evaluar($question, (string) $answers[$id]);
            } catch (Throwable) {
                $this->quota->reembolsar($user, 1);
            }
        }

        return $reviews;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $already
     * @return array<string, mixed>
     */
    public function evaluarUna(User $user, array $question, string $respuesta, array $already = []): array
    {
        $id = (string) ($question['id'] ?? '');
        $type = (string) ($question['type'] ?? $question['tipo'] ?? '');

        if ($id === '' || $type !== ExamQuestionNormalizer::TYPE_OPEN) {
            return $already;
        }

        if (isset($already[$id]['estado'])) {
            return $already;
        }

        $text = trim($respuesta);
        if ($text === '' || ! $user->esUsuarioPremium()) {
            return $already;
        }

        if ($this->quota->descontarAtomicamente($user, 1) < 1) {
            return $already;
        }

        try {
            $already[$id] = $this->evaluador->evaluar($question, $text);
        } catch (Throwable) {
            $this->quota->reembolsar($user, 1);
        }

        return $already;
    }
}
