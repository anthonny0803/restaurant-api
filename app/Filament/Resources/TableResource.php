<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TableResource\Pages;
use App\Models\Table;
use BackedEnum;
use Filament\Actions;
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
use Filament\Tables\Filters\TernaryFilter;

class TableResource extends Resource
{
    protected static ?string $model = Table::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string | \UnitEnum | null $navigationGroup = 'Restaurante';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Mesas';

    protected static ?string $modelLabel = 'Mesa';

    protected static ?string $pluralModelLabel = 'Mesas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),

                        TextInput::make('min_capacity')
                            ->label('Capacidad minima')
                            ->required()
                            ->numeric()
                            ->minValue(1),

                        TextInput::make('max_capacity')
                            ->label('Capacidad maxima')
                            ->required()
                            ->numeric()
                            ->gte('min_capacity'),

                        TextInput::make('location')
                            ->label('Ubicacion')
                            ->required()
                            ->maxLength(100),

                        Textarea::make('description')
                            ->label('Descripcion')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
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

                TextColumn::make('min_capacity')
                    ->label('Cap. minima')
                    ->sortable(),

                TextColumn::make('max_capacity')
                    ->label('Cap. maxima')
                    ->sortable(),

                TextColumn::make('location')
                    ->label('Ubicacion')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
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
            'index' => Pages\ListTables::route('/'),
            'create' => Pages\CreateTable::route('/create'),
            'edit' => Pages\EditTable::route('/{record}/edit'),
        ];
    }
}
