<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Catalog\Actions\ModerateCourseReview;
use App\Domain\Catalog\Enums\ReviewStatus;
use App\Domain\Catalog\Models\CourseReview;
use App\Filament\Admin\Resources\CourseReviewResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CourseReviewResource extends Resource
{
    protected static ?string $model = CourseReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Academic Catalog';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'name')
                    ->disabled(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'first_name')
                    ->disabled(),
                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('title')
                    ->disabled(),
                Forms\Components\Textarea::make('review_text')
                    ->rows(4)
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                    ->required(),
                Forms\Components\Textarea::make('moderation_note')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.name')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Student')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => "★ {$state}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('review_text')
                    ->limit(70)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ReviewStatus|string $state): string => match ($state instanceof ReviewStatus ? $state : ReviewStatus::tryFrom((string) $state)) {
                        ReviewStatus::Pending => 'warning',
                        ReviewStatus::Approved => 'success',
                        ReviewStatus::Rejected => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('course_id')
                    ->relationship('course', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CourseReview $record) => $record->status !== ReviewStatus::Approved)
                    ->action(function (CourseReview $record, ModerateCourseReview $action) {
                        $action->execute($record, ReviewStatus::Approved, auth()->user());
                        Notification::make()->title('Review approved and average rating updated')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (CourseReview $record) => $record->status !== ReviewStatus::Rejected)
                    ->action(function (CourseReview $record, ModerateCourseReview $action) {
                        $action->execute($record, ReviewStatus::Rejected, auth()->user());
                        Notification::make()->title('Review rejected')->warning()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseReviews::route('/'),
            'edit' => Pages\EditCourseReview::route('/{record}/edit'),
        ];
    }
}
