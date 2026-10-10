<?php

namespace App\Filament\Staff\Resources\Contracts;

use App\Filament\Staff\Resources\Contracts\Pages\ListContracts;
use App\Filament\Staff\Resources\Contracts\Pages\ViewContract;
use App\Filament\Staff\Resources\Contracts\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\Contracts\RelationManagers\PeriodsRelationManager;
use App\Filament\Staff\Resources\Contracts\Schemas\ContractInfolist;
use App\Filament\Staff\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Contracts (spec 005). The rules live in the contract Actions and the policies; this resource
 * only lists and shows them and triggers those Actions.
 */
class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static ?string $slug = 'contracts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getModelLabel(): string
    {
        return __('contracts.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contracts.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    /** Contracts are created from a budget, never from a plain form. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            PeriodsRelationManager::class,
            HistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'view' => ViewContract::route('/{record}'),
        ];
    }
}
