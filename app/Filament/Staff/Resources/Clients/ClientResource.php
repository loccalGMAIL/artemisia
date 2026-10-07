<?php

namespace App\Filament\Staff\Resources\Clients;

use App\Filament\Staff\Resources\Clients\Pages\CreateClient;
use App\Filament\Staff\Resources\Clients\Pages\EditClient;
use App\Filament\Staff\Resources\Clients\Pages\ListClients;
use App\Filament\Staff\Resources\Clients\Pages\ViewClient;
use App\Filament\Staff\Resources\Clients\RelationManagers\AccountsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\Clients\Schemas\ClientForm;
use App\Filament\Staff\Resources\Clients\Schemas\ClientInfolist;
use App\Filament\Staff\Resources\Clients\Tables\ClientsTable;
use App\Models\Client;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Staff side of clients (spec 003). Rules live in the client Actions and ClientPolicy;
 * this resource only lists, shows and triggers them.
 */
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $slug = 'clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getModelLabel(): string
    {
        return __('clients.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('clients.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    /**
     * Archived clients stay reachable (RF-27, RF-31); the list hides them by default (RF-50).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            AccountsRelationManager::class,
            HistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
