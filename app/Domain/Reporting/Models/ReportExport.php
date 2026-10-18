<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * Report Export Ledger Entry (Section H).
 * Tracks background queued exports, status, row count, file path, and signed download links.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $report_type
 * @property string $format
 * @property ?array<string, mixed> $filters
 * @property string $status
 * @property ?string $file_path
 * @property string $file_name
 * @property int $row_count
 * @property int $file_size_bytes
 * @property ?string $error_message
 * @property CarbonInterface $expires_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ?User $user
 */
class ReportExport extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'report_exports';

    protected $fillable = [
        'ulid',
        'user_id',
        'report_type',
        'format',
        'filters',
        'status',
        'file_path',
        'file_name',
        'row_count',
        'file_size_bytes',
        'error_message',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'row_count' => 'integer',
            'file_size_bytes' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markCompleted(string $filePath, int $rowCount, int $fileSizeBytes): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'row_count' => $rowCount,
            'file_size_bytes' => $fileSizeBytes,
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Generate an expiring signed download URL.
     */
    public function getDownloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'reporting.exports.download',
            $this->expires_at,
            ['export' => $this->ulid]
        );
    }
}
