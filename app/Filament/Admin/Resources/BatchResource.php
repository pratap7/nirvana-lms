<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Actions\GenerateSessionsAction;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\BatchSchedule;
use App\Filament\Admin\Resources\BatchResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Scheduling & Campus';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Batch Configuration')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Batch Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('course_id')
                                    ->label('Course')
                                    ->relationship('course', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('delivery_mode')
                                    ->options(collect(DeliveryMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                                    ->default(DeliveryMode::Online->value)
                                    ->required(),
                                Forms\Components\Select::make('status')
                                    ->options(collect(BatchStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                                    ->default(BatchStatus::Draft->value)
                                    ->required(),
                                Forms\Components\DatePicker::make('start_date')
                                    ->required(),
                                Forms\Components\DatePicker::make('end_date')
                                    ->required(),
                                Forms\Components\TextInput::make('capacity')
                                    ->numeric()
                                    ->default(30)
                                    ->required(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Location & Pricing')
                            ->schema([
                                Forms\Components\Select::make('venue_id')
                                    ->label('Campus / Ashram Venue')
                                    ->relationship('venue', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Forms\Components\Select::make('room_id')
                                    ->label('Room / Yoga Shala')
                                    ->relationship('room', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Forms\Components\TextInput::make('fee_override_amount')
                                    ->label('Batch Fee Override (in paise, optional)')
                                    ->numeric()
                                    ->helperText('Leave empty to inherit standard course fee'),
                                Forms\Components\Select::make('fee_override_currency')
                                    ->options(['INR' => 'INR (₹)', 'USD' => 'USD ($)'])
                                    ->default('INR'),
                                Forms\Components\DateTimePicker::make('enrollment_starts_at')
                                    ->label('Enrollment Opens At'),
                                Forms\Components\DateTimePicker::make('enrollment_ends_at')
                                    ->label('Enrollment Closes At'),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('course.name')
                    ->label('Course')
                    ->limit(30)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (BatchStatus|string $state): string => match ($state instanceof BatchStatus ? $state : BatchStatus::tryFrom((string) $state)) {
                        BatchStatus::Draft => 'gray',
                        BatchStatus::Open => 'info',
                        BatchStatus::InProgress => 'success',
                        BatchStatus::Completed => 'primary',
                        BatchStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('delivery_mode')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('sessions_count')
                    ->counts('sessions')
                    ->label('Sessions')
                    ->badge(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                // Generate Sessions Action
                Tables\Actions\Action::make('generate_sessions')
                    ->label('Generate Schedule')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->form([
                        Forms\Components\CheckboxList::make('days_of_week')
                            ->label('Recurring Days of the Week')
                            ->options([
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                                7 => 'Sunday',
                            ])
                            ->default([1, 3, 5])
                            ->columns(4)
                            ->required(),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Session Start Time')
                            ->default('06:00:00')
                            ->required(),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Session End Time')
                            ->default('07:30:00')
                            ->required(),
                        Forms\Components\Select::make('timezone')
                            ->options([
                                'Asia/Kolkata' => 'Asia/Kolkata (IST)',
                                'UTC' => 'UTC',
                                'America/New_York' => 'America/New_York (EST/EDT)',
                                'Europe/London' => 'Europe/London (GMT/BST)',
                            ])
                            ->default('Asia/Kolkata')
                            ->required(),
                        Forms\Components\DatePicker::make('until_date')
                            ->label('Recurrence Until Date')
                            ->default(fn (Batch $record) => $record->end_date)
                            ->required(),
                        Forms\Components\Select::make('lead_teacher_id')
                            ->label('Assign Lead Teacher')
                            ->options(User::role('teacher')->pluck('first_name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Toggle::make('exclude_holidays')
                            ->label('Skip Institute Holidays')
                            ->default(true),
                    ])
                    ->action(function (Batch $record, array $data, GenerateSessionsAction $action) {
                        $schedule = BatchSchedule::create([
                            'batch_id' => $record->id,
                            'days_of_week' => array_map('intval', $data['days_of_week']),
                            'start_time' => $data['start_time'],
                            'end_time' => $data['end_time'],
                            'timezone' => $data['timezone'],
                            'until_date' => $data['until_date'],
                            'exclude_holidays' => ! empty($data['exclude_holidays']),
                        ]);

                        $leadTeacher = ! empty($data['lead_teacher_id']) ? User::find($data['lead_teacher_id']) : null;
                        $sessions = $action->execute($record, $schedule, $leadTeacher);

                        Notification::make()
                            ->title("Generated {$sessions->count()} sessions successfully")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBatches::route('/'),
            'create' => Pages\CreateBatch::route('/create'),
            'edit' => Pages\EditBatch::route('/{record}/edit'),
        ];
    }
}
