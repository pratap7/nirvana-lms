<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Enrollment\Actions\ReleaseExpiredSeatHoldsAction;
use Illuminate\Console\Command;

class ReleaseExpiredSeatHoldsCommand extends Command
{
    protected $signature = 'enrollment:release-expired-holds';

    protected $description = 'Release expired seat holds and promote waitlisted students';

    public function handle(ReleaseExpiredSeatHoldsAction $action): int
    {
        $this->info('Checking for expired seat holds...');

        $result = $action->execute();

        $this->info("Released {$result['released_count']} expired seat holds.");
        $this->info("Promoted {$result['promoted_count']} waitlisted students.");

        return Command::SUCCESS;
    }
}
