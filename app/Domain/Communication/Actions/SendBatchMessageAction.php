<?php

declare(strict_types=1);

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\BatchMessage;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use Illuminate\Validation\ValidationException;

/**
 * Send Batch Message Action (Section H).
 * Strictly enforces that teacher-student messaging is restricted to shared active batches.
 */
class SendBatchMessageAction extends BaseAction
{
    /**
     * Send a direct message within a batch scope.
     *
     * @throws ValidationException If sender or recipient do not share the batch
     */
    public function execute(
        User $sender,
        Batch $batch,
        string $messageText,
        ?User $recipient = null
    ): BatchMessage {
        $messageText = trim($messageText);
        if ($messageText === '') {
            throw ValidationException::withMessages([
                'message' => 'Message body cannot be empty.',
            ]);
        }

        // 1. Verify that sender belongs to this batch (as assigned teacher or active enrolled student)
        if (! $this->isUserInBatch($sender, $batch)) {
            throw ValidationException::withMessages([
                'sender' => 'Sender does not participate in this batch.',
            ]);
        }

        // 2. Verify that recipient (if directed) belongs to the SAME batch
        if ($recipient && ! $this->isUserInBatch($recipient, $batch)) {
            throw ValidationException::withMessages([
                'recipient' => 'Teacher-student messaging is strictly limited to shared batches.',
            ]);
        }

        /** @var BatchMessage $message */
        $message = BatchMessage::create([
            'batch_id' => $batch->id,
            'sender_id' => $sender->id,
            'recipient_id' => $recipient?->id,
            'message' => $messageText,
            'read_at' => null,
        ]);

        return $message;
    }

    /**
     * Check if a user is an active student or assigned teacher in the given batch.
     */
    protected function isUserInBatch(User $user, Batch $batch): bool
    {
        // A. Is student actively enrolled?
        $isEnrolledStudent = $batch->enrollments()
            ->where('student_id', $user->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->exists();

        if ($isEnrolledStudent) {
            return true;
        }

        // B. Is user an assigned lead teacher or substitute in this batch?
        $isTeacher = $batch->sessions()
            ->where(function ($query) use ($user): void {
                $query->whereHas('teachers', fn ($t) => $t->where('user_id', $user->id));
            })
            ->exists();

        if ($isTeacher) {
            return true;
        }

        // C. Is user the course creator/lead teacher?
        if ($batch->course && (int) $batch->course->created_by === $user->id) {
            return true;
        }

        return false;
    }
}
