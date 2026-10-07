<?php

namespace App\Filament\Staff\Resources\Accounts;

use App\Filament\Staff\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Staff\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Staff\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Staff\Resources\Accounts\Tables\AccountsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Account management for admins. Accounts are never edited or deleted from here:
 * only created, re-roled and (de)activated, always through the account Actions.
 */
class AccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getModelLabel(): string
    {
        return __('accounts.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounts.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
        ];
    }
}
