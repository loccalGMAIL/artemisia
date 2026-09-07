<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoleForm
{
    /**
     * Roles que decide `User::canAccessPanel()`. Se les bloquea el nombre
     * para que no se puedan renombrar por accidente y romper el acceso a
     * los paneles (el borrado ya está bloqueado en `RolePolicy`).
     *
     * @var list<string>
     */
    private const ROLES_PROTEGIDOS = ['staff', 'cliente'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Role $record): bool => $record !== null && in_array($record->name, self::ROLES_PROTEGIDOS, strict: true))
                    ->helperText(fn (?Role $record): ?string => $record !== null && in_array($record->name, self::ROLES_PROTEGIDOS, strict: true)
                        ? 'Este rol decide el acceso a los paneles y no se puede renombrar.'
                        : null),
                Hidden::make('guard_name')
                    ->default('web'),
                CheckboxList::make('permissions')
                    ->label('Permisos')
                    ->relationship('permissions', 'name')
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(3)
                    ->gridDirection('row'),
            ]);
    }
}
