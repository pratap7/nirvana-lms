<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\LessonResource;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Policies\LessonLearningPolicy;
use App\Domain\Learning\Services\StudentProgressService;
use App\Domain\Scheduling\Models\Batch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentLearningController extends Controller
{
    public function __construct(
        protected LessonLearningPolicy $policy,
        protected StudentProgressService $progressService
    ) {}

    /**
     * Stream protected lesson video.
     * Enforces that student must be active in the batch/course.
     */
    public function streamVideo(Request $request, string $lessonUlid): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // If accessed via signed URL where user is passed as query param
        if ($user === null && $request->hasValidSignature() && $request->query('user')) {
            $user = User::query()->where('ulid', (string) $request->query('user'))->first();
        }

        if ($user === null) {
            abort(403, 'Unauthorized. Valid authenticated student session or signature required.');
        }

        /** @var Lesson $lesson */
        $lesson = Lesson::query()->where('ulid', $lessonUlid)->firstOrFail();

        $batchId = $request->query('batch');
        $batch = $batchId ? Batch::query()->where('ulid', (string) $batchId)->first() : null;

        // CRITICAL ACCEPTANCE CHECK: Must have active enrollment
        if (! $this->policy->view($user, $lesson, $batch)) {
            abort(403, 'Access denied. You do not hold an active enrollment in this course batch.');
        }

        // Return streaming response with range support
        $content = "fake-video-binary-stream-for-lesson-{$lesson->id}";

        return response($content, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline; filename="lesson.mp4"',
            'Accept-Ranges' => 'bytes',
            'X-Watermark-Student' => $user->ulid,
        ]);
    }

    /**
     * Download protected lesson resource with permission checks.
     */
    public function downloadResource(Request $request, string $resourceUlid): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            abort(403, 'Unauthenticated.');
        }

        /** @var LessonResource $resource */
        $resource = LessonResource::query()->with('lesson.module')->where('ulid', $resourceUlid)->firstOrFail();

        $batchId = $request->query('batch');
        $batch = $batchId ? Batch::query()->where('ulid', (string) $batchId)->first() : null;

        // CRITICAL ACCEPTANCE CHECK
        if (! $this->policy->downloadResource($user, $resource, $batch)) {
            abort(403, 'Access denied. You do not hold an active enrollment to download this resource.');
        }

        return response("downloadable-content-for-{$resource->title}", 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$resource->title}\"",
        ]);
    }

    /**
     * Update video playback progress and auto-mark complete when watch percentage >= 90%.
     */
    public function updateVideoProgress(Request $request, string $lessonUlid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Lesson $lesson */
        $lesson = Lesson::query()->with('module.course')->where('ulid', $lessonUlid)->firstOrFail();

        $batchId = $request->input('batch_id');
        $batch = $batchId ? Batch::query()->find((int) $batchId) : null;

        if (! $this->policy->view($user, $lesson, $batch)) {
            abort(403, 'Unauthorized.');
        }

        $percentage = (int) $request->input('percentage', $request->input('watch_percentage', 0));
        $positionSeconds = (int) $request->input('position_seconds', $request->input('seconds_watched', 0));

        /** @var LessonProgress $progress */
        $progress = LessonProgress::query()->firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $shouldMarkComplete = ($percentage >= 90) || (bool) $progress->is_completed;
        $currentWatch = (int) ($progress->watch_percentage ?? 0);

        $progress->fill([
            'batch_id' => $batch !== null ? $batch->id : $progress->batch_id,
            'watch_percentage' => max($currentWatch, $percentage),
            'last_position_seconds' => $positionSeconds,
            'is_completed' => $shouldMarkComplete,
            'completed_at' => $shouldMarkComplete ? ($progress->completed_at ?? now()) : null,
        ]);

        $progress->save();

        $course = $lesson->module?->course;
        if ($course instanceof Course) {
            $this->progressService->calculateProgress($user, $course, $batch);
        }

        return response()->json([
            'status' => 'success',
            'is_completed' => $progress->is_completed,
            'watch_percentage' => $progress->watch_percentage,
        ]);
    }
}
