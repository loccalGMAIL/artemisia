<?php

namespace App\Filament\Staff\Resources\Accounts\Schemas;

use App\Enums\AccountRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('accounts.fields.name'))
                    ->required()
                    ->maxLength(150),
                TextInput::make('email')
                    ->label(__('accounts.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(150),
                Select::make('role')
                    ->label(__('accounts.fields.role'))
                    ->options(AccountRole::options())
                    ->required(),
            ]);
    }
}
