<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\Branch;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Institute holiday dates to skip during batch session generation.
 *
 * @property int $id
 * @property string $ulid
 * @property ?int $branch_id
 * @property string $name
 * @property CarbonInterface $holiday_date
 * @property bool $is_recurring
 * @property-read ?Branch $branch
 */
class Holiday extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'branch_id',
        'name',
        'holiday_date',
        'is_recurring',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
