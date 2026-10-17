<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recurrence schedule rule for generating batch sessions.
 *
 * @property int $id
 * @property string $ulid
 * @property int $batch_id
 * @property array<int> $days_of_week
 * @property string $start_time
 * @property string $end_time
 * @property string $timezone
 * @property CarbonInterface $until_date
 * @property bool $exclude_holidays
 * @property-read ?Batch $batch
 */
class BatchSchedule extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'batch_id',
        'days_of_week',
        'start_time',
        'end_time',
        'timezone',
        'until_date',
        'exclude_holidays',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'until_date' => 'date',
            'exclude_holidays' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
