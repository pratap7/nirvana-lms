<?php

declare(strict_types=1);

namespace App\Support\Actions;

/**
 * Base action class providing static run() dispatch.
 */
abstract class BaseAction
{
    /**
     * Dispatch the action statically.
     * Supports either handle() or execute() on the child class.
     */
    public static function run(mixed ...$arguments): mixed
    {
        /** @var static $instance */
        $instance = app(static::class);

        if (method_exists($instance, 'handle')) {
            return $instance->handle(...$arguments);
        }

        if (method_exists($instance, 'execute')) {
            return $instance->execute(...$arguments);
        }

        throw new \BadMethodCallException('Action ['.static::class.'] must implement handle() or execute().');
    }
}
