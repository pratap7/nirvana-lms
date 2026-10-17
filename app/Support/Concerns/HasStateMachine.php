<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Exceptions\IllegalStateTransitionException;
use BackedEnum;

/**
 * Provides explicit state machine behaviour for Eloquent models.
 *
 * Models using this trait must:
 * 1. Have a status column (configurable via getStatusColumn())
 * 2. Use a BackedEnum for status that implements canTransitionTo()
 *
 * Every transition is audit-logged via spatie/laravel-activitylog.
 */
trait HasStateMachine
{
    /**
     * The column that holds the status value.
     */
    protected function getStatusColumn(): string
    {
        return 'status';
    }

    /**
     * Transition the model to a new status.
     *
     * @throws IllegalStateTransitionException
     */
    public function transitionTo(BackedEnum $newStatus): void
    {
        $column = $this->getStatusColumn();
        $current = $this->{$column};

        if (! $current->canTransitionTo($newStatus)) {
            throw new IllegalStateTransitionException(
                model: static::class,
                from: $current,
                to: $newStatus,
            );
        }

        $oldValue = $current->value;
        $this->{$column} = $newStatus;
        $this->save();

        if (method_exists($this, 'logStateTransition')) {
            $this->logStateTransition($oldValue, $newStatus->value);
        } else {
            activity()
                ->performedOn($this)
                ->withProperties([
                    'column' => $column,
                    'from' => $oldValue,
                    'to' => $newStatus->value,
                ])
                ->log('status_transition');
        }
    }

    /**
     * Check if a transition to the given status is legal.
     */
    public function canTransitionTo(BackedEnum $newStatus): bool
    {
        $column = $this->getStatusColumn();
        $current = $this->{$column};

        return $current->canTransitionTo($newStatus);
    }
}
