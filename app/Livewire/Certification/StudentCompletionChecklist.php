<?php

declare(strict_types=1);

namespace App\Livewire\Certification;

use App\Domain\Catalog\Models\Course;
use App\Domain\Certification\Actions\IssueCourseCertificateAction;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Certification\Services\CompletionEvaluator;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.student')]
class StudentCompletionChecklist extends Component
{
    public Course $course;

    public ?Batch $batch = null;

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $courseSlug, ?int $batchId = null): void
    {
        $this->course = Course::query()->where('slug', $courseSlug)->firstOrFail();
        if ($batchId) {
            $this->batch = Batch::find($batchId);
        }
    }

    public function claimCertificate(IssueCourseCertificateAction $action): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        /** @var User $student */
        $student = Auth::user();

        try {
            $cert = $action->execute($this->course, $student, $this->batch);
            $this->feedbackMessage = "Congratulations! Certificate #{$cert->certificate_number} has been conferred and issued.";
            $this->dispatch('certificate-claimed', certificateId: $cert->id);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Unable to issue certificate.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error issuing certificate: '.$e->getMessage();
        }
    }

    public function render(CompletionEvaluator $evaluator): View
    {
        /** @var User $student */
        $student = Auth::user();

        // Run evaluation
        $evaluation = $evaluator->evaluate($this->course, $student, $this->batch);

        /** @var ?Certificate $existingCertificate */
        $existingCertificate = Certificate::query()
            ->where('course_id', $this->course->id)
            ->where('user_id', $student->id)
            ->where('status', 'valid')
            ->first();

        return view('livewire.certification.student-completion-checklist', [
            'evaluation' => $evaluation,
            'existingCertificate' => $existingCertificate,
        ]);
    }
}
