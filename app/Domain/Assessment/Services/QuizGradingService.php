<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Models\Quiz;
use App\Domain\Assessment\Models\QuizAttempt;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Services\StudentProgressService;
use App\Domain\Scheduling\Models\Batch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class QuizGradingService
{
    public function __construct(
        protected StudentProgressService $progressService
    ) {}

    /**
     * Grade student quiz answers, record attempt, and trigger progress update.
     *
     * @param  array<string|int, mixed>  $submittedAnswers
     */
    public function submitAndGrade(
        Quiz $quiz,
        User $student,
        array $submittedAnswers,
        ?Batch $batch = null
    ): QuizAttempt {
        return DB::transaction(function () use ($quiz, $student, $submittedAnswers, $batch): QuizAttempt {
            $questions = $quiz->questions;
            $earnedScore = 0;
            $maxScore = 0;

            foreach ($questions as $question) {
                $maxScore += $question->points;
                $userAnswer = $submittedAnswers[$question->id] ?? $submittedAnswers[(string) $question->id] ?? null;

                if ($userAnswer === null) {
                    continue;
                }

                $isCorrect = false;

                if ($question->type === 'single_choice' || $question->type === 'true_false') {
                    foreach ($question->options as $opt) {
                        if ($opt['is_correct'] && (string) $opt['id'] === (string) $userAnswer) {
                            $isCorrect = true;
                            break;
                        }
                    }
                } elseif ($question->type === 'multiple_choice') {
                    $selected = is_array($userAnswer) ? $userAnswer : [$userAnswer];
                    $correctOptionIds = [];
                    foreach ($question->options as $opt) {
                        if ($opt['is_correct']) {
                            $correctOptionIds[] = (string) $opt['id'];
                        }
                    }
                    $selectedStrings = array_map(fn ($val): string => (string) $val, $selected);
                    sort($selectedStrings);
                    sort($correctOptionIds);
                    $isCorrect = ($selectedStrings === $correctOptionIds);
                } elseif ($question->type === 'short_answer') {
                    $cleanedUser = strtolower(trim((string) $userAnswer));
                    foreach ($question->options as $opt) {
                        $expected = strtolower(trim((string) ($opt['text'] ?: $opt['id'])));
                        if ($cleanedUser === $expected) {
                            $isCorrect = true;
                            break;
                        }
                    }
                    if (! $isCorrect && $question->explanation) {
                        if ($cleanedUser === strtolower(trim($question->explanation))) {
                            $isCorrect = true;
                        }
                    }
                }

                if ($isCorrect) {
                    $earnedScore += $question->points;
                }
            }

            $percentage = $maxScore > 0 ? round(($earnedScore / $maxScore) * 100, 2) : 100.00;
            $passed = $percentage >= $quiz->pass_percentage;

            $attemptCount = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $student->id)
                ->count();

            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $student->id,
                'batch_id' => $batch?->id,
                'attempt_number' => $attemptCount + 1,
                'answers' => $submittedAnswers,
                'score' => $earnedScore,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'passed' => $passed,
                'started_at' => CarbonImmutable::now()->subMinutes(10),
                'submitted_at' => CarbonImmutable::now(),
            ]);

            // Update student progress in course
            if ($quiz->course !== null) {
                $this->progressService->calculateProgress($student, $quiz->course, $batch);
            }

            return $attempt;
        });
    }
}
