<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Cms\Models\Testimonial;
use App\Filament\Admin\Resources\TestimonialResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('author_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('author_title')
                    ->placeholder('e.g. RYT-500 Certified Yoga Teacher')
                    ->maxLength(255),
                Forms\Components\TextInput::make('author_location')
                    ->placeholder('e.g. Rishikesh / San Francisco')
                    ->maxLength(255),
                Forms\Components\Select::make('rating')
                    ->options([
                        5 => '5 Stars ★★★★★',
                        4 => '4 Stars ★★★★☆',
                        3 => '3 Stars ★★★☆☆',
                    ])
                    ->default(5)
                    ->required(),
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Select::make('locale')
                    ->options([
                        'en' => 'English',
                        'hi' => 'Hindi',
                    ])
                    ->default('en')
                    ->required(),
                Forms\Components\Toggle::make('is_featured')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('quote')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('author_name')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('author_title')
                    ->limit(30),
                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state)),
                Tables\Columns\TextColumn::make('course.name')
                    ->limit(25),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean(),
                Tables\Columns\TextColumn::make('locale')
                    ->badge(),
            ])
            ->actions([
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
            'index' => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit' => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
