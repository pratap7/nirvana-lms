<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\LessonResource;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class PrivateFileDownloadController extends Controller
{
    /**
     * Generate an expiring temporary signed URL for a private file.
     */
    public static function signedDownloadUrl(string $type, int|string $id, int $minutes = 30): string
    {
        return URL::temporarySignedRoute(
            'secure.download',
            now()->addMinutes($minutes),
            ['type' => $type, 'id' => $id]
        );
    }

    /**
     * Download a private file via signed URL with strict authorization checks.
     */
    public function download(Request $request, string $type, string $id): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'The download link is invalid or has expired.');
        }

        /** @var User|null $user */
        $user = $request->user();

        return match ($type) {
            'resource' => $this->downloadCourseResource((int) $id, $user),
            'submission' => $this->downloadSubmission((int) $id, $user),
            'invoice' => $this->downloadInvoice((int) $id, $user),
            default => abort(404, 'Unknown private resource type.'),
        };
    }

    protected function downloadCourseResource(int $resourceId, ?User $user): Response
    {
        $resource = LessonResource::with('lesson.module.course.teachers')->findOrFail($resourceId);
        $course = $resource->lesson?->module?->course;

        // Verify active enrollment or teacher/admin access
        if ($user && $course instanceof Course) {
            $isAuthorized = $user->hasRole(['admin', 'super_admin'])
                || $user->enrollments()->where('course_id', $course->id)->exists()
                || $course->teachers->contains('id', $user->id);

            if (! $isAuthorized) {
                abort(403, 'You do not have permission to download this course resource.');
            }
        }

        $disk = Storage::disk('local');
        $path = $resource->url_or_path;

        if (! $disk->exists($path)) {
            abort(404, 'File not found on storage.');
        }

        return response()->download(
            $disk->path($path),
            $resource->title ?: basename($path)
        );
    }

    protected function downloadSubmission(int $submissionId, ?User $user): Response
    {
        if (! $user) {
            abort(401, 'Authentication required to access student submission files.');
        }

        $submission = AssignmentSubmission::with(['assignment.course.teachers'])->findOrFail($submissionId);

        // Only the submitting student, course teacher, or admin can access
        $isOwner = $submission->user_id === $user->id;
        $isTeacher = $submission->assignment?->course?->teachers->contains('id', $user->id) ?? false;
        $isAdmin = $user->hasRole(['admin', 'super_admin']);

        if (! ($isOwner || $isTeacher || $isAdmin)) {
            abort(403, 'Unauthorized access to student submission (IDOR protected).');
        }

        $filePath = $submission->file_path;
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            abort(404, 'Submission attachment file not found.');
        }

        return response()->download(
            Storage::disk('local')->path($filePath),
            "submission-{$submission->id}.pdf"
        );
    }

    protected function downloadInvoice(int $invoiceId, ?User $user): Response
    {
        if (! $user) {
            abort(401, 'Authentication required to access invoices.');
        }

        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->student_id !== $user->id && ! $user->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Unauthorized access to invoice (IDOR protected).');
        }

        // Return inline view or PDF download
        return redirect()->route('invoices.view', $invoice->id);
    }
}
