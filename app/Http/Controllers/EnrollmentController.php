<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Actions\RequestEnrollmentAction;
use App\Domain\Enrollment\Exceptions\BatchFullException;
use App\Domain\Enrollment\Exceptions\DuplicateEnrollmentException;
use App\Domain\Enrollment\Exceptions\PrerequisiteNotMetException;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    /**
     * Submit an online enrollment request with consent capture.
     */
    public function store(Request $request, RequestEnrollmentAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'batch_id' => 'nullable|exists:batches,id',
            'course_id' => 'nullable|exists:courses,id',
            'consent' => 'required|accepted',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = $request->user();

        // Resolve batch: explicit batch or default rolling batch for course
        if (! empty($validated['batch_id'])) {
            $batch = Batch::findOrFail($validated['batch_id']);
        } elseif (! empty($validated['course_id'])) {
            $course = Course::findOrFail($validated['course_id']);
            $batch = Batch::getDefaultBatchForCourse($course);
        } else {
            abort(422, 'Either batch_id or course_id is required.');
        }

        try {
            $enrollment = $action->execute(
                student: $user,
                batch: $batch,
                consentGiven: true,
                consentIp: $request->ip(),
                notes: $validated['notes'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Enrollment request received successfully.',
                    'enrollment' => $enrollment->load('batch', 'course'),
                ], 201);
            }

            return redirect()->route('enrollments.timeline', $enrollment)
                ->with('success', 'Your enrollment request has been registered.');
        } catch (DuplicateEnrollmentException|PrerequisiteNotMetException|BatchFullException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['enrollment' => $e->getMessage()]);
        }
    }

    /**
     * View student enrollment status timeline.
     */
    public function timeline(Enrollment $enrollment): View|JsonResponse
    {
        $enrollment->load(['batch', 'course', 'statusHistories.changer']);

        if (request()->wantsJson()) {
            return response()->json([
                'enrollment' => $enrollment,
                'timeline' => $enrollment->statusHistories->map(fn ($history) => [
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'changed_by' => $history->changer ? $history->changer->name : 'System',
                    'reason' => $history->reason,
                    'metadata' => $history->metadata,
                    'created_at' => $history->created_at?->toIso8601String(),
                ]),
            ]);
        }

        return view('enrollment.timeline', [
            'enrollment' => $enrollment,
            'timeline' => $enrollment->statusHistories,
        ]);
    }
}
