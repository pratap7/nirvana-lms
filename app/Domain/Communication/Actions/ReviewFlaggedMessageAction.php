<?php

declare(strict_types=1);

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\BatchMessage;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Review Flagged Message Action (Section H).
 * Strictly audited admin review of flagged messages, requiring 'message.audit' or 'communication.review' permission.
 */
class ReviewFlaggedMessageAction extends BaseAction
{
    /**
     * @throws AuthorizationException
     */
    public function execute(BatchMessage $message, User $admin, string $notes): BatchMessage
    {
        // Enforce audited permission
        $hasPermission = $admin->can('message.audit')
            || $admin->can('communication.review')
            || $admin->hasRole('Admin');

        if (! $hasPermission) {
            throw new AuthorizationException('Unauthorized: Reviewing flagged communication requires audited permission [message.audit].');
        }

        $message->review($admin, $notes);

        activity('communication')
            ->performedOn($message)
            ->causedBy($admin)
            ->withProperties([
                'flagged_by' => $message->flagged_by,
                'flag_reason' => $message->flag_reason,
                'admin_review_notes' => $notes,
            ])
            ->log('Flagged message was reviewed and adjudicated by administrator.');

        return $message;
    }
}
