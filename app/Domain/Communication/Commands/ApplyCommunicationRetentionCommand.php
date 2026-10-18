<?php

declare(strict_types=1);

namespace App\Domain\Communication\Commands;

use App\Domain\Communication\Models\BatchMessage;
use Illuminate\Console\Command;

class ApplyCommunicationRetentionCommand extends Command
{
    protected $signature = 'lms:apply-communication-retention {--days=180 : Retention threshold in days}';

    protected $description = 'Enforce communication retention policy by archiving older batch messages';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        $count = BatchMessage::query()
            ->whereNull('archived_at')
            ->where('created_at', '<', $threshold)
            ->update(['archived_at' => now()]);

        $this->info("Archived {$count} communication message(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
