<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        /** @var array<string, array<string, string>> $modules */
        $modules = config('permissions.modules', []);

        $moduleSections = [];

        foreach ($modules as $moduleName => $permissions) {
            $options = [];
            foreach ($permissions as $name => $description) {
                $options[$name] = "{$name} — {$description}";
            }

            $moduleSections[] = Forms\Components\Section::make($moduleName.' Domain Permissions')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->relationship('permissions', 'name')
                        ->options($options)
                        ->bulkToggleable()
                        ->columns(2),
                ])
                ->collapsible();
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Role Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('e.g. Senior Acharya, Academic Counselor'),
                    ]),

                Forms\Components\Tabs::make('Permissions Matrix')
                    ->tabs(
                        collect($modules)->map(function (array $permissions, string $moduleName) {
                            $options = [];
                            foreach ($permissions as $name => $description) {
                                $options[$name] = "{$name} — {$description}";
                            }

                            return Forms\Components\Tabs\Tab::make($moduleName)
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->relationship('permissions', 'name')
                                        ->options($options)
                                        ->bulkToggleable()
                                        ->columns(2),
                                ]);
                        })->toArray()
                    )->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Assigned Users')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Role $record): bool => in_array($record->name, [
                        'Super Admin', 'Admin', 'Moderator', 'Teacher', 'Student', 'Guest Teacher',
                    ])),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
