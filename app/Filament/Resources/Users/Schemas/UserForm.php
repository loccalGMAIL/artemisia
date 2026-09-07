<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\TipoUsuario;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (?string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))
                    ->helperText('Dejar en blanco para no cambiar la contraseña actual.'),
                Select::make('tipo')
                    ->options(TipoUsuario::class)
                    ->default(TipoUsuario::Staff)
                    ->live()
                    ->required(),
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nombre')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('tipo') === TipoUsuario::Cliente->value)
                    ->required(fn (Get $get): bool => $get('tipo') === TipoUsuario::Cliente->value),
            ]);
    }
}
