<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Catalog\Actions\BulkPublishCourses;
use App\Domain\Catalog\Actions\CancelCourse;
use App\Domain\Catalog\Actions\DuplicateCourse;
use App\Domain\Catalog\Actions\TransitionCourseStatus;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Course;
use App\Filament\Admin\Resources\CourseResource\Pages;
use App\Filament\Admin\Resources\CourseResource\RelationManagers\ModulesRelationManager;
use App\Filament\Admin\Resources\CourseResource\RelationManagers\ReviewsRelationManager;
use App\Filament\Admin\Resources\CourseResource\RelationManagers\TeachersRelationManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
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
                Forms\Components\Tabs::make('Course Configuration')
                    ->tabs([
                        // Tab 1: General
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Course Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Forms\Components\Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('type')
                                    ->options(collect(CourseType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                                    ->default(CourseType::ShortTerm->value)
                                    ->required(),
                                Forms\Components\Select::make('default_delivery_mode')
                                    ->label('Delivery Mode')
                                    ->options(collect(DeliveryMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                                    ->default(DeliveryMode::Online->value)
                                    ->required(),
                                Forms\Components\Select::make('difficulty_level')
                                    ->options(collect(DifficultyLevel::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()]))
                                    ->default(DifficultyLevel::AllLevels->value)
                                    ->required(),
                                Forms\Components\TextInput::make('duration_hours')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('hours'),
                                Forms\Components\Select::make('language')
                                    ->options([
                                        'en' => 'English',
                                        'sa' => 'Sanskrit',
                                        'hi' => 'Hindi',
                                    ])
                                    ->default('en')
                                    ->required(),
                            ])->columns(2),

                        // Tab 2: Curriculum & Description
                        Forms\Components\Tabs\Tab::make('Description & Outcomes')
                            ->schema([
                                Forms\Components\Textarea::make('short_description')
                                    ->maxLength(500)
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('long_description')
                                    ->label('Detailed Course Syllabus & Overview')
                                    ->columnSpanFull(),
                                Forms\Components\TagsInput::make('outcomes')
                                    ->label('Learning Outcomes (Press Enter after each outcome)')
                                    ->placeholder('e.g. Master the 8 limbs of Raja Yoga')
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('prerequisites_text')
                                    ->label('Prerequisites & Eligibility Guidelines')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),

                        // Tab 3: Pricing & Enrollment
                        Forms\Components\Tabs\Tab::make('Pricing & Schedule')
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
                                Forms\Components\TextInput::make('enrollment_limit')
                                    ->label('Maximum Student Enrollment Limit')
                                    ->numeric()
                                    ->nullable()
                                    ->helperText('Leave empty for unlimited enrollment capacity'),
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Batch / Course Start Date'),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Batch / Course End Date'),
                            ])->columns(2),

                        // Tab 4: Completion Rules
                        Forms\Components\Tabs\Tab::make('Completion Rules')
                            ->schema([
                                Forms\Components\Section::make('Certification Criteria')
                                    ->description('Configure minimum thresholds required for course completion and certificate issuance.')
                                    ->schema([
                                        Forms\Components\TextInput::make('completion_rules.min_attendance_percent')
                                            ->label('Minimum Live Attendance (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->default(80)
                                            ->suffix('%'),
                                        Forms\Components\TextInput::make('completion_rules.min_score_percent')
                                            ->label('Minimum Assessment Score (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->default(70)
                                            ->suffix('%'),
                                        Forms\Components\TextInput::make('completion_rules.assignments_required')
                                            ->label('Mandatory Assignments to Submit')
                                            ->numeric()
                                            ->default(0),
                                        Forms\Components\Toggle::make('completion_rules.manual_approval')
                                            ->label('Require Acharya / Lead Teacher Manual Approval')
                                            ->default(false),
                                    ])->columns(2),
                            ]),

                        // Tab 5: SEO & Cover
                        Forms\Components\Tabs\Tab::make('SEO & Media')
                            ->schema([
                                Forms\Components\TextInput::make('cover_image_path')
                                    ->label('Cover Image URL / Storage Path')
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('SEO Meta Title')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('seo_description')
                                    ->label('SEO Meta Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (CourseStatus|string $state): string => match ($state instanceof CourseStatus ? $state : CourseStatus::tryFrom((string) $state)) {
                        CourseStatus::Draft => 'gray',
                        CourseStatus::InReview => 'warning',
                        CourseStatus::Published => 'success',
                        CourseStatus::Archived => 'danger',
                        CourseStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rating_average')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state, Course $record) => $record->reviews_count > 0 ? "★ {$state} ({$record->reviews_count})" : 'No reviews')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_fee_amount')
                    ->label('Fee')
                    ->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_delivery_mode')
                    ->label('Mode')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CourseStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('difficulty_level')
                    ->options(collect(DifficultyLevel::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])),
            ])
            ->actions([
                // 1. Preview as student action
                Tables\Actions\Action::make('preview_student')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Course $record): string => "/courses/{$record->slug}/preview")
                    ->openUrlInNewTab(),

                // 2. Lifecycle transitions
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

                // 3. Duplicate course action
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate Course')
                    ->modalDescription('Create a full duplicate of this course including modules, lessons, resources, and completion criteria.')
                    ->action(function (Course $record, DuplicateCourse $action) {
                        $newCourse = $action->execute($record);
                        Notification::make()
                            ->title('Course duplicated successfully')
                            ->body("Created '{$newCourse->name}' with draft status.")
                            ->success()
                            ->send();
                    }),

                // 4. Cancel course action
                Tables\Actions\Action::make('cancel_course')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->placeholder('Specify why this course is being cancelled...'),
                    ])
                    ->visible(fn (Course $record) => $record->status !== CourseStatus::Cancelled)
                    ->action(function (Course $record, array $data, CancelCourse $action) {
                        $action->execute($record, $data['cancellation_reason'], auth()->user());
                        Notification::make()
                            ->title('Course cancelled')
                            ->body('Enrolled students notified and refunds flagged for review.')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Bulk publish action per spec
                    Tables\Actions\BulkAction::make('bulk_publish')
                        ->label('Bulk Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, BulkPublishCourses $action) {
                            $count = $action->execute($records, auth()->user());
                            Notification::make()
                                ->title("{$count} courses published successfully")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ModulesRelationManager::class,
            TeachersRelationManager::class,
            ReviewsRelationManager::class,
        ];
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
