<?php

declare(strict_types=1);

namespace App\Domain\Learning\Services;

use App\Domain\Assessment\Models\Quiz;
use App\Domain\Assessment\Models\QuizAttempt;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use Illuminate\Support\Str;

class StudentProgressService
{
    public function recalculateCourseProgress(User $student, Course $course, ?Batch $batch = null): CourseProgress
    {
        return $this->calculateProgress($student, $course, $batch);
    }

    /**
     * Calculate and persist progress metrics for a student in a course/batch.
     * Evaluates strictly over published required lessons and held sessions.
     */
    public function calculateProgress(User $student, Course $course, ?Batch $batch = null): CourseProgress
    {
        // 1. Lesson Completion Percentage (strictly over published mandatory lessons)
        $requiredLessonIds = Lesson::query()
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('modules.course_id', $course->id)
            ->where('lessons.is_published', true)
            ->where('lessons.is_mandatory', true)
            ->whereNull('lessons.deleted_at')
            ->pluck('lessons.id');

        $totalRequiredLessons = $requiredLessonIds->count();
        $lessonsCompleted = 0;
        if ($totalRequiredLessons > 0) {
            $lessonsCompleted = LessonProgress::query()
                ->where('user_id', $student->id)
                ->whereIn('lesson_id', $requiredLessonIds)
                ->where('is_completed', true)
                ->count();
            $lessonPercentage = round(($lessonsCompleted / $totalRequiredLessons) * 100, 2);
        } else {
            $lessonPercentage = 100.00;
        }

        // 2. Attendance Percentage (strictly over held sessions: status = completed or started in past)
        $attendancePercentage = 100.00;
        $resolvedBatch = $batch;
        if ($resolvedBatch === null) {
            /** @var Enrollment|null $enrollment */
            $enrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->first();
            $resolvedBatch = $enrollment?->batch;
        }

        if ($resolvedBatch !== null) {
            $heldSessionIds = Session::query()
                ->where('batch_id', $resolvedBatch->id)
                ->where(function ($query) {
                    $query->where('status', SessionStatus::Completed->value)
                        ->orWhere('starts_at', '<=', now());
                })
                ->pluck('id');

            $totalHeldSessions = $heldSessionIds->count();
            if ($totalHeldSessions > 0) {
                $attendedCount = SessionAttendance::query()
                    ->where('user_id', $student->id)
                    ->whereIn('session_id', $heldSessionIds)
                    ->whereIn('status', ['present', 'late'])
                    ->count();
                $attendancePercentage = round(($attendedCount / $totalHeldSessions) * 100, 2);
            }
        }

        // 3. Quiz Average (published course quizzes)
        $quizzes = Quiz::query()
            ->where('course_id', $course->id)
            ->where('is_published', true)
            ->get();

        $quizAverage = 100.00;
        if ($quizzes->count() > 0) {
            $totalQuizScores = 0.0;
            foreach ($quizzes as $quiz) {
                $bestScore = QuizAttempt::query()
                    ->where('quiz_id', $quiz->id)
                    ->where('user_id', $student->id)
                    ->max('percentage') ?? 0.0;
                $totalQuizScores += (float) $bestScore;
            }
            $quizAverage = round($totalQuizScores / $quizzes->count(), 2);
        }

        // 4. Overall Percentage
        $hasSessions = ($resolvedBatch !== null && Session::query()->where('batch_id', $resolvedBatch->id)->exists());
        $hasQuizzes = $quizzes->count() > 0;

        if ($hasSessions && $hasQuizzes) {
            $overall = round(($lessonPercentage * 0.50) + ($attendancePercentage * 0.25) + ($quizAverage * 0.25), 2);
        } elseif ($hasSessions) {
            $overall = round(($lessonPercentage * 0.70) + ($attendancePercentage * 0.30), 2);
        } elseif ($hasQuizzes) {
            $overall = round(($lessonPercentage * 0.70) + ($quizAverage * 0.30), 2);
        } else {
            $overall = $lessonPercentage;
        }

        $overall = min(100.00, max(0.00, $overall));

        // Get last accessed lesson
        $lastProgress = LessonProgress::query()
            ->where('user_id', $student->id)
            ->latest('updated_at')
            ->first();

        /** @var CourseProgress $courseProgress */
        $courseProgress = CourseProgress::updateOrCreate(
            [
                'user_id' => $student->id,
                'course_id' => $course->id,
            ],
            [
                'batch_id' => $resolvedBatch?->id,
                'overall_percentage' => $overall,
                'lessons_completed' => $lessonsCompleted,
                'total_required_lessons' => $totalRequiredLessons,
                'attendance_percentage' => $attendancePercentage,
                'quiz_average' => $quizAverage,
                'last_accessed_lesson_id' => $lastProgress?->lesson_id,
            ]
        );

        if ($overall >= 100.00 && $courseProgress->completed_at === null) {
            $courseProgress->update(['completed_at' => now()]);
            $this->issueCertificateIfNeeded($student, $course, $resolvedBatch);
        }

        return $courseProgress;
    }

    protected function issueCertificateIfNeeded(User $student, Course $course, ?Batch $batch): void
    {
        $existing = Certificate::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing === null) {
            Certificate::create([
                'certificate_number' => 'CERT-'.strtoupper(Str::random(8)).'-'.date('Y'),
                'user_id' => $student->id,
                'course_id' => $course->id,
                'batch_id' => $batch?->id,
                'issue_date' => now(),
                'template_name' => 'classical_yoga_v1',
                'metadata' => [
                    'student_name' => $student->full_name,
                    'course_title' => $course->title,
                    'issued_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }
}
