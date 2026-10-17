<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CourseResource\RelationManagers;

use App\Domain\Catalog\Actions\ModerateCourseReview;
use App\Domain\Catalog\Enums\ReviewStatus;
use App\Domain\Catalog\Models\CourseReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->maxLength(255),
                Forms\Components\Textarea::make('review_text')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Student')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => "★ {$state}"),
                Tables\Columns\TextColumn::make('review_text')
                    ->limit(60)
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
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CourseReview $record) => $record->status !== ReviewStatus::Approved)
                    ->action(function (CourseReview $record, ModerateCourseReview $action) {
                        $action->execute($record, ReviewStatus::Approved);
                        Notification::make()->title('Review approved and rating updated')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (CourseReview $record) => $record->status !== ReviewStatus::Rejected)
                    ->action(function (CourseReview $record, ModerateCourseReview $action) {
                        $action->execute($record, ReviewStatus::Rejected);
                        Notification::make()->title('Review rejected')->warning()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
