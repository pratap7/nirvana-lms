<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Scheduling\Models\Venue;
use App\Filament\Admin\Resources\VenueResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Scheduling & Campus';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Venue / Ashram Name')
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
                Forms\Components\Select::make('timezone')
                    ->options([
                        'Asia/Kolkata' => 'Asia/Kolkata (IST +5:30)',
                        'UTC' => 'UTC (GMT)',
                        'America/New_York' => 'America/New_York (EST/EDT)',
                        'Europe/London' => 'Europe/London (GMT/BST)',
                    ])
                    ->default('Asia/Kolkata')
                    ->required(),
                Forms\Components\TextInput::make('city')
                    ->maxLength(100),
                Forms\Components\TextInput::make('state')
                    ->maxLength(100),
                Forms\Components\TextInput::make('country')
                    ->default('IN')
                    ->maxLength(2),
                Forms\Components\TextInput::make('postal_code')
                    ->maxLength(20),
                Forms\Components\Textarea::make('address_line_1')
                    ->label('Address')
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active Facility')
                    ->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Venue')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('branch.name')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rooms_count')
                    ->counts('rooms')
                    ->label('Rooms / Studios')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenues::route('/'),
            'create' => Pages\CreateVenue::route('/create'),
            'edit' => Pages\EditVenue::route('/{record}/edit'),
        ];
    }
}
