<?php

declare(strict_types=1);

namespace App\Domain\Certification\Services;

use App\Domain\Assessment\Enums\AssessmentType;
use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Assessment\Models\QuizAttempt;
use App\Domain\Catalog\Models\Course;
use App\Domain\Certification\Models\CompletionEvaluation;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;

/**
 * Evaluates a student's progress against a course's JSON completion rules.
 * Generates per-requirement checklist with pass/fail reasons.
 * Snapshots rules and checklist for audit integrity.
 */
class CompletionEvaluator
{
    /**
     * Evaluate completion rules and return an evaluated CompletionEvaluation model.
     *
     * @param  array<string, mixed>|null  $ruleOverride  Optional rules override (default reads course's current rules)
     */
    public function evaluate(Course $course, User $student, ?Batch $batch = null, ?array $ruleOverride = null): CompletionEvaluation
    {
        // 1. Read course rules (JSON or array). Empty rules are valid (a workshop may need nothing).
        $rules = $ruleOverride ?? $this->extractCourseRules($course);

        // 2. Fetch existing evaluation to preserve manual approval if previously granted
        /** @var ?CompletionEvaluation $existing */
        $existing = CompletionEvaluation::query()
            ->where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->first();

        $isManuallyApproved = $existing ? $existing->is_manually_approved : false;

        // 3. Fetch student learning metrics
        /** @var ?CourseProgress $progress */
        $progress = CourseProgress::query()
            ->where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->first();

        $actualAttendance = $progress ? (float) $progress->attendance_percentage : 0.0;
        if ($actualAttendance === 0.0) {
            $sessionQuery = Session::query()
                ->where('status', SessionStatus::Completed);

            if ($batch) {
                $sessionQuery->where('batch_id', $batch->id);
            } else {
                $sessionQuery->where('course_id', $course->id);
            }

            $totalHeldSessions = (clone $sessionQuery)->count();
            if ($totalHeldSessions > 0) {
                $attended = SessionAttendance::query()
                    ->where('user_id', $student->id)
                    ->whereIn('session_id', (clone $sessionQuery)->select('id'))
                    ->whereIn('status', ['present', 'late'])
                    ->count();

                $actualAttendance = round(($attended / $totalHeldSessions) * 100.0, 1);
            }
        }

        $actualQuizScore = $progress ? (float) $progress->quiz_average : 0.0;
        if ($actualQuizScore === 0.0) {
            $quizAttempts = QuizAttempt::query()
                ->where('user_id', $student->id)
                ->whereHas('quiz', fn ($q) => $q->where('course_id', $course->id))
                ->where('status', 'submitted')
                ->get();

            if ($quizAttempts->isNotEmpty()) {
                $actualQuizScore = (float) $quizAttempts->avg('percentage');
            }
        }

        $completedLessonsCount = LessonProgress::query()
            ->where('user_id', $student->id)
            ->where('is_completed', true)
            ->whereHas('lesson.module', fn ($q) => $q->where('course_id', $course->id))
            ->count();

        $completedAssignmentsCount = AssignmentSubmission::query()
            ->where('user_id', $student->id)
            ->where('status', 'graded')
            ->whereHas('assignment', fn ($q) => $q->where('course_id', $course->id))
            ->where('score', '>=', 50)
            ->count();

        $completedPracticalsCount = AssignmentSubmission::query()
            ->where('user_id', $student->id)
            ->where('status', 'graded')
            ->whereHas('assignment', fn ($q) => $q->where('course_id', $course->id)->where('assessment_type', AssessmentType::Practical))
            ->where('score', '>=', 50)
            ->count();

        $totalPracticals = Assignment::query()
            ->where('course_id', $course->id)
            ->where('assessment_type', AssessmentType::Practical)
            ->count();

        // 4. Build per-requirement checklist
        $checklist = [];
        $allPassed = true;

        // Rule A: Minimum Attendance
        $minAttendance = $rules['min_attendance_percent'] ?? $rules['min_attendance'] ?? null;
        if ($minAttendance !== null && (int) $minAttendance > 0) {
            $required = (int) $minAttendance;
            $passed = $actualAttendance >= $required;
            if (! $passed) {
                $allPassed = false;
            }

            $checklist[] = [
                'key' => 'attendance',
                'type' => 'attendance',
                'label' => 'Session Attendance',
                'requirement' => 'Session Attendance',
                'required' => "{$required}%",
                'achieved' => round($actualAttendance, 1).'%',
                'is_satisfied' => $passed,
                'passed' => $passed,
                'reason' => $passed
                    ? "Satisfied: {$actualAttendance}% achieved meets the required {$required}%."
                    : "Unmet: {$actualAttendance}% achieved is below the required {$required}%.",
            ];
        }

        // Rule B: Minimum Score / Quiz Average
        $minScore = $rules['min_quiz_average'] ?? $rules['min_score_percent'] ?? $rules['min_score'] ?? null;
        if ($minScore !== null && (int) $minScore > 0) {
            $required = (int) $minScore;
            $passed = $actualQuizScore >= $required;
            if (! $passed) {
                $allPassed = false;
            }

            $checklist[] = [
                'key' => 'quiz_score',
                'type' => 'quiz_score',
                'label' => 'Assessment & Quiz Average',
                'requirement' => 'Assessment & Quiz Average',
                'required' => "{$required}%",
                'achieved' => round($actualQuizScore, 1).'%',
                'is_satisfied' => $passed,
                'passed' => $passed,
                'reason' => $passed
                    ? "Satisfied: {$actualQuizScore}% score meets the required {$required}% minimum."
                    : "Unmet: Current average score of {$actualQuizScore}% is below required {$required}%.",
            ];
        }

        // Rule C: Required Lessons
        $requiredLessons = $rules['required_lessons'] ?? [];
        if (! empty($requiredLessons)) {
            $requiredCount = count($requiredLessons);
            $passed = $completedLessonsCount >= $requiredCount;
            if (! $passed) {
                $allPassed = false;
            }

            $checklist[] = [
                'key' => 'lessons',
                'type' => 'lessons',
                'label' => 'Curriculum Lesson Completion',
                'requirement' => 'Curriculum Lesson Completion',
                'required' => "{$requiredCount} core lessons",
                'achieved' => "{$completedLessonsCount} lessons",
                'is_satisfied' => $passed,
                'passed' => $passed,
                'reason' => $passed
                    ? "Satisfied: Completed {$completedLessonsCount} required curriculum lessons."
                    : "Unmet: Only {$completedLessonsCount} of {$requiredCount} required lessons completed.",
            ];
        }

        // Rule D: Graded Assignments
        $requireAssignments = ! empty($rules['require_assignments']) || (isset($rules['assignments_required']) && (int) $rules['assignments_required'] > 0);
        if ($requireAssignments) {
            $required = isset($rules['assignments_required']) && (int) $rules['assignments_required'] > 0
                ? (int) $rules['assignments_required']
                : 1;
            $passed = $completedAssignmentsCount >= $required;
            if (! $passed) {
                $allPassed = false;
            }

            $checklist[] = [
                'key' => 'assignments',
                'type' => 'assignments',
                'label' => 'Graded Assignments',
                'requirement' => 'Graded Assignments',
                'required' => "{$required} passing submissions",
                'achieved' => "{$completedAssignmentsCount} completed",
                'is_satisfied' => $passed,
                'passed' => $passed,
                'reason' => $passed
                    ? "Satisfied: Submitted and passed {$completedAssignmentsCount} assignments."
                    : "Unmet: {$completedAssignmentsCount} of {$required} assignments graded as passing.",
            ];
        }

        // Rule E: Practicals
        $requirePracticals = ! empty($rules['require_practicals']) || (isset($rules['practicals_required']) && (int) $rules['practicals_required'] > 0);
        if ($requirePracticals) {
            $required = isset($rules['practicals_required']) && (int) $rules['practicals_required'] > 0
                ? (int) $rules['practicals_required']
                : max(1, $totalPracticals);
            $passed = $completedPracticalsCount >= $required;
            if (! $passed) {
                $allPassed = false;
            }

            $checklist[] = [
                'key' => 'practicals',
                'type' => 'practicals',
                'label' => 'Practical Demonstrations & Rubrics',
                'requirement' => 'Practical Demonstrations',
                'required' => "{$required} passed practicals",
                'achieved' => "{$completedPracticalsCount} completed",
                'is_satisfied' => $passed,
                'passed' => $passed,
                'reason' => $passed
                    ? "Satisfied: Successfully completed {$completedPracticalsCount} practical demonstration(s)."
                    : "Pending submission or grading: {$completedPracticalsCount} of {$required} practical demonstrations completed.",
            ];
        }

        // Rule F: Manual Approval Step
        $requiresManual = ! empty($rules['require_manual_approval']) || ! empty($rules['manual_approval']);
        if ($requiresManual) {
            $passed = $isManuallyApproved;
            if (! $passed) {
                $allPassed = false;
            }

            $checklist[] = [
                'key' => 'manual_approval',
                'type' => 'manual_approval',
                'label' => 'Faculty Mentor & Acharya Approval',
                'requirement' => 'Faculty Mentor & Acharya Approval',
                'required' => 'Signed Approval',
                'achieved' => $isManuallyApproved ? 'Approved by Faculty' : 'Pending Review',
                'is_satisfied' => $passed,
                'passed' => $passed,
                'reason' => $isManuallyApproved
                    ? 'Satisfied: Manual completion approval granted by faculty.'
                    : 'Pending approval: Awaiting formal review and endorsement by course faculty.',
            ];
        }

        // If rules were completely empty (e.g. workshop), student automatically passes!
        if (empty($checklist)) {
            $checklist[] = [
                'key' => 'general_completion',
                'type' => 'general_completion',
                'label' => 'Program Participation',
                'requirement' => 'Open Completion',
                'required' => 'Standard Enrollment',
                'achieved' => 'Enrolled',
                'is_satisfied' => true,
                'passed' => true,
                'reason' => 'This workshop has no strict assessment criteria. Registration completes the program.',
            ];
        }

        // 5. Store / update evaluation record with snapshot
        return CompletionEvaluation::updateOrCreate(
            [
                'course_id' => $course->id,
                'user_id' => $student->id,
            ],
            [
                'batch_id' => $batch?->id,
                'is_passed' => $allPassed,
                'is_manually_approved' => $isManuallyApproved,
                'requires_manual_approval' => $requiresManual,
                'rules_snapshot' => $rules,
                'checklist_results' => $checklist,
            ]
        );
    }

    /**
     * Grant manual approval for student completion.
     */
    public function approveManually(CompletionEvaluation $evaluation, User $approver, ?string $notes = null): CompletionEvaluation
    {
        $evaluation->update([
            'is_manually_approved' => true,
            'manual_approved_by' => $approver->id,
            'manual_approved_at' => now(),
        ]);

        // Re-evaluate with manual approval applied
        $course = $evaluation->course;
        $student = $evaluation->user;
        if ($course && $student) {
            return $this->evaluate($course, $student, $evaluation->batch, $evaluation->rules_snapshot);
        }

        return $evaluation;
    }

    /**
     * Extract normalized array rules from course model.
     *
     * @return array<string, mixed>
     */
    private function extractCourseRules(Course $course): array
    {
        $raw = $course->getRawOriginal('completion_rules');

        if ($raw === null || $raw === '' || $raw === '[]' || $raw === '{}') {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($raw) && method_exists($raw, 'toArray')) {
            return $raw->toArray();
        }

        return [];
    }
}
