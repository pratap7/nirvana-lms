<?php

declare(strict_types=1);

namespace App\Support\Concerns;

/**
 * Scopes queries by branch_id for multi-branch support.
 *
 * Models using this trait must have a `branch_id` column.
 */
trait ScopedByBranch
{
    /**
     * Scope query to a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where("{$this->getTable()}.branch_id", $branchId);
    }

    /**
     * Scope query to multiple branches.
     *
     * @param  array<int>  $branchIds
     */
    public function scopeForBranches($query, array $branchIds)
    {
        return $query->whereIn("{$this->getTable()}.branch_id", $branchIds);
    }
}
