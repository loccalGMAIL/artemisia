<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Enums\EstadoPago;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResumenesMensualesRelationManager extends RelationManager
{
    protected static string $relationship = 'resumenesMensuales';

    protected static ?string $title = 'Resúmenes mensuales';

    /**
     * Los resúmenes se crean únicamente vía la acción "Generar resúmenes" del
     * contrato, para garantizar que los 3 periodos queden precargados con el
     * plan base del presupuesto.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('periodo')
                    ->disabled(),
                TextInput::make('monto_base')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                TextInput::make('monto_extras')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                TextInput::make('monto_total')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                Select::make('estado_pago')
                    ->label('Estado de pago')
                    ->options(EstadoPago::class)
                    ->required(),
                DatePicker::make('fecha_pago')
                    ->label('Fecha de pago'),
                Toggle::make('enviado'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('periodo')
            ->defaultSort('periodo')
            ->columns([
                TextColumn::make('periodo'),
                TextColumn::make('monto_base')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('monto_extras')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('monto_total')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('estado_pago')
                    ->badge(),
                TextColumn::make('fecha_pago')
                    ->date()
                    ->placeholder('-'),
                IconColumn::make('enviado')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
