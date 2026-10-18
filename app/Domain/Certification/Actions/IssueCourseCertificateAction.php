<?php

declare(strict_types=1);

namespace App\Domain\Certification\Actions;

use App\Domain\Catalog\Models\Course;
use App\Domain\Certification\Jobs\GenerateCertificatePdfJob;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Certification\Models\CertificateTemplate;
use App\Domain\Certification\Services\CompletionEvaluator;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueCourseCertificateAction extends BaseAction
{
    public function __construct(
        protected CompletionEvaluator $evaluator
    ) {}

    /**
     * Issue an official course completion certificate based on evaluated checklist rules.
     */
    public function execute(
        Course $course,
        User $student,
        ?Batch $batch = null,
        ?CertificateTemplate $template = null
    ): Certificate {
        // 1. Evaluate completion rules
        $evaluation = $this->evaluator->evaluate($course, $student, $batch);

        if (! $evaluation->is_passed) {
            $unmetReasons = collect($evaluation->checklist_results)
                ->where('is_satisfied', false)
                ->pluck('reason')
                ->implode(' ');

            throw ValidationException::withMessages([
                'completion' => "Student has not satisfied all completion criteria: {$unmetReasons}",
            ]);
        }

        // 2. Prevent duplicate active certificate
        $existing = Certificate::query()
            ->where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->where('status', 'valid')
            ->first();

        if ($existing) {
            return $existing;
        }

        // 3. Resolve template
        $templateModel = $template ?? CertificateTemplate::query()
            ->where('course_id', $course->id)
            ->first() ?? CertificateTemplate::query()->where('is_default', true)->first();

        // 4. Generate unique human-readable certificate number (e.g. YOG-2027-000123)
        $year = now()->format('Y');
        $seq = str_pad((string) (Certificate::count() + 1), 6, '0', STR_PAD_LEFT);
        $certNumber = "YOG-{$year}-{$seq}";

        // 5. Create Certificate record
        /** @var Certificate $certificate */
        $certificate = Certificate::create([
            'certificate_number' => $certNumber,
            'public_token' => 'cert_'.Str::random(40),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch?->id,
            'certifiable_type' => Course::class,
            'certifiable_id' => $course->id,
            'template_id' => $templateModel?->id,
            'issue_date' => now()->toDateString(),
            'status' => 'valid',
            'template_name' => $templateModel ? $templateModel->slug : 'classical_yoga_v1',
            'rules_snapshot' => $evaluation->rules_snapshot,
            'evaluation_snapshot' => $evaluation->checklist_results,
            'metadata' => [
                'course_title' => $course->title,
                'student_name' => $student->full_name,
                'evaluated_at' => now()->toIso8601String(),
            ],
        ]);

        // Generate QR code
        $certificate->generateQrCode();

        // Dispatch queued PDF generation job
        GenerateCertificatePdfJob::dispatch($certificate);

        return $certificate;
    }
}
