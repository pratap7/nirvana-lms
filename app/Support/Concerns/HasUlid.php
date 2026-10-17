<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Adds a ULID-based public identifier to Eloquent models.
 *
 * - Auto-generates ULID on creation
 * - Routes resolve by ULID (never exposing auto-increment IDs)
 * - Provides a scopeByUlid() query scope
 */
trait HasUlid
{
    public static function bootHasUlid(): void
    {
        static::creating(function ($model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Scope query to a specific ULID.
     */
    public function scopeByUlid($query, string $ulid)
    {
        return $query->where('ulid', $ulid);
    }

    /**
     * Find a model by its ULID or fail.
     */
    public static function findByUlid(string $ulid): static
    {
        $model = static::where('ulid', $ulid)->firstOrFail();
        assert($model instanceof static);

        return $model;
    }
}
