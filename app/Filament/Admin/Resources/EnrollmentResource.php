<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Enrollment\Actions\DropEnrollmentAction;
use App\Domain\Enrollment\Actions\TransferEnrollmentAction;
use App\Domain\Enrollment\Actions\TransitionEnrollmentAction;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Filament\Admin\Resources\EnrollmentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Students & Admissions';

    protected static ?string $navigationLabel = 'Enrollments & Review Queue';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = Enrollment::whereIn('status', [
            EnrollmentStatus::Requested->value,
            EnrollmentStatus::UnderReview->value,
        ])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Student & Course')
                    ->schema([
                        Forms\Components\Select::make('student_id')
                            ->label('Student')
                            ->relationship('student', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (User $user): string => $user->full_name." ({$user->email})")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('batch_id')
                            ->label('Batch')
                            ->relationship('batch', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options(EnrollmentStatus::options())
                            ->default(EnrollmentStatus::Requested->value)
                            ->required(),

                        Forms\Components\DateTimePicker::make('seat_hold_expires_at')
                            ->label('Seat Hold Expiration')
                            ->nullable(),

                        Forms\Components\TextInput::make('waitlist_position')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\Toggle::make('prerequisites_verified')
                            ->label('Prerequisites Verified by Moderator'),

                        Forms\Components\Toggle::make('consent_given')
                            ->label('Consent Form Signed')
                            ->default(true),

                        Forms\Components\Textarea::make('internal_notes')
                            ->columnSpanFull()
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->description(fn (Enrollment $record): string => $record->student ? $record->student->email : ''),

                Tables\Columns\TextColumn::make('course.name')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('batch.name')
                    ->label('Batch')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'info' => EnrollmentStatus::Requested->value,
                        'warning' => EnrollmentStatus::UnderReview->value,
                        'primary' => EnrollmentStatus::Approved->value,
                        'success' => EnrollmentStatus::Active->value,
                        'gray' => EnrollmentStatus::Waitlisted->value,
                        'danger' => [EnrollmentStatus::Rejected->value, EnrollmentStatus::Cancelled->value, EnrollmentStatus::Dropped->value],
                    ]),

                Tables\Columns\TextColumn::make('seat_hold_expires_at')
                    ->label('Seat Hold')
                    ->dateTime('M d, H:i')
                    ->placeholder('None')
                    ->sortable(),

                Tables\Columns\TextColumn::make('waitlist_position')
                    ->label('WL #')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\IconColumn::make('prerequisites_verified')
                    ->label('Prereqs')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(EnrollmentStatus::options()),

                Tables\Filters\SelectFilter::make('batch')
                    ->relationship('batch', 'name'),

                Tables\Filters\TernaryFilter::make('prerequisites_verified')
                    ->label('Prerequisites Verified'),

                Tables\Filters\TernaryFilter::make('is_offline_registration')
                    ->label('Registration Mode')
                    ->trueLabel('Offline Registration')
                    ->falseLabel('Online Registration'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Enrollment $record): bool => in_array($record->status, [
                        EnrollmentStatus::Requested,
                        EnrollmentStatus::UnderReview,
                        EnrollmentStatus::Waitlisted,
                    ], true))
                    ->form([
                        Forms\Components\TextInput::make('hold_hours')
                            ->label('Seat Hold Duration (Hours)')
                            ->default(24)
                            ->numeric()
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
                            ->nullable(),
                    ])
                    ->action(function (Enrollment $record, array $data): void {
                        /** @var TransitionEnrollmentAction $transition */
                        $transition = app(TransitionEnrollmentAction::class);
                        $transition->execute(
                            enrollment: $record,
                            newStatus: EnrollmentStatus::Approved,
                            actor: auth()->user(),
                            reason: 'Approved by moderator',
                            metadata: ['hold_hours' => (int) $data['hold_hours']]
                        );

                        Notification::make()
                            ->title('Enrollment approved. Seat hold placed.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Enrollment $record): bool => in_array($record->status, [
                        EnrollmentStatus::Requested,
                        EnrollmentStatus::UnderReview,
                    ], true))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (Enrollment $record, array $data): void {
                        /** @var TransitionEnrollmentAction $transition */
                        $transition = app(TransitionEnrollmentAction::class);
                        $transition->execute(
                            enrollment: $record,
                            newStatus: EnrollmentStatus::Rejected,
                            actor: auth()->user(),
                            reason: $data['rejection_reason']
                        );

                        Notification::make()
                            ->title('Enrollment rejected.')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn (Enrollment $record): bool => in_array($record->status, [
                        EnrollmentStatus::Approved,
                        EnrollmentStatus::PaymentPending,
                    ], true))
                    ->action(function (Enrollment $record): void {
                        /** @var TransitionEnrollmentAction $transition */
                        $transition = app(TransitionEnrollmentAction::class);
                        $transition->execute(
                            enrollment: $record,
                            newStatus: EnrollmentStatus::Active,
                            actor: auth()->user(),
                            reason: 'Activated by moderator / payment received'
                        );

                        Notification::make()
                            ->title('Enrollment active.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('drop')
                    ->label('Drop')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('danger')
                    ->visible(fn (Enrollment $record): bool => $record->status === EnrollmentStatus::Active)
                    ->form([
                        Forms\Components\Textarea::make('drop_reason')
                            ->label('Reason for Dropping')
                            ->required(),
                    ])
                    ->action(function (Enrollment $record, array $data): void {
                        /** @var DropEnrollmentAction $dropAction */
                        $dropAction = app(DropEnrollmentAction::class);
                        $dropAction->execute(
                            enrollment: $record,
                            actor: auth()->user(),
                            reason: $data['drop_reason']
                        );

                        Notification::make()
                            ->title('Student dropped from batch. Waitlist promoted if applicable.')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('transfer')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn (Enrollment $record): bool => in_array($record->status, [
                        EnrollmentStatus::Approved,
                        EnrollmentStatus::Active,
                    ], true))
                    ->form([
                        Forms\Components\Select::make('target_batch_id')
                            ->label('Target Batch')
                            ->options(function (Enrollment $record) {
                                return Batch::where('course_id', $record->course_id)
                                    ->where('id', '!=', $record->batch_id)
                                    ->pluck('name', 'id');
                            })
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Transfer Reason')
                            ->nullable(),
                    ])
                    ->action(function (Enrollment $record, array $data): void {
                        /** @var TransferEnrollmentAction $transferAction */
                        $transferAction = app(TransferEnrollmentAction::class);
                        $targetBatch = Batch::findOrFail($data['target_batch_id']);

                        $transferAction->execute(
                            sourceEnrollment: $record,
                            targetBatch: $targetBatch,
                            actor: auth()->user(),
                            reason: $data['reason'] ?? null
                        );

                        Notification::make()
                            ->title('Enrollment transferred successfully.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('view_timeline')
                    ->label('Timeline')
                    ->icon('heroicon-o-clock')
                    ->url(fn (Enrollment $record): string => route('enrollments.timeline', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Collection $records): void {
                        /** @var TransitionEnrollmentAction $transition */
                        $transition = app(TransitionEnrollmentAction::class);
                        $count = 0;

                        /** @var Enrollment $enrollment */
                        foreach ($records as $enrollment) {
                            if ($enrollment->canTransitionTo(EnrollmentStatus::Approved)) {
                                $transition->execute(
                                    enrollment: $enrollment,
                                    newStatus: EnrollmentStatus::Approved,
                                    actor: auth()->user(),
                                    reason: 'Bulk approved by moderator'
                                );
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} enrollments approved.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\BulkAction::make('bulk_reject')
                    ->label('Reject Selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        /** @var TransitionEnrollmentAction $transition */
                        $transition = app(TransitionEnrollmentAction::class);
                        $count = 0;

                        /** @var Enrollment $enrollment */
                        foreach ($records as $enrollment) {
                            if ($enrollment->canTransitionTo(EnrollmentStatus::Rejected)) {
                                $transition->execute(
                                    enrollment: $enrollment,
                                    newStatus: EnrollmentStatus::Rejected,
                                    actor: auth()->user(),
                                    reason: $data['rejection_reason']
                                );
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} enrollments rejected.")
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollments::route('/'),
            'create' => Pages\CreateEnrollment::route('/create'),
            'edit' => Pages\EditEnrollment::route('/{record}/edit'),
        ];
    }
}
