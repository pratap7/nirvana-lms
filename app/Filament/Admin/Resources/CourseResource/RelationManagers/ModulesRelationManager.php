<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CourseResource\RelationManagers;

use App\Domain\Catalog\Enums\LessonContentType;
use App\Domain\Catalog\Enums\LessonDripType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ModulesRelationManager extends RelationManager
{
    protected static string $relationship = 'modules';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Module Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_locked_by_default')
                    ->label('Locked by default')
                    ->default(false),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),

                // Embedded Lessons Repeater with Drag-and-Drop ordering within Module
                Forms\Components\Section::make('Lessons in this Module')
                    ->description('Add and reorder lessons for this module.')
                    ->schema([
                        Forms\Components\Repeater::make('lessons')
                            ->relationship('lessons')
                            ->orderColumn('sort_order')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\Select::make('content_type')
                                    ->options(collect(LessonContentType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                                    ->default(LessonContentType::Video->value)
                                    ->required(),
                                Forms\Components\TextInput::make('duration_minutes')
                                    ->numeric()
                                    ->suffix('mins')
                                    ->default(15),
                                Forms\Components\Select::make('drip_type')
                                    ->options(collect(LessonDripType::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()]))
                                    ->default(LessonDripType::Immediate->value)
                                    ->required(),
                                Forms\Components\TextInput::make('drip_days')
                                    ->numeric()
                                    ->default(0)
                                    ->visible(fn (callable $get) => $get('drip_type') === LessonDripType::DaysAfterEnrollment->value),
                                Forms\Components\Toggle::make('is_published')
                                    ->label('Published')
                                    ->default(true),
                                Forms\Components\Toggle::make('is_mandatory')
                                    ->label('Required for completion')
                                    ->default(true),
                                Forms\Components\Toggle::make('is_free_preview')
                                    ->label('Free Preview')
                                    ->default(false),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Module Name')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('lessons_count')
                    ->counts('lessons')
                    ->label('Lessons')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_locked_by_default')
                    ->label('Locked')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
