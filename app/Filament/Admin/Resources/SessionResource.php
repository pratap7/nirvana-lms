<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Actions\ApproveSubstituteAction;
use App\Domain\Scheduling\Actions\AttachSessionRecordingAction;
use App\Domain\Scheduling\Actions\CancelSessionAction;
use App\Domain\Scheduling\Actions\RequestSubstituteAction;
use App\Domain\Scheduling\Actions\RescheduleSessionAction;
use App\Domain\Scheduling\Enums\MeetingProviderType;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Enums\SessionTeacherRole;
use App\Domain\Scheduling\Exceptions\ScheduleConflictException;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Scheduling\Models\Session;
use App\Filament\Admin\Resources\SessionResource\Pages;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SessionResource extends Resource
{
    protected static ?string $model = Session::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Scheduling & Campus';

    protected static ?string $navigationLabel = 'Sessions';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Information')
                    ->schema([
                        Forms\Components\Select::make('batch_id')
                            ->label('Batch')
                            ->relationship('batch', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('session_number')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->options(SessionStatus::options())
                            ->default(SessionStatus::Scheduled->value)
                            ->required(),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->required(),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->required(),

                        Forms\Components\TextInput::make('timezone')
                            ->default(config('app.timezone', 'UTC'))
                            ->required(),

                        Forms\Components\Select::make('venue_id')
                            ->label('Venue')
                            ->relationship('venue', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->nullable(),

                        Forms\Components\Select::make('room_id')
                            ->label('Room')
                            ->options(function (callable $get) {
                                $venueId = $get('venue_id');
                                if (! $venueId) {
                                    return Room::all()->pluck('name', 'id');
                                }

                                return Room::where('venue_id', $venueId)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Online Meeting & Links')
                    ->schema([
                        Forms\Components\Select::make('meeting_provider')
                            ->options(MeetingProviderType::options())
                            ->default(MeetingProviderType::None->value),

                        Forms\Components\TextInput::make('meeting_url')
                            ->url()
                            ->nullable(),

                        Forms\Components\TextInput::make('meeting_id')
                            ->nullable(),

                        Forms\Components\TextInput::make('meeting_passcode')
                            ->nullable(),

                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull()
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_number')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Session $record): string => $record->batch ? $record->batch->name : ''),

                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('teacher')
                    ->label('Teacher')
                    ->getStateUsing(function (Session $record): string {
                        $activeTeacher = $record->getActiveTeacher();
                        if (! $activeTeacher) {
                            return 'Unassigned';
                        }
                        $lead = $record->sessionTeachers()->where('role', SessionTeacherRole::Lead)->first();
                        if ($lead && $lead->user_id !== $activeTeacher->id) {
                            return "{$activeTeacher->name} (Substitute)";
                        }

                        return $activeTeacher->name;
                    }),

                Tables\Columns\TextColumn::make('venue.name')
                    ->label('Location')
                    ->getStateUsing(function (Session $record): string {
                        if ($record->room) {
                            return ($record->venue ? $record->venue->name : 'Venue').' - '.$record->room->name;
                        }
                        if ($record->venue) {
                            return $record->venue->name;
                        }
                        if ($record->meeting_url) {
                            return 'Online ('.$record->meeting_provider->label().')';
                        }

                        return 'TBD';
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => SessionStatus::Scheduled->value,
                        'warning' => SessionStatus::InProgress->value,
                        'success' => SessionStatus::Completed->value,
                        'danger' => SessionStatus::Cancelled->value,
                        'info' => SessionStatus::Rescheduled->value,
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('batch')
                    ->relationship('batch', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options(SessionStatus::options()),
            ])
            ->actions([
                Tables\Actions\Action::make('reschedule')
                    ->label('Reschedule')
                    ->icon('heroicon-o-arrows-pointing-out')
                    ->color('warning')
                    ->form([
                        Forms\Components\DateTimePicker::make('new_starts_at')
                            ->label('New Start Time')
                            ->required(),
                        Forms\Components\DateTimePicker::make('new_ends_at')
                            ->label('New End Time')
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Rescheduling')
                            ->nullable(),
                    ])
                    ->action(function (Session $record, array $data): void {
                        /** @var RescheduleSessionAction $action */
                        $action = app(RescheduleSessionAction::class);
                        try {
                            $action->execute(
                                $record,
                                CarbonImmutable::parse($data['new_starts_at']),
                                CarbonImmutable::parse($data['new_ends_at']),
                                $data['reason'] ?? null,
                                auth()->user()
                            );
                            Notification::make()
                                ->title('Session rescheduled successfully')
                                ->success()
                                ->send();
                        } catch (ScheduleConflictException $e) {
                            Notification::make()
                                ->title('Schedule Conflict')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel_session')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required(),
                    ])
                    ->action(function (Session $record, array $data): void {
                        /** @var CancelSessionAction $action */
                        $action = app(CancelSessionAction::class);
                        $action->execute($record, $data['reason'], auth()->user());
                        Notification::make()
                            ->title('Session cancelled. Other sessions remain unaffected.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('assign_substitute')
                    ->label('Substitute')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('substitute_teacher_id')
                            ->label('Substitute Teacher')
                            ->options(User::role('Teacher')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason / Notes')
                            ->nullable(),
                        Forms\Components\Toggle::make('auto_approve')
                            ->label('Approve Immediately')
                            ->default(true),
                    ])
                    ->action(function (Session $record, array $data): void {
                        /** @var RequestSubstituteAction $requestAction */
                        $requestAction = app(RequestSubstituteAction::class);
                        /** @var ApproveSubstituteAction $approveAction */
                        $approveAction = app(ApproveSubstituteAction::class);

                        $substitute = User::findOrFail($data['substitute_teacher_id']);
                        $requester = auth()->user() ?? $record->getActiveTeacher() ?? $substitute;

                        $sessionTeacher = $requestAction->execute(
                            $record,
                            $substitute,
                            $requester,
                            $data['reason'] ?? null
                        );

                        if (! empty($data['auto_approve'])) {
                            $approveAction->execute($sessionTeacher, auth()->user() ?? $requester);
                            Notification::make()
                                ->title('Substitute approved and assigned.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Substitute requested. Awaiting moderator approval.')
                                ->info()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('attach_recording')
                    ->label('Recording')
                    ->icon('heroicon-o-video-camera')
                    ->color('secondary')
                    ->form([
                        Forms\Components\TextInput::make('recording_url')
                            ->label('Recording URL')
                            ->url()
                            ->required(),
                        Forms\Components\TextInput::make('passcode')
                            ->label('Passcode / Key')
                            ->nullable(),
                        Forms\Components\TextInput::make('duration_minutes')
                            ->label('Duration (Minutes)')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->action(function (Session $record, array $data): void {
                        /** @var AttachSessionRecordingAction $action */
                        $action = app(AttachSessionRecordingAction::class);
                        $action->execute(
                            session: $record,
                            videoUrl: $data['recording_url'],
                            title: 'Session Recording',
                            provider: 'bunny',
                            durationSeconds: isset($data['duration_minutes']) ? (int) $data['duration_minutes'] * 60 : null
                        );
                        Notification::make()
                            ->title('Recording attached to session.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('add_to_calendar')
                    ->label('Calendar')
                    ->icon('heroicon-o-calendar')
                    ->url(fn (Session $record): string => route('calendar.session.ics', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSessions::route('/'),
            'create' => Pages\CreateSession::route('/create'),
            'edit' => Pages\EditSession::route('/{record}/edit'),
        ];
    }
}
