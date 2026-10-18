<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Policies\LessonLearningPolicy;
use App\Domain\Learning\Services\StudentProgressService;
use App\Domain\Learning\Services\VideoStreamingService;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CoursePlayer extends Component
{
    public Course $course;

    public ?Batch $batch = null;

    public ?Lesson $activeLesson = null;

    public ?LessonProgress $activeProgress = null;

    public ?CourseProgress $courseProgress = null;

    public string $signedStreamUrl = '';

    /** @var array{text: string, user_id: string, email: string, timestamp: string} */
    public array $watermarkData = ['text' => '', 'user_id' => '', 'email' => '', 'timestamp' => ''];

    public float $playbackSpeed = 1.0;

    public function mount(
        string $courseSlug,
        ?string $lessonUlid = null,
        ?LessonLearningPolicy $policy = null,
        ?VideoStreamingService $videoService = null,
        ?StudentProgressService $progressService = null
    ): void {
        $policy ??= app(LessonLearningPolicy::class);
        $videoService ??= app(VideoStreamingService::class);
        $progressService ??= app(StudentProgressService::class);

        /** @var User $student */
        $student = auth()->user();

        $this->course = Course::query()
            ->with(['modules.lessons.resources', 'modules.lessons.contents'])
            ->where('slug', $courseSlug)
            ->firstOrFail();

        $batchUlid = request()->query('batch');
        if ($batchUlid) {
            $this->batch = Batch::query()->where('ulid', (string) $batchUlid)->first();
        } else {
            /** @var Enrollment|null $enrollment */
            $enrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_id', $this->course->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->first();
            $this->batch = $enrollment?->batch;
        }

        // CRITICAL ACCEPTANCE CHECK: Must hold ACTIVE enrollment in this batch
        $hasActiveEnrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $this->course->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($this->batch, fn ($q) => $q->where('batch_id', $this->batch->id))
            ->exists();

        if (! $hasActiveEnrollment && ! $student->hasRole(['Admin', 'Super Admin', 'Moderator'])) {
            abort(403, 'Forbidden: You do not have an active enrollment in this batch.');
        }

        // Resolve active lesson (resume where left off)
        if ($lessonUlid) {
            $this->activeLesson = Lesson::query()->where('ulid', $lessonUlid)->firstOrFail();
        } else {
            // Find last accessed lesson
            $lastAccessedId = CourseProgress::query()
                ->where('user_id', $student->id)
                ->where('course_id', $this->course->id)
                ->value('last_accessed_lesson_id');

            if ($lastAccessedId) {
                $this->activeLesson = Lesson::query()->find($lastAccessedId);
            }

            if ($this->activeLesson === null) {
                $firstModule = $this->course->modules->first();
                $firstLesson = $firstModule?->lessons->first();
                $this->activeLesson = $firstLesson instanceof Lesson ? $firstLesson : null;
            }
        }

        if ($this->activeLesson) {
            $this->loadLessonDetails($this->activeLesson, $videoService, $progressService);
        }
    }

    public function selectLesson(
        string $lessonUlid,
        LessonLearningPolicy $policy,
        VideoStreamingService $videoService,
        StudentProgressService $progressService
    ): void {
        /** @var User $student */
        $student = auth()->user();

        /** @var Lesson $lesson */
        $lesson = Lesson::query()->where('ulid', $lessonUlid)->firstOrFail();

        // Enforce policy check on every lesson selection
        if (! $policy->view($student, $lesson, $this->batch)) {
            abort(403, 'Forbidden: You cannot access this lesson.');
        }

        $this->activeLesson = $lesson;
        $this->loadLessonDetails($lesson, $videoService, $progressService);
    }

    protected function loadLessonDetails(
        Lesson $lesson,
        VideoStreamingService $videoService,
        StudentProgressService $progressService
    ): void {
        /** @var User $student */
        $student = auth()->user();

        $this->activeProgress = LessonProgress::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'batch_id' => $this->batch?->id,
                'is_completed' => false,
                'watch_percentage' => 0,
                'last_position_seconds' => 0,
            ]
        );

        $this->signedStreamUrl = $videoService->generateSignedUrl($lesson, $student);
        $this->watermarkData = $videoService->getWatermarkData($student);

        // Update last accessed lesson in course progress
        $this->courseProgress = $progressService->calculateProgress($student, $this->course, $this->batch);
    }

    public function toggleComplete(StudentProgressService $progressService): void
    {
        if ($this->activeProgress && $this->activeLesson) {
            /** @var User $student */
            $student = auth()->user();

            $newState = ! $this->activeProgress->is_completed;
            $this->activeProgress->update([
                'is_completed' => $newState,
                'watch_percentage' => $newState ? 100 : $this->activeProgress->watch_percentage,
                'completed_at' => $newState ? now() : null,
            ]);

            $this->courseProgress = $progressService->calculateProgress($student, $this->course, $this->batch);
        }
    }

    public function recordProgress(
        int $seconds,
        int $percentage,
        StudentProgressService $progressService
    ): void {
        if ($this->activeProgress && $this->activeLesson) {
            /** @var User $student */
            $student = auth()->user();

            $autoComplete = ($percentage >= 90) || $this->activeProgress->is_completed;

            $this->activeProgress->update([
                'last_position_seconds' => $seconds,
                'watch_percentage' => max($this->activeProgress->watch_percentage, $percentage),
                'is_completed' => $autoComplete,
                'completed_at' => $autoComplete ? ($this->activeProgress->completed_at ?? now()) : null,
            ]);

            $this->courseProgress = $progressService->calculateProgress($student, $this->course, $this->batch);
        }
    }

    public function render(): View
    {
        /** @var User $student */
        $student = auth()->user();

        // Get student's completed lesson IDs for sidebar checkmarks
        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $student->id)
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->toArray();

        return view('livewire.student.course-player', [
            'completedLessonIds' => $completedLessonIds,
        ])->layout('layouts.student', ['title' => $this->course->title]);
    }
}
