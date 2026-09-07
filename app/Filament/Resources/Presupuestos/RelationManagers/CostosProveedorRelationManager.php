<?php

namespace App\Filament\Resources\Presupuestos\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CostosProveedorRelationManager extends RelationManager
{
    protected static string $relationship = 'costosProveedor';

    protected static ?string $title = 'Costos de proveedores';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('proveedor_id')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('monto')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                Textarea::make('descripcion')
                    ->columnSpanFull(),
                DatePicker::make('fecha')
                    ->default(Carbon::today())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descripcion')
            ->columns([
                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable(),
                TextColumn::make('monto')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('descripcion')
                    ->limit(40),
                TextColumn::make('fecha')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
