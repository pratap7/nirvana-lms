<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Domain\Assessment\Models\Quiz;
use App\Domain\Assessment\Models\QuizAttempt;
use App\Domain\Assessment\Services\QuizGradingService;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QuizPlayer extends Component
{
    public ?Quiz $activeQuiz = null;

    /** @var array<int|string, mixed> */
    public array $answers = [];

    public ?QuizAttempt $latestAttempt = null;

    public bool $isTakingQuiz = false;

    public function startQuiz(string $quizUlid): void
    {
        /** @var User $student */
        $student = auth()->user();

        /** @var Quiz $quiz */
        $quiz = Quiz::query()->with(['questions', 'course'])->where('ulid', $quizUlid)->firstOrFail();

        if (! $quiz->canUserAttempt($student)) {
            session()->flash('error', 'You have reached the maximum allowed attempts for this quiz.');

            return;
        }

        $this->activeQuiz = $quiz;
        $this->answers = [];
        $this->latestAttempt = null;
        $this->isTakingQuiz = true;
    }

    public function submitQuiz(QuizGradingService $gradingService): void
    {
        if ($this->activeQuiz === null) {
            return;
        }

        /** @var User $student */
        $student = auth()->user();

        $this->latestAttempt = $gradingService->submitAndGrade(
            quiz: $this->activeQuiz,
            student: $student,
            submittedAnswers: $this->answers
        );

        $this->isTakingQuiz = false;
    }

    public function resetQuiz(): void
    {
        $this->activeQuiz = null;
        $this->isTakingQuiz = false;
        $this->latestAttempt = null;
    }

    public function render(): View
    {
        /** @var User $student */
        $student = auth()->user();

        $courseIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->pluck('course_id');

        $quizzes = Quiz::query()
            ->with(['course', 'questions', 'attempts' => fn ($q) => $q->where('user_id', $student->id)])
            ->whereIn('course_id', $courseIds)
            ->where('is_published', true)
            ->get();

        return view('livewire.student.quiz-player', [
            'quizzes' => $quizzes,
        ])->layout('layouts.student', ['title' => 'Practice Quizzes']);
    }
}
