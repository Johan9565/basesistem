<?php

namespace Tests\Feature;

use App\Models\ExamAttemptModel;
use App\Models\ExamModel;
use App\Models\User;
use App\Services\AiQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreemiumAndAiEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_cannot_access_a_premium_exam(): void
    {
        $exam = ExamModel::factory()->premiumAccess()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('exams.show', $exam->getKey()))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('exams.attempts.store', $exam->getKey()), [
                'anuncio_visto' => true,
            ])
            ->assertForbidden();
    }

    public function test_premium_user_can_access_a_premium_exam(): void
    {
        $exam = ExamModel::factory()->premiumAccess()->create();
        $user = User::factory()->premium()->create();

        $this->actingAs($user)
            ->get(route('exams.show', $exam->getKey()))
            ->assertOk();
    }

    public function test_free_user_gets_three_trial_attempts_and_the_fourth_is_blocked(): void
    {
        $exam = ExamModel::factory()->prueba()->create();
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)
                ->post(route('exams.attempts.store', $exam->getKey()), [
                    'anuncio_visto' => true,
                ])
                ->assertRedirect();

            $attempt = ExamAttemptModel::query()
                ->where('user_id', (string) $user->getKey())
                ->where('exam_id', (string) $exam->getKey())
                ->where('status', ExamAttemptModel::STATUS_IN_PROGRESS)
                ->first();

            $this->assertNotNull($attempt);

            $this->actingAs($user)
                ->post(route('exams.attempts.submit', [
                    'exam' => $exam->getKey(),
                    'attempt' => $attempt->getKey(),
                ]))
                ->assertRedirect();
        }

        $this->assertSame(3, ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->count());

        $this->actingAs($user)
            ->from(route('exams.show', $exam->getKey()))
            ->post(route('exams.attempts.store', $exam->getKey()), [
                'anuncio_visto' => true,
            ])
            ->assertRedirect(route('exams.show', $exam->getKey()))
            ->assertSessionHasErrors('exam');
    }

    public function test_trial_exam_requires_the_ad_before_a_new_attempt(): void
    {
        $exam = ExamModel::factory()->prueba()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('exams.show', $exam->getKey()))
            ->post(route('exams.attempts.store', $exam->getKey()))
            ->assertRedirect(route('exams.show', $exam->getKey()))
            ->assertSessionHasErrors('anuncio');
    }

    public function test_trial_exam_caps_questions_at_ten(): void
    {
        $exam = ExamModel::factory()->prueba()->create();
        $questions = [];
        for ($i = 0; $i < 12; $i++) {
            $questions[] = [
                'tipo' => 'opcion_unica',
                'pregunta' => 'Pregunta '.$i,
                'materia' => 'Tema',
                'opciones' => ['A', 'B'],
                'correctas' => [0],
                'respuesta_correcta' => 'A',
                'respuesta_modelo' => 'A',
                'criterios' => [],
            ];
        }
        $exam->replaceQuestions($questions);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('exams.attempts.store', $exam->getKey()), [
                'anuncio_visto' => true,
            ])
            ->assertRedirect();

            $attempt = ExamAttemptModel::query()
                ->where('user_id', (string) $user->getKey())
                ->where('exam_id', (string) $exam->getKey())
                ->first();
            $this->assertNotNull($attempt);
            $this->assertCount(10, $attempt->question_ids ?? []);
    }

    public function test_premium_submit_evaluates_open_questions_and_decrements_quota(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response($this->deepSeekPayload([
                'estado' => 'correcto',
                'nivel_acierto' => 100,
                'criterios_cumplidos' => ['supremacia constitucional'],
                'criterios_omitidos' => [],
                'retroalimentacion' => 'Cubrió el criterio central.',
            ])),
        ]);

        $exam = ExamModel::factory()->withOpenQuestion()->create();
        $user = User::factory()->premium()->create();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();
        $open = $exam->questionRecords()->where('tipo', 'abierta')->first();
        $this->assertNotNull($open);

        $this->actingAs($user)
            ->post(route('exams.attempts.submit', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]), [
                'answers' => [
                    (string) $open->getKey() => 'Todas las normas deben ajustarse a la Constitución por supremacía constitucional.',
                ],
            ])
            ->assertRedirect();

        $attempt->refresh();
        $user->refresh();

        $this->assertSame(29, (int) $user->intentos_ia_restantes);
        $openReview = collect($attempt->review ?? [])->first(
            fn ($row) => (string) ($row['question_id'] ?? '') === (string) $open->getKey()
        );
        $this->assertIsArray($openReview);
        $this->assertSame('correcto', $openReview['estado'] ?? null);
        $this->assertSame(100, (int) ($openReview['nivel_acierto'] ?? 0));
        $this->assertTrue((bool) $openReview['is_correct']);
        Http::assertSentCount(1);
    }

    public function test_exhausted_quota_falls_back_to_keyword_grading_without_calling_the_llm(): void
    {
        Http::fake();

        $exam = ExamModel::factory()->withOpenQuestion()->create();
        $user = User::factory()->premium()->create([
            'intentos_ia_restantes' => 0,
            'limite_ia_resetea_el' => now()->addDay(),
        ]);

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();
        $open = $exam->questionRecords()->where('tipo', 'abierta')->first();

        $this->actingAs($user)
            ->post(route('exams.attempts.submit', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]), [
                'answers' => [
                    (string) $open->getKey() => 'supremacia constitucional normas se ajustan a la constitucion',
                ],
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(0, (int) $user->intentos_ia_restantes);
        Http::assertNothingSent();
    }

    public function test_quota_resets_when_the_period_expired(): void
    {
        $user = User::factory()->premium()->create([
            'intentos_ia_restantes' => 0,
            'limite_ia_resetea_el' => now()->subMinute(),
        ]);

        $refreshed = app(AiQuotaService::class)->verificarYResetear($user);

        $this->assertSame(30, (int) $refreshed->intentos_ia_restantes);
        $this->assertTrue($refreshed->limite_ia_resetea_el->greaterThan(now()));
    }

    public function test_evaluar_respuesta_requires_premium_and_returns_429_when_quota_is_empty(): void
    {
        $exam = ExamModel::factory()->withOpenQuestion()->create();
        $open = $exam->questionRecords()->where('tipo', 'abierta')->first();

        $free = User::factory()->create();
        $this->actingAs($free)
            ->postJson(route('api.evaluar-respuesta'), [
                'pregunta_id' => (string) $open->getKey(),
                'respuesta' => 'Una respuesta de prueba.',
            ])
            ->assertForbidden()
            ->assertJson(['code' => 'REQUIERE_PREMIUM']);

        $premium = User::factory()->premium()->create([
            'intentos_ia_restantes' => 0,
            'limite_ia_resetea_el' => now()->addDay(),
        ]);

        $this->actingAs($premium)
            ->postJson(route('api.evaluar-respuesta'), [
                'pregunta_id' => (string) $open->getKey(),
                'respuesta' => 'Una respuesta de prueba.',
            ])
            ->assertStatus(429)
            ->assertJson(['code' => 'CUOTA_IA_AGOTADA']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function deepSeekPayload(array $payload): array
    {
        return [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode($payload),
                    ],
                ],
            ],
        ];
    }
}
