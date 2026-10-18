<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventInvitation;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportEventInvitationsAction extends BaseAction
{
    /**
     * Import invitees from raw CSV data or structured array.
     * Generates signed single-use invite records.
     *
     * @param  string|array<int, array{email: string, name?: string}>  $data
     * @return Collection<int, EventInvitation>
     */
    public function execute(Event $event, string|array $data, ?int $expiresInDays = 14): Collection
    {
        return DB::transaction(function () use ($event, $data, $expiresInDays): Collection {
            $records = is_array($data) ? $data : $this->parseCsv($data);
            $invitations = collect();
            $expiresAt = $expiresInDays ? now()->addDays($expiresInDays) : null;

            foreach ($records as $row) {
                $email = trim($row['email']);
                $name = trim($row['name'] ?? explode('@', $email)[0]);

                if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                /** @var EventInvitation $invitation */
                $invitation = EventInvitation::create([
                    'event_id' => $event->id,
                    'email' => $email,
                    'name' => $name,
                    'token' => Str::random(48),
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                ]);

                $invitations->push($invitation);
            }

            return $invitations;
        });
    }

    /**
     * @return array<int, array{email: string, name: string}>
     */
    private function parseCsv(string $csvContent): array
    {
        $rows = [];
        $lines = explode("\n", trim($csvContent));
        $header = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $fields = str_getcsv($line);
            if (! $header) {
                // If first row has headers
                $lower = array_map('strtolower', $fields);
                if (in_array('email', $lower, true)) {
                    $header = $lower;

                    continue;
                }
                // No header row
                $header = ['email', 'name'];
            }

            $item = [];
            foreach ($header as $idx => $key) {
                $item[$key] = $fields[$idx] ?? '';
            }

            if (! empty($item['email'])) {
                $rows[] = [
                    'email' => $item['email'],
                    'name' => $item['name'] ?? '',
                ];
            }
        }

        return $rows;
    }
}
