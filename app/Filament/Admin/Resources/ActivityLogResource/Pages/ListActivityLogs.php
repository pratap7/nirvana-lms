<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActivityLogResource\Pages;

use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\ActivityLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Response;
use Spatie\Activitylog\Models\Activity;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export Audit Log (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $fileName = 'pramana_audit_log_'.now()->format('Y_m_d_His').'.csv';

                    $headers = [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                        'Pragma' => 'no-cache',
                        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                        'Expires' => '0',
                    ];

                    $callback = function (): void {
                        $file = fopen('php://output', 'w');
                        if (! $file) {
                            return;
                        }

                        fputcsv($file, ['ID', 'Timestamp (UTC)', 'Event', 'Actor', 'Subject Type', 'Subject ID', 'IP Address', 'User Agent']);

                        Activity::with('causer')->chunk(200, function ($records) use ($file): void {
                            foreach ($records as $log) {
                                $actor = $log->causer instanceof User ? $log->causer->email : 'System';

                                fputcsv($file, [
                                    $log->id,
                                    $log->created_at?->toIso8601String(),
                                    $log->description,
                                    $actor,
                                    $log->subject_type,
                                    $log->subject_id,
                                    $log->properties['ip_address'] ?? '',
                                    $log->properties['user_agent'] ?? '',
                                ]);
                            }
                        });

                        fclose($file);
                    };

                    return Response::stream($callback, 200, $headers);
                }),
        ];
    }
}
