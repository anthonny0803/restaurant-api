<?php

namespace App\Filament\Resources;

use App\Enums\MenuCategory;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string | \UnitEnum | null $navigationGroup = 'Restaurante';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Platos';

    protected static ?string $modelLabel = 'Plato';

    protected static ?string $pluralModelLabel = 'Platos';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('price')
                            ->label('Precio')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('€'),

                        Select::make('category')
                            ->label('Categoria')
                            ->options(MenuCategory::class)
                            ->required(),

                        TextInput::make('daily_stock')
                            ->label('Stock diario')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Dejar vacio para stock ilimitado'),

                        Textarea::make('description')
                            ->label('Descripcion')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Toggle::make('is_available')
                            ->label('Disponible')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Destacado')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Precio')
                    ->money('EUR', locale: 'es')
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Categoria')
                    ->sortable()
                    ->badge()
                    ->color(fn(MenuCategory $state): string => match ($state) {
                        MenuCategory::Entrantes => 'info',
                        MenuCategory::Principales => 'success',
                        MenuCategory::Postres => 'warning',
                        MenuCategory::Bebidas => 'primary',
                    }),

                TextColumn::make('daily_stock')
                    ->label('Stock diario')
                    ->sortable()
                    ->placeholder('Ilimitado'),

                IconColumn::make('is_available')
                    ->label('Disponible')
                    ->boolean(),

                IconColumn::make('is_featured')
                    ->label('Destacado')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_available')
                    ->label('Disponibilidad')
                    ->trueLabel('Disponibles')
                    ->falseLabel('No disponibles'),

                SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(MenuCategory::class),

                TernaryFilter::make('is_featured')
                    ->label('Destacados')
                    ->trueLabel('Destacados')
                    ->falseLabel('No destacados'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
