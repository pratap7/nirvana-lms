<?php

declare(strict_types=1);

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\BatchMessage;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;

/**
 * Flag Batch Message Action (Section H).
 * Allows students or teachers to flag inappropriate or concerning messages for administrative audit.
 */
class FlagBatchMessageAction extends BaseAction
{
    public function execute(BatchMessage $message, User $flagger, string $reason): BatchMessage
    {
        $message->flag($flagger, $reason);

        return $message;
    }
}
