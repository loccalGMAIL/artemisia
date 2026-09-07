<?php

namespace App\Filament\Resources\Servicios\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class PreciosRelationManager extends RelationManager
{
    protected static string $relationship = 'precios';

    protected static ?string $title = 'Lista de precios';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('categoria_cliente_id')
                    ->label('Categoría de cliente')
                    ->relationship('categoriaCliente', 'nombre')
                    ->placeholder('Todas las categorías (precio único)')
                    ->searchable()
                    ->preload(),
                TextInput::make('precio')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                DatePicker::make('vigente_desde')
                    ->default(Carbon::today())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('precio')
            ->defaultSort('vigente_desde', 'desc')
            ->columns([
                TextColumn::make('categoriaCliente.nombre')
                    ->label('Categoría')
                    ->placeholder('Todas (precio único)')
                    ->badge(),
                TextColumn::make('precio')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('vigente_desde')
                    ->label('Vigente desde')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon(Heroicon::Plus),
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
