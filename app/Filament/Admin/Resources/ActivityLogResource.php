<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false; // Logs are strictly append-only
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Audit Event Metadata')
                    ->schema([
                        Forms\Components\TextInput::make('log_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('description')
                            ->disabled(),
                        Forms\Components\TextInput::make('event')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->disabled(),
                        Forms\Components\TextInput::make('causer.email')
                            ->label('Actor / Causer')
                            ->disabled(),
                        Forms\Components\TextInput::make('subject_type')
                            ->label('Subject Entity')
                            ->disabled(),
                        Forms\Components\TextInput::make('subject_id')
                            ->label('Subject ID')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Activity Payload & Client Context')
                    ->schema([
                        Forms\Components\KeyValue::make('properties.attributes')
                            ->label('New / Updated Values')
                            ->disabled(),
                        Forms\Components\KeyValue::make('properties.old')
                            ->label('Previous Values')
                            ->disabled(),
                        Forms\Components\TextInput::make('properties.ip_address')
                            ->label('Client IP')
                            ->disabled(),
                        Forms\Components\Textarea::make('properties.user_agent')
                            ->label('User Agent')
                            ->rows(2)
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Event')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('causer.email')
                    ->label('Actor')
                    ->searchable()
                    ->default('System / Guest'),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Target Entity')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('properties.ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('properties.impersonated_by')
                    ->label('Impersonator ID')
                    ->badge()
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'status_transition' => 'Status Transition',
                        'user_impersonation_started' => 'Impersonation Started',
                        'student_health_profile_accessed' => 'Health Profile Read',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Append-only: No delete bulk action allowed
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
