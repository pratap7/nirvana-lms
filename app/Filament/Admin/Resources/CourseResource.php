<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Catalog\Actions\TransitionCourseStatus;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Course;
use App\Filament\Admin\Resources\CourseResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Academic Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Course Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('program_id')
                                    ->relationship('program', 'name')
                                    ->nullable()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('difficulty_level')
                                    ->options(collect(DifficultyLevel::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()]))
                                    ->default(DifficultyLevel::AllLevels->value)
                                    ->required(),
                                Forms\Components\TextInput::make('duration_hours')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('hours'),
                                Forms\Components\TextInput::make('max_students')
                                    ->numeric()
                                    ->nullable()
                                    ->helperText('Leave empty for unlimited'),
                                Forms\Components\Textarea::make('short_description')
                                    ->maxLength(500)
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('long_description')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Pricing & Fees')
                            ->schema([
                                Forms\Components\TextInput::make('base_fee_amount')
                                    ->label('Base Fee (in paise, ₹1 = 100 paise)')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->helperText('e.g. ₹5,000 should be entered as 500000 paise'),
                                Forms\Components\Select::make('base_fee_currency')
                                    ->options([
                                        'INR' => 'INR (₹)',
                                        'USD' => 'USD ($)',
                                        'EUR' => 'EUR (€)',
                                    ])
                                    ->default('INR')
                                    ->required(),
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
                Tables\Columns\TextColumn::make('branch.name')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (CourseStatus|string $state): string => match ($state instanceof CourseStatus ? $state : CourseStatus::tryFrom((string) $state)) {
                        CourseStatus::Draft => 'gray',
                        CourseStatus::InReview => 'warning',
                        CourseStatus::Published => 'success',
                        CourseStatus::Archived => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('difficulty_level')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('base_fee_amount')
                    ->label('Fee')
                    ->formatStateUsing(fn ($state, Course $record) => '₹'.number_format($state / 100, 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_hours')
                    ->suffix(' hrs')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CourseStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('difficulty_level')
                    ->options(collect(DifficultyLevel::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])),
            ])
            ->actions([
                Tables\Actions\Action::make('submit_for_review')
                    ->label('Submit Review')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn (Course $record) => $record->status === CourseStatus::Draft)
                    ->action(function (Course $record, TransitionCourseStatus $action) {
                        $action->execute($record, CourseStatus::InReview, auth()->id());
                        Notification::make()->title('Course submitted for review')->success()->send();
                    }),

                Tables\Actions\Action::make('approve_publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Course $record) => $record->status === CourseStatus::InReview)
                    ->action(function (Course $record, TransitionCourseStatus $action) {
                        $action->execute($record, CourseStatus::Published, auth()->id());
                        Notification::make()->title('Course successfully published')->success()->send();
                    }),

                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Course $record) => $record->status === CourseStatus::Published)
                    ->action(function (Course $record, TransitionCourseStatus $action) {
                        $action->execute($record, CourseStatus::Archived, auth()->id());
                        Notification::make()->title('Course archived')->warning()->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
