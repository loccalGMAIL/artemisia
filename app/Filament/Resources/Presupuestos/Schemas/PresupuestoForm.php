<?php

namespace App\Filament\Resources\Presupuestos\Schemas;

use App\Enums\EstadoPresupuesto;
use App\Models\Cliente;
use App\Models\Servicio;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class PresupuestoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nombre')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                DatePicker::make('fecha')
                    ->default(Carbon::today())
                    ->live()
                    ->required(),
                Select::make('estado')
                    ->options(EstadoPresupuesto::class)
                    ->default(EstadoPresupuesto::Borrador)
                    ->required(),
                TextInput::make('total')
                    ->numeric()
                    ->prefix('$')
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Se calcula automáticamente a partir de las líneas del detalle.'),
                Textarea::make('notas')
                    ->columnSpanFull(),
                Repeater::make('detalles')
                    ->relationship()
                    ->label('Detalle')
                    ->columnSpanFull()
                    ->addActionLabel('Agregar línea')
                    ->schema([
                        Select::make('servicio_id')
                            ->label('Servicio')
                            ->relationship('servicio', 'nombre')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {
                                if (! $state) {
                                    return;
                                }

                                $servicio = Servicio::find($state);

                                if (! $servicio) {
                                    return;
                                }

                                $categoriaClienteId = Cliente::find($get('../../cliente_id'))?->categoria_cliente_id;
                                $fecha = $get('../../fecha');

                                $precio = $servicio->precioVigente(
                                    $categoriaClienteId,
                                    $fecha ? Carbon::parse($fecha) : null,
                                );

                                if ($precio !== null) {
                                    $set('precio_unitario', $precio);
                                }
                            })
                            ->columnSpan(2),
                        TextInput::make('descripcion_libre')
                            ->label('Descripción (ítem libre)')
                            ->columnSpan(2),
                        TextInput::make('cantidad')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        TextInput::make('precio_unitario')
                            ->label('Precio unitario')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ])
                    ->columns(6)
                    ->defaultItems(0),
            ]);
    }
}
