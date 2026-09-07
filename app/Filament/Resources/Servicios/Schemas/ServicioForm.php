<?php

namespace App\Filament\Resources\Servicios\Schemas;

use App\Enums\UnidadServicio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServicioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('rubro_id')
                    ->label('Rubro')
                    ->relationship('rubro', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->required(),
                        Toggle::make('es_recurrente')
                            ->label('Es recurrente')
                            ->helperText('Genera contratos de 3 meses en vez de producciones puntuales.'),
                        Toggle::make('activo')
                            ->default(true),
                    ])
                    ->editOptionForm([
                        TextInput::make('nombre')
                            ->required(),
                        Toggle::make('es_recurrente')
                            ->label('Es recurrente')
                            ->helperText('Genera contratos de 3 meses en vez de producciones puntuales.'),
                        Toggle::make('activo'),
                    ]),
                TextInput::make('nombre')
                    ->required(),
                Textarea::make('descripcion')
                    ->columnSpanFull(),
                Select::make('unidad')
                    ->options(UnidadServicio::class)
                    ->default('unidad')
                    ->required(),
                Toggle::make('activo')
                    ->required(),
            ]);
    }
}
