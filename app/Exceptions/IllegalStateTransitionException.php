<?php

declare(strict_types=1);

namespace App\Exceptions;

use BackedEnum;
use RuntimeException;

/**
 * Thrown when a state machine transition is illegal.
 */
final class IllegalStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $model,
        public readonly BackedEnum $from,
        public readonly BackedEnum $to,
    ) {
        parent::__construct(
            "Illegal state transition on [{$model}]: [{$from->value}] → [{$to->value}]",
        );
    }
}
