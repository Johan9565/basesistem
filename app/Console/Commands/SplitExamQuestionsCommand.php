<?php

namespace App\Console\Commands;

use App\Models\ExamModel;
use App\Models\ExamQuestionModel;
use Illuminate\Console\Command;

class SplitExamQuestionsCommand extends Command
{
    protected $signature = 'exams:split-questions';

    protected $description = 'Mueve preguntas embebidas en exams hacia la colección questions';

    public function handle(): int
    {
        $moved = 0;
        $skipped = 0;

        ExamModel::query()->orderBy('order_index')->each(function (ExamModel $exam) use (&$moved, &$skipped) {
            $rows = $exam->embeddedQuestionsArray();

            if ($rows === []) {
                $skipped++;
                $exam->clearEmbeddedQuestions();

                return;
            }

            $examId = (string) $exam->getKey();
            $existing = ExamQuestionModel::where('examen_id', $exam->objectId())
                ->orWhere('examen_id', $examId)
                ->orWhere('exam_id', $examId)
                ->count();

            if ($existing === 0) {
                $exam->replaceQuestions($rows);
                $moved++;
            } else {
                $skipped++;
                $exam->clearEmbeddedQuestions();
            }
        });

        $this->info("Exámenes migrados: {$moved}. Sin cambios: {$skipped}.");

        return self::SUCCESS;
    }
}
