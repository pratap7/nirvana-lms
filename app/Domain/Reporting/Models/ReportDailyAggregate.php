<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-optimized reporting summary aggregate model (Section H).
 * Refreshed by scheduled background jobs to enable fast dashboard rendering.
 *
 * @property int $id
 * @property CarbonInterface $date
 * @property string $metric_group
 * @property string $dimension_key
 * @property float $numeric_value
 * @property ?float $secondary_value
 * @property ?array<string, mixed> $metadata
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 *
 * @method static Builder<static> forMetricGroup(string $metricGroup)
 * @method static Builder<static> forDateRange(CarbonInterface $from, CarbonInterface $to)
 */
class ReportDailyAggregate extends Model
{
    use HasFactory;

    protected $table = 'report_daily_aggregates';

    protected $fillable = [
        'date',
        'metric_group',
        'dimension_key',
        'numeric_value',
        'secondary_value',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'numeric_value' => 'float',
            'secondary_value' => 'float',
            'metadata' => 'array',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForMetricGroup(Builder $query, string $metricGroup): Builder
    {
        return $query->where('metric_group', $metricGroup);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDateRange(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }
}
