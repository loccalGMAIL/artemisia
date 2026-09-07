<?php

namespace App\Filament\Resources\Rubros\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RubroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required(),
                Toggle::make('es_recurrente')
                    ->required(),
                Toggle::make('activo')
                    ->required(),
            ]);
    }
}
