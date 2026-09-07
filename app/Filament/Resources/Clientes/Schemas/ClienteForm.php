<?php

namespace App\Filament\Resources\Clientes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('categoria_cliente_id')
                    ->label('Categoría de cliente')
                    ->relationship('categoriaCliente', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->required(),
                        Textarea::make('descripcion')
                            ->columnSpanFull(),
                        Toggle::make('activo')
                            ->default(true),
                    ])
                    ->editOptionForm([
                        TextInput::make('nombre')
                            ->required(),
                        Textarea::make('descripcion')
                            ->columnSpanFull(),
                        Toggle::make('activo'),
                    ]),
                TextInput::make('nombre')
                    ->required(),
                TextInput::make('razon_social'),
                TextInput::make('cuit'),
                TextInput::make('email')
                    ->email(),
                TextInput::make('telefono')
                    ->tel(),
                TextInput::make('direccion'),
                Textarea::make('notas')
                    ->columnSpanFull(),
                Toggle::make('activo')
                    ->required(),
            ]);
    }
}
